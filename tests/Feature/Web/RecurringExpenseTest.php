<?php

namespace Tests\Feature\Web;

use App\Enums\RecurrenceFrequency;
use App\Models\Expense;
use App\Models\ExpenseSeries;
use App\Models\User;
use App\Services\Finance\ProfitAndLossService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rent(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'bill',
            'spent_on' => '2026-11-10',
            'category' => 'rent',
            'description' => 'Aluguel',
            'amount' => '2500',
            'tags' => ['fixa'],
            'repeat' => 'monthly',
            'occurrences' => '12',
        ];
    }

    private function dates(): array
    {
        return Expense::query()->orderBy('occurrence')->get()->map(fn (Expense $e) => $e->spent_on->toDateString())->all();
    }

    public function test_dates_are_counted_from_the_first_one_without_drifting(): void
    {
        $start = CarbonImmutable::parse('2026-01-31');

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            array_map(fn ($d) => $d->toDateString(), RecurrenceFrequency::Monthly->dates($start, 4)));
        $this->assertSame(['2026-01-31', '2026-02-14', '2026-02-28'],
            array_map(fn ($d) => $d->toDateString(), RecurrenceFrequency::Biweekly->dates($start, 3)));
        $this->assertSame(['2026-01-31', '2026-04-30', '2026-07-31'],
            array_map(fn ($d) => $d->toDateString(), RecurrenceFrequency::Quarterly->dates($start, 3)));
        $this->assertSame(['2028-02-29', '2029-02-28'],
            array_map(fn ($d) => $d->toDateString(), RecurrenceFrequency::Yearly->dates(CarbonImmutable::parse('2028-02-29'), 2)));
    }

    public function test_a_repeating_expense_creates_every_occurrence_with_its_tags(): void
    {
        $this->post(route('expenses.store'), $this->rent())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $series = ExpenseSeries::query()->sole();
        $this->assertSame(RecurrenceFrequency::Monthly, $series->frequency);
        $this->assertSame(12, $series->occurrences);
        $this->assertSame(12, Expense::query()->count());
        $this->assertSame('2026-11-10', $this->dates()[0]);
        $this->assertSame('2027-10-10', $this->dates()[11]);
        $this->assertSame(range(1, 12), Expense::query()->orderBy('occurrence')->pluck('occurrence')->all());
        $this->assertTrue(Expense::query()->get()->every(fn (Expense $e) => $e->tags->pluck('slug')->all() === ['fixa']));
    }

    public function test_general_expenses_do_not_repeat_and_only_bills_take_a_number_of_times(): void
    {
        $this->post(route('expenses.store'), [
            'kind' => 'general', 'spent_on' => '2026-11-14', 'category' => 'transport', 'amount' => '40', 'repeat' => 'weekly', 'occurrences' => '4',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Expense::query()->count());
        $this->assertSame(0, ExpenseSeries::query()->count());

        // Bills: repeat × number of times. Staff: repeat until stopped (OpenEndedRecurringTest). General: no repeat.
        $this->get(route('expenses.index', 'general'))->assertOk()->assertDontSee('name="repeat"', false);
        $this->get(route('expenses.index', 'bill'))->assertOk()->assertSee('name="repeat"', false)->assertSee('name="occurrences"', false);
        $this->get(route('expenses.index', 'personnel'))->assertOk()->assertSee('name="repeat"', false)->assertDontSee('name="occurrences"', false);

        // Weekly bills work.
        $this->post(route('expenses.store'), $this->rent(['spent_on' => '2026-11-14', 'category' => 'gas', 'repeat' => 'weekly', 'occurrences' => '4']));
        $this->assertSame(['2026-11-14', '2026-11-21', '2026-11-28', '2026-12-05'], ExpenseSeries::query()->sole()->expenses->map(fn ($e) => $e->spent_on->toDateString())->all());
    }

    public function test_the_number_of_times_is_validated(): void
    {
        $this->post(route('expenses.store'), $this->rent(['occurrences' => '1']))->assertSessionHasErrors('occurrences');
        $this->post(route('expenses.store'), $this->rent(['occurrences' => '']))->assertSessionHasErrors('occurrences');
        $this->post(route('expenses.store'), $this->rent(['occurrences' => '999']))->assertSessionHasErrors('occurrences');
        $this->post(route('expenses.store'), $this->rent(['repeat' => 'hourly']))->assertSessionHasErrors('repeat');

        // "Does not repeat" ignores the count and saves one expense.
        $this->post(route('expenses.store'), $this->rent(['repeat' => 'none', 'occurrences' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::query()->count());
        $this->assertSame(0, ExpenseSeries::query()->count());
    }

    public function test_only_occurrences_up_to_today_count_in_results(): void
    {
        $this->post(route('expenses.store'), $this->rent(['spent_on' => '2026-09-15', 'repeat' => 'monthly', 'occurrences' => '6']));

        // Sep 15, Oct 15, Nov 15 are due (Nov 15 is today); Dec–Feb are scheduled.
        $report = app(ProfitAndLossService::class)->report(today()->subMonths(3), today());
        $this->assertSame(7500.0, $report['expenses']);

        $this->get(route('expenses.index', ['kind' => 'bill']))
            ->assertOk()
            ->assertSee('Coming up')
            ->assertSee('15/12/2026')
            ->assertSee('3/6');
    }

    public function test_editing_one_the_next_ones_or_the_whole_series(): void
    {
        $this->post(route('expenses.store'), $this->rent(['occurrences' => '5']));
        $third = Expense::query()->where('occurrence', 3)->sole();

        // Only this one: can move its date too.
        $this->put(route('expenses.update', $third), $this->rent(['amount' => '2600', 'spent_on' => '2027-01-12', 'scope' => 'this']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['2500.00', '2500.00', '2600.00', '2500.00', '2500.00'], Expense::query()->orderBy('occurrence')->pluck('amount')->all());
        $this->assertSame('2027-01-12', $third->fresh()->spent_on->toDateString());

        // This and the next ones: dates stay, amount and tags change from #3 on.
        $this->put(route('expenses.update', $third), $this->rent(['amount' => '2700', 'tags' => ['reajuste'], 'scope' => 'following']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['2500.00', '2500.00', '2700.00', '2700.00', '2700.00'], Expense::query()->orderBy('occurrence')->pluck('amount')->all());
        $this->assertSame('2027-01-12', $third->fresh()->spent_on->toDateString());
        $this->assertSame(['reajuste'], Expense::query()->where('occurrence', 5)->sole()->tags->pluck('slug')->all());
        $this->assertSame(['fixa'], Expense::query()->where('occurrence', 1)->sole()->tags->pluck('slug')->all());

        // The whole series.
        $this->put(route('expenses.update', $third), $this->rent(['amount' => '2000', 'description' => 'Aluguel loja', 'scope' => 'all']));
        $this->assertSame(['2000.00'], Expense::query()->distinct()->pluck('amount')->all());
        $this->assertSame(['Aluguel loja'], Expense::query()->distinct()->pluck('description')->all());

        $this->get(route('expenses.edit', $third))->assertOk()->assertSee('Occurrence 3 of 5');
    }

    public function test_deleting_one_the_next_ones_or_the_whole_series(): void
    {
        $this->post(route('expenses.store'), $this->rent(['occurrences' => '6']));
        $occurrence = fn (int $n) => Expense::query()->where('occurrence', $n)->sole();

        $this->delete(route('expenses.destroy', $occurrence(2)), ['scope' => 'this']);
        $this->assertSame([1, 3, 4, 5, 6], Expense::query()->orderBy('occurrence')->pluck('occurrence')->all());

        $this->delete(route('expenses.destroy', $occurrence(5)), ['scope' => 'following']);
        $this->assertSame([1, 3, 4], Expense::query()->orderBy('occurrence')->pluck('occurrence')->all());

        $this->delete(route('expenses.destroy', $occurrence(3)), ['scope' => 'all']);
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, ExpenseSeries::query()->count());
    }

    public function test_the_recurring_page_stops_or_deletes_a_series(): void
    {
        $this->post(route('expenses.store'), $this->rent(['spent_on' => '2026-10-01', 'occurrences' => '6']));
        $series = ExpenseSeries::query()->sole();

        $this->get(route('expenses.series.index'))
            ->assertOk()
            ->assertSee('Aluguel')
            ->assertSee('Monthly')
            ->assertSee('2/6')            // Oct 1 and Nov 1 done
            ->assertSee('01/12/2026');    // next one

        $this->delete(route('expenses.series.destroy', $series), ['what' => 'upcoming'])->assertSessionHas('success');
        $this->assertSame(['2026-10-01', '2026-11-01'], $this->dates());

        $this->delete(route('expenses.series.destroy', $series), ['what' => 'all']);
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, ExpenseSeries::query()->count());

        $this->get(route('expenses.series.index'))->assertOk()->assertSee('No recurring expenses yet');
    }
}
