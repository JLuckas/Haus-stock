<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Categories ("tags") for the catalogue:
 *  - ingredient categories (Pão, Carne, Queijo, Molho, Salada…) on inventory items;
 *  - product categories (Box, Hambúrguer, Bebida, Acompanhamento…) on products.
 *
 * They drive order customizations: an ingredient can be swapped for another of the same
 * category (Cheddar → Mussarela) and a product inside a box for another of the same category
 * (Coca → Guaraná). Order lines keep the customizations they were sold with.
 *
 * Existing items and products get a best-guess category from their name; edit them freely.
 */
return new class extends Migration
{
    private const INGREDIENT = [
        ['Pão', 'amber', ['pao', 'brioche', 'bun']],
        ['Carne', 'pink', ['blend', 'carne', 'hamburguer', 'smash', 'frango', 'costela']],
        ['Queijo', 'amber', ['queijo', 'cheddar', 'mussarela', 'muçarela', 'prato', 'parmesao', 'gorgonzola', 'provolone', 'catupiry']],
        ['Molho', 'violet', ['molho', 'maionese', 'ketchup', 'mostarda', 'barbecue', 'bbq', 'aioli', 'sauce']],
        ['Salada', 'teal', ['alface', 'tomate', 'cebola', 'picles', 'rucula', 'pepino', 'salada']],
        ['Bacon e embutidos', 'pink', ['bacon', 'calabresa', 'presunto', 'linguica']],
        ['Batata e acompanhamentos', 'amber', ['batata', 'onion', 'aneis', 'nuggets', 'mandioca']],
        ['Bebida', 'cyan', ['coca', 'guarana', 'refri', 'suco', 'agua', 'cerveja', 'fanta', 'sprite', 'pepsi']],
        ['Embalagem', 'gray', ['embalagem', 'caixa', 'saco', 'guardanapo', 'copo', 'canudo']],
        ['Outros', 'gray', []],
    ];

    private const PRODUCT = [
        ['Box', 'indigo', ['box', 'combo', 'kit']],
        ['Hambúrguer', 'pink', ['burger', 'burguer', 'hamburguer', 'smash', 'cheese', 'bacon']],
        ['Bebida', 'cyan', ['coca', 'guarana', 'refri', 'suco', 'agua', 'cerveja', 'fanta', 'sprite', 'pepsi', 'zero']],
        ['Acompanhamento', 'amber', ['batata', 'frita', 'onion', 'aneis', 'nuggets']],
        ['Sobremesa', 'violet', ['sobremesa', 'milkshake', 'shake', 'brownie', 'sorvete', 'petit']],
        ['Adicional', 'teal', ['adicional', 'extra']],
    ];

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);          // ingredient | product
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->string('color', 20)->default('gray');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['scope', 'slug']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('type')->constrained('categories')->nullOnDelete();
        });

        // SQLite rebuilds the table to add a foreign key and recreates the "unique among active
        // items" index without its WHERE clause; put the partial index back (no-op on PostgreSQL).
        DB::statement('DROP INDEX IF EXISTS inventory_items_name_active_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_items_name_active_unique ON inventory_items (name) WHERE deleted_at IS NULL');

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('categories')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // e.g. [{"type":"swap_ingredient","from_name":"Cheddar","to_name":"Mussarela",…}]
            $table->json('customizations')->nullable()->after('is_add_on');
        });

        $this->seed('ingredient', self::INGREDIENT, 'inventory_items');
        $this->seed('product', self::PRODUCT, 'products');
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('customizations'));
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::dropIfExists('categories');
    }

    /** @param  list<array{0: string, 1: string, 2: list<string>}>  $defaults */
    private function seed(string $scope, array $defaults, string $table): void
    {
        $ids = [];
        foreach ($defaults as $position => [$name, $color, $words]) {
            $ids[$name] = DB::table('categories')->insertGetId([
                'scope' => $scope, 'name' => $name, 'slug' => Str::slug($name), 'color' => $color,
                'position' => $position, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Best guess from the name, first matching category wins; anything else stays uncategorized.
        // Specific categories are tried first ("Adicional bacon" is an add-on, not a burger).
        $matchOrder = collect($defaults)->sortBy(fn (array $d): int => in_array($d[0], ['Adicional', 'Box'], true) ? 0 : 1)->values()->all();

        foreach (DB::table($table)->whereNull('category_id')->get(['id', 'name']) as $row) {
            $name = Str::slug($row->name, ' ');
            foreach ($matchOrder as [$category, , $words]) {
                foreach ($words as $word) {
                    if (preg_match('/\b'.preg_quote(Str::slug($word, ' '), '/').'/', $name)) {
                        DB::table($table)->where('id', $row->id)->update(['category_id' => $ids[$category]]);

                        continue 3;
                    }
                }
            }
        }
    }
};
