<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The app is written with English keys (__('Add product')) and shown in Portuguese
 * through lang/pt_BR.json. Other tests run in English; these check the Portuguese side.
 */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_translation_key_used_by_the_app_has_a_portuguese_translation(): void
    {
        $missing = array_values(array_filter(
            $this->usedKeys(),
            fn (string $key): bool => ! Lang::has($key, 'pt_BR', false),
        ));

        $this->assertSame([], $missing, "Add these keys to lang/pt_BR.json:\n".implode("\n", $missing));
    }

    public function test_pages_render_in_portuguese(): void
    {
        $this->seed(StockDemoSeeder::class);
        app()->setLocale('pt_BR');
        $this->actingAs(User::factory()->create());

        $order = Order::query()->create([
            'saipos_order_id' => 'PT-1',
            'raw_payload' => ['order_id' => 'PT-1', 'items' => [['integration_code' => 'NOPE', 'desc_item' => 'X', 'quantity' => 1]]],
            'last_error' => 'erro',
        ]);

        $pages = [
            route('dashboard') => ['Painel', 'Dar baixa no estoque', 'Vendas hoje', 'Resultado do mês', 'Pedidos com falha', 'Visão geral', 'Buscar ou ir para…'],
            route('inventory.index') => ['Estoque', 'Em estoque', 'Somente abaixo do mínimo', 'Entrada', 'Dar baixa', '20 kg'],
            route('inventory.create') => ['Novo item de estoque', 'Estoque inicial', 'Ingrediente', 'Gramas (g)'],
            route('products.index') => ['Produtos', 'Consome do estoque', 'Combo', 'Receita', 'Revenda'],
            route('products.edit', Product::query()->where('saipos_sku_id', 'HAUS-BOX-CHEESE')->value('id')) => ['Uma unidade consome', 'Margem', 'Produtos dentro deste', 'Custo e preço', 'Preço recomendado', 'Margem alvo'],
            route('products.create') => ['Novo produto', 'Custo da receita', 'Custo total'],
            route('products.costs') => ['Catálogo', 'Custos', 'Margem alvo da loja', 'Preço recomendado'],
            route('expenses.index', 'personnel') => ['Despesas', 'Pessoal', 'Cozinha', 'Motoboy', 'Caixa', 'Registrar uma diária'],
            route('expenses.index', 'general') => ['Despesas gerais', 'Aluguel', 'Transporte', 'Registrar uma despesa'],
            route('orders.index') => ['Pedidos', 'Falhou', 'Reprocessar', 'Novo pedido'],
            route('orders.create') => ['Novo pedido', 'Registrar pedido', 'Resumo', 'Nº do pedido no Saipos (opcional)'],
            route('orders.show', $order) => ['Pedido PT-1', 'Não cadastrado', 'Cadastrar produto'],
            route('manual-deductions.create') => ['Nova baixa manual', 'Refeição de funcionário', 'O que você está baixando?'],
            route('reports.profitability') => ['Rentabilidade', 'Margem média', 'Margem baixa', 'Saudável', 'Margem dos produtos'],
            route('reports.pnl') => ['Financeiro', 'Faturamento', 'Lucro bruto', 'Perdas', 'Despesas', 'Resultado', 'Este mês'],
        ];

        foreach ($pages as $url => $expected) {
            $response = $this->get($url)->assertOk();

            foreach ($expected as $text) {
                $response->assertSee($text, false);
            }

            // A few English UI strings that must not leak through.
            foreach (['Add product', 'Below minimum only', 'Takes from stock', 'Profitability', 'Log Out'] as $english) {
                $response->assertDontSee('>'.$english.'<', false);
            }
        }
    }

    public function test_messages_and_validation_are_in_portuguese(): void
    {
        $this->seed(StockDemoSeeder::class);
        app()->setLocale('pt_BR');
        $this->actingAs(User::factory()->create());

        $bun = InventoryItem::query()->where('name', 'Pão brioche')->sole();

        $this->post(route('inventory.receive.store', $bun), ['quantity' => '10'])
            ->assertSessionHas('success', 'Entrada de 10 un de "Pão brioche". Estoque atual: 130 un.');

        $this->post(route('manual-deductions.store'), ['inventory_item_id' => $bun->id, 'quantity' => '0', 'reason' => ''])
            ->assertSessionHasErrors([
                'quantity' => 'O campo quantidade deve ser maior que 0.',
                'reason' => 'É obrigatória a indicação de um valor para o campo motivo.',
            ]);

        $this->delete(route('inventory.destroy', InventoryItem::query()->where('name', 'Bacon')->sole()))
            ->assertSessionHas('error', '"Bacon" não pode ser excluído porque é ingrediente de Adicional bacon e Box 1. Remova-o desses produtos primeiro.');

        $this->assertSame('há 2 minutos', now()->subMinutes(2)->diffForHumans());
    }

    public function test_the_layout_ships_the_theme_switcher(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee("localStorage.getItem('theme')", false) // applied before paint
            ->assertSee('themeSwitcher()', false);
    }

    /** @return list<string> */
    private function usedKeys(): array
    {
        $keys = [];

        foreach (['app' => '.php', 'resources/views' => '.blade.php'] as $dir => $suffix) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $single);
                preg_match_all('/(?:__|trans_choice)\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $source, $double);

                foreach ($single[1] as $key) {
                    $keys[] = str_replace("\\'", "'", $key);
                }

                foreach ($double[1] as $key) {
                    $keys[] = stripcslashes($key);
                }
            }
        }

        // Reasons are stored as English constants and translated when shown.
        array_push($keys, ...\App\Http\Controllers\ManualDeductionController::REASONS);

        return array_values(array_unique($keys));
    }
}
