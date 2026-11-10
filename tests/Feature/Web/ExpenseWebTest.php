<?php

namespace Tests\Feature\Web;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Models\Expense;
use App\Models\Tag;
use App\Models\User;
use App\Services\Finance\ProfitAndLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function dayPayment(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'personnel',
            'spent_on' => today()->toDateString(),
            'employee_name' => 'Ana',
            'employee_role' => 'kitchen',
            'amount' => '120.50',
            'tags' => ['Fim de semana', 'diária'],
        ];
    }

    public function test_a_day_payment_is_saved_with_role_and_tags(): void
    {
        $this->post(route('expenses.store'), $this->dayPayment())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseKind::Personnel, $expense->kind);
        $this->assertSame(EmployeeRole::Kitchen, $expense->employee_role);
        $this->assertSame('Ana', $expense->employee_name);
        $this->assertNull($expense->category);
        $this->assertEqualsCanonicalizing(['fim-de-semana', 'diaria'], $expense->tags->pluck('slug')->all());

        $this->get(route('expenses.index', 'personnel'))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('Kitchen')
            ->assertSee('R$ 120,50');
    }

    public function test_every_role_is_accepted_and_others_are_rejected(): void
    {
        foreach (['kitchen', 'delivery', 'cashier'] as $role) {
            $this->post(route('expenses.store'), $this->dayPayment(['employee_role' => $role]))->assertSessionHasNoErrors();
        }

        $this->post(route('expenses.store'), $this->dayPayment(['employee_role' => 'manager']))->assertSessionHasErrors('employee_role');
        $this->post(route('expenses.store'), $this->dayPayment(['employee_name' => '']))->assertSessionHasErrors('employee_name');
        $this->assertSame(3, Expense::query()->count());
    }

    public function test_a_general_expense_needs_a_category_and_ignores_employee_fields(): void
    {
        $this->post(route('expenses.store'), [
            'kind' => 'general', 'spent_on' => today()->toDateString(), 'amount' => '350', 'description' => 'Conta de luz',
        ])->assertSessionHasErrors('category');

        $this->post(route('expenses.store'), [
            'kind' => 'general', 'spent_on' => today()->toDateString(), 'amount' => '350', 'category' => 'bills',
            'description' => 'Conta de luz', 'employee_name' => 'Ignored', 'employee_role' => 'kitchen', 'tags' => ['fixa'],
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseCategory::Bills, $expense->category);
        $this->assertNull($expense->employee_name);
        $this->assertNull($expense->employee_role);

        $this->get(route('expenses.index', 'general'))->assertOk()->assertSee('Conta de luz')->assertSee('fixa');
    }

    public function test_the_list_filters_by_tag_and_role(): void
    {
        $this->post(route('expenses.store'), $this->dayPayment(['employee_name' => 'Ana', 'tags' => ['extra']]));
        $this->post(route('expenses.store'), $this->dayPayment(['employee_name' => 'Bruno', 'employee_role' => 'delivery', 'tags' => []]));

        $this->get(route('expenses.index', ['kind' => 'personnel', 'tag' => 'extra']))
            ->assertOk()->assertSeeText('Ana')->assertDontSeeText('Bruno'); // as text: the name autocomplete lists everyone

        $this->get(route('expenses.index', ['kind' => 'personnel', 'group' => 'delivery']))
            ->assertOk()->assertSeeText('Bruno')->assertDontSeeText('Ana');
    }

    public function test_updating_replaces_tags_and_deleting_removes_the_expense(): void
    {
        $this->post(route('expenses.store'), $this->dayPayment(['tags' => ['a', 'b']]));
        $expense = Expense::query()->sole();

        $this->get(route('expenses.edit', $expense))->assertOk();

        $this->put(route('expenses.update', $expense), $this->dayPayment(['amount' => '90', 'tags' => ['b', 'c']]))
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('90.00', $expense->amount);
        $this->assertEqualsCanonicalizing(['b', 'c'], $expense->tags->pluck('slug')->all());
        $this->assertSame(3, Tag::query()->count()); // tags are kept for suggestions

        $this->delete(route('expenses.destroy', $expense))->assertRedirect();
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_expenses_are_subtracted_in_the_results(): void
    {
        $this->post(route('expenses.store'), $this->dayPayment(['amount' => '100']));
        $this->post(route('expenses.store'), [
            'kind' => 'general', 'spent_on' => today()->toDateString(), 'amount' => '50', 'category' => 'rent',
        ]);
        // Outside the period: not counted.
        $this->post(route('expenses.store'), $this->dayPayment(['amount' => '999', 'spent_on' => today()->subYears(1)->toDateString()]));

        $report = app(ProfitAndLossService::class)->report(today()->startOfMonth(), today());

        $this->assertSame(150.0, $report['expenses']);
        $this->assertSame(100.0, $report['expenses_by_kind']['personnel']);
        $this->assertSame(50.0, $report['expenses_by_kind']['general']);
        $this->assertSame(-150.0, $report['net']);

        $this->get(route('reports.pnl', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Expenses')
            ->assertSee('-R$ 150,00');
    }

    public function test_expenses_need_login(): void
    {
        auth()->logout();

        $this->get(route('expenses.index', 'personnel'))->assertRedirect(route('login'));
    }
}
