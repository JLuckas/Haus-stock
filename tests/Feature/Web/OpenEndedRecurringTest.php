<?php

namespace Tests\Feature\Web;

use App\Models\Expense;
use App\Models\ExpenseSeries;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Staff day payments that repeat with no set number of times: they keep coming until stopped. */
class OpenEndedRecurringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 10:00:00'); // a Sunday
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Ana, delivery rider, every Saturday from Nov 14. */
    private function everySaturday(array $overrides = []): void
    {
        $this->post(route('expenses.store'), $overrides + [
            'kind' => 'personnel', 'spent_on' => '2026-11-14', 'employee_name' => 'Ana', 'employee_role' => 'delivery',
            'amount' => '120', 'repeat' => 'weekly', 'tags' => ['sábado'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    private function dates(): array
    {
        return Expense::query()->orderBy('spent_on')->get()->map(fn (Expense $e) => $e->spent_on->toDateString())->all();
    }

    private function jumpTo(string $date): void
    {
        Carbon::setTestNow($date);
        app(ExpenseService::class)->materialize();
    }

    public function test_a_staff_payment_repeats_without_a_number_of_times(): void
    {
        // A number of times sent for staff is ignored: it repeats until stopped.
        $this->everySaturday(['occurrences' => '3']);

        $series = ExpenseSeries::query()->sole();
        $this->assertNull($series->occurrences);
        $this->assertTrue($series->isOpenEnded());

        // Up to 30 days ahead exist already (Dec 15 is the horizon).
        $this->assertSame(['2026-11-14', '2026-11-21', '2026-11-28', '2026-12-05', '2026-12-12'], $this->dates());
        $this->assertSame(5, $series->fresh()->generated_count);
        $this->assertTrue(Expense::query()->get()->every(fn (Expense $e) => $e->tags->pluck('slug')->all() === ['sabado'] && $e->employee_name === 'Ana'));
    }

    public function test_new_payments_keep_coming_as_time_passes_without_duplicates(): void
    {
        $this->everySaturday();

        $this->jumpTo('2026-11-29 08:00:00');
        $this->assertSame(['2026-11-14', '2026-11-21', '2026-11-28', '2026-12-05', '2026-12-12', '2026-12-19', '2026-12-26'], $this->dates());

        app(ExpenseService::class)->materialize();
        $this->assertSame(7, Expense::query()->count());
        $this->assertSame(range(1, 7), Expense::query()->orderBy('occurrence')->pluck('occurrence')->all());
    }

    public function test_a_deleted_payment_does_not_come_back_and_edits_carry_on(): void
    {
        $this->everySaturday();
        $last = Expense::query()->where('occurrence', 5)->sole();

        $this->delete(route('expenses.destroy', $last), ['scope' => 'this']);
        $this->jumpTo('2026-11-16 08:00:00');
        $this->assertNotContains('2026-12-12', $this->dates());

        // A raise from Dec 5 on: the payments created later copy it.
        $this->put(route('expenses.update', Expense::query()->where('occurrence', 4)->sole()), [
            'kind' => 'personnel', 'spent_on' => '2026-12-05', 'employee_name' => 'Ana', 'employee_role' => 'delivery',
            'amount' => '150', 'scope' => 'following',
        ])->assertSessionHasNoErrors();

        $this->jumpTo('2026-12-01 08:00:00');
        $this->assertSame('150.00', Expense::query()->where('spent_on', 'like', '2026-12-19%')->sole()->amount);
        $this->assertSame('120.00', Expense::query()->where('occurrence', 1)->sole()->amount);
    }

    public function test_stopping_removes_what_is_scheduled_and_creates_no_more(): void
    {
        $this->everySaturday();
        $series = ExpenseSeries::query()->sole();

        $this->get(route('expenses.series.index'))->assertOk()->assertSee('No end date')->assertSee('until stopped');

        $this->delete(route('expenses.series.destroy', $series), ['what' => 'upcoming'])->assertSessionHas('success');
        $this->assertSame(['2026-11-14'], $this->dates());     // Nov 14 already happened, it stays
        $this->assertTrue($series->fresh()->isStopped());

        $this->jumpTo('2027-01-10 08:00:00');
        $this->assertSame(['2026-11-14'], $this->dates());

        $this->get(route('expenses.series.index'))->assertOk()->assertSee('Finished');
    }

    public function test_deleting_this_and_the_next_ones_also_stops_it(): void
    {
        $this->everySaturday();

        $this->delete(route('expenses.destroy', Expense::query()->where('occurrence', 3)->sole()), ['scope' => 'following']);
        $this->jumpTo('2027-01-10 08:00:00');

        $this->assertSame(['2026-11-14', '2026-11-21'], $this->dates());
    }

    public function test_page_loads_and_the_scheduled_command_fill_it_in(): void
    {
        $this->everySaturday();
        Carbon::setTestNow('2026-12-20 08:00:00');
        Cache::flush();

        $this->get(route('dashboard'))->assertOk();
        $this->assertContains('2027-01-16', $this->dates()); // created by the page load

        Carbon::setTestNow('2027-01-20 08:00:00');
        $this->artisan('expenses:recurring')->assertSuccessful();
        $this->assertContains('2027-02-13', $this->dates());
    }

    public function test_an_existing_staff_payment_can_be_made_recurring_from_its_edit_page(): void
    {
        $this->post(route('expenses.store'), [
            'kind' => 'personnel', 'spent_on' => '2026-11-14', 'employee_name' => 'Ana', 'employee_role' => 'delivery',
            'amount' => '100', 'tags' => ['sábado'],
        ])->assertSessionHasNoErrors();
        $payment = Expense::query()->sole();
        $this->assertNull($payment->series_id);

        $this->get(route('expenses.index', 'personnel'))->assertSee(route('expenses.edit', $payment).'#repeat-block', false);
        $this->get(route('expenses.edit', $payment))->assertOk()->assertSee('name="repeat"', false)->assertDontSee('name="occurrences"', false);

        // Same edit can change the amount and make it weekly.
        $this->put(route('expenses.update', $payment), [
            'kind' => 'personnel', 'spent_on' => '2026-11-14', 'employee_name' => 'Ana', 'employee_role' => 'delivery',
            'amount' => '120', 'tags' => ['sábado'], 'repeat' => 'weekly',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $series = ExpenseSeries::query()->sole();
        $this->assertTrue($series->isOpenEnded());
        $this->assertSame($series->id, $payment->fresh()->series_id);
        $this->assertSame(1, $payment->fresh()->occurrence);
        $this->assertSame(['2026-11-14', '2026-11-21', '2026-11-28', '2026-12-05', '2026-12-12'], $this->dates());
        $this->assertSame(['120.00'], Expense::query()->distinct()->pluck('amount')->all());
        $this->assertTrue(Expense::query()->get()->every(fn (Expense $e) => $e->tags->pluck('slug')->all() === ['sabado']));

        // Once in a series, the edit page offers the scope instead of "repeat".
        $this->get(route('expenses.edit', $payment))->assertOk()->assertDontSee('name="repeat"', false)->assertSee('name="scope"', false);

        // And it keeps coming.
        $this->jumpTo('2026-12-01 08:00:00');
        $this->assertContains('2026-12-26', $this->dates());
    }

    public function test_an_existing_bill_can_be_made_recurring_with_a_number_of_times(): void
    {
        $this->post(route('expenses.store'), ['kind' => 'bill', 'spent_on' => '2026-11-10', 'category' => 'internet', 'amount' => '99.90']);
        $bill = Expense::query()->sole();
        $data = ['kind' => 'bill', 'spent_on' => '2026-11-10', 'category' => 'internet', 'amount' => '99.90', 'repeat' => 'monthly'];

        $this->put(route('expenses.update', $bill), $data)->assertSessionHasErrors('occurrences');
        $this->assertSame(0, ExpenseSeries::query()->count());

        $this->put(route('expenses.update', $bill), $data + ['occurrences' => '3'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-10', '2026-12-10', '2027-01-10'], $this->dates());
        $this->assertSame(3, ExpenseSeries::query()->sole()->occurrences);
    }

    public function test_general_expenses_cannot_be_made_recurring(): void
    {
        $this->post(route('expenses.store'), ['kind' => 'general', 'spent_on' => '2026-11-10', 'category' => 'transport', 'amount' => '40']);
        $expense = Expense::query()->sole();

        $this->get(route('expenses.edit', $expense))->assertOk()->assertDontSee('name="repeat"', false);
        $this->put(route('expenses.update', $expense), ['kind' => 'general', 'spent_on' => '2026-11-10', 'category' => 'transport', 'amount' => '40', 'repeat' => 'weekly']);

        $this->assertSame(0, ExpenseSeries::query()->count());
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_bills_still_ask_for_the_number_of_times(): void
    {
        $this->post(route('expenses.store'), [
            'kind' => 'bill', 'spent_on' => '2026-11-10', 'category' => 'internet', 'amount' => '99.90', 'repeat' => 'monthly',
        ])->assertSessionHasErrors('occurrences');

        $this->get(route('expenses.index', 'personnel'))->assertOk()->assertSee('name="repeat"', false)->assertDontSee('name="occurrences"', false);
        $this->get(route('expenses.index', 'bill'))->assertOk()->assertSee('name="occurrences"', false);
    }
}
