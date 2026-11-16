<?php

namespace App\Services\Finance;

use App\Enums\ExpenseKind;
use App\Enums\RecurrenceFrequency;
use App\Models\Expense;
use App\Models\ExpenseSeries;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Expenses and recurring series of expenses.
 *
 * A series is stored as all of its occurrences up front, each an ordinary expense with its own
 * date. Editing or deleting one occurrence can reach just it, it and the following ones, or the
 * whole series (see the SCOPE_* constants).
 */
class ExpenseService
{
    public const SCOPE_THIS = 'this';

    public const SCOPE_FOLLOWING = 'following';

    public const SCOPE_ALL = 'all';

    public const MAX_OCCURRENCES = 366;

    /**
     * Creates or updates one expense and its tags (unknown tag names are created).
     *
     * @param  array<string, mixed>  $data  Validated input (see SaveExpenseRequest).
     */
    public function save(Expense $expense, array $data, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $userId): Expense {
            $expense->fill($this->attributes($data) + ['spent_on' => $data['spent_on']]);

            if (! $expense->exists) {
                $expense->created_by = $userId;
            }

            $expense->save();
            $expense->tags()->sync($this->tagIds($data['tags'] ?? []));

            return $expense->load('tags');
        });
    }

    /** How far ahead open-ended series are filled in (so the next payments show under "Coming up"). */
    public const HORIZON_DAYS = 30;

    /** Safety cap on occurrences created for one series in one run (e.g. a daily series left alone for months). */
    private const MAX_PER_RUN = 400;

    /**
     * Creates a recurring series, the first occurrence on $data['spent_on'].
     *  - $occurrences set (bills): all of them now. Monthly × 12 from 2026-01-31 → Jan 31, Feb 28, Mar 31 … Dec 31.
     *  - $occurrences null (staff day payments): open-ended; the ones up to HORIZON_DAYS ahead are
     *    created now and the rest as time passes (materialize()), until the series is stopped.
     */
    public function createSeries(array $data, RecurrenceFrequency $frequency, ?int $occurrences, ?int $userId = null): ExpenseSeries
    {
        $occurrences = $occurrences === null ? null : max(2, min(self::MAX_OCCURRENCES, $occurrences));
        $start = CarbonImmutable::parse($data['spent_on'])->startOfDay();

        return DB::transaction(function () use ($data, $frequency, $occurrences, $start, $userId): ExpenseSeries {
            $series = ExpenseSeries::query()->create([
                'frequency' => $frequency,
                'occurrences' => $occurrences,
                'generated_count' => 0,
                'starts_on' => $start,
                'created_by' => $userId,
            ]);

            $attributes = $this->attributes($data);
            $tagIds = $this->tagIds($data['tags'] ?? []);

            // Bills: every occurrence. Open-ended: the first one now (even if it's far ahead), the rest below.
            foreach ($frequency->dates($start, $occurrences ?? 1) as $index => $date) {
                $this->createOccurrence($series, $attributes, $tagIds, $index, $date, $userId);
            }
            $series->update(['generated_count' => $occurrences ?? 1]);

            if ($series->isOpenEnded()) {
                $this->materialize($series);
            }

            return $series->load('expenses');
        });
    }

    /**
     * Makes an existing one-off expense recurring: it becomes occurrence #1 of a new series that
     * starts on its date (its fields are updated from $data first). Bills get $occurrences copies;
     * staff payments ($occurrences null) repeat until stopped.
     */
    public function makeRecurring(Expense $expense, array $data, RecurrenceFrequency $frequency, ?int $occurrences): ExpenseSeries
    {
        if ($expense->series_id !== null) {
            throw new \LogicException('This expense already belongs to a series.');
        }

        $occurrences = $occurrences === null ? null : max(2, min(self::MAX_OCCURRENCES, $occurrences));

        return DB::transaction(function () use ($expense, $data, $frequency, $occurrences): ExpenseSeries {
            $this->save($expense, $data);
            $start = CarbonImmutable::parse($expense->spent_on)->startOfDay();

            $series = ExpenseSeries::query()->create([
                'frequency' => $frequency,
                'occurrences' => $occurrences,
                'generated_count' => 1,
                'starts_on' => $start,
                'created_by' => $expense->created_by,
            ]);
            $expense->forceFill(['series_id' => $series->id, 'occurrence' => 1])->save();

            if ($occurrences !== null) {
                $attributes = $this->attributes($data);
                $tagIds = $expense->tags->pluck('id')->all();

                foreach (array_slice($frequency->dates($start, $occurrences), 1, null, true) as $index => $date) {
                    $this->createOccurrence($series, $attributes, $tagIds, $index, $date, $expense->created_by);
                }
                $series->update(['generated_count' => $occurrences]);
            } else {
                $this->materialize($series);
            }

            return $series->load('expenses');
        });
    }

    /**
     * Creates the occurrences of running open-ended series dated up to $until (default: HORIZON_DAYS
     * from today). Each new one copies the latest occurrence, so edits made to "this and the next
     * ones" carry on. Safe to run often and concurrently: the series row is locked while it grows.
     *
     * @return int occurrences created
     */
    public function materialize(?ExpenseSeries $only = null, ?CarbonImmutable $until = null): int
    {
        $until ??= CarbonImmutable::today()->addDays(self::HORIZON_DAYS);
        $ids = $only ? [$only->id] : ExpenseSeries::query()->runningOpenEnded()->pluck('id')->all();
        $created = 0;

        foreach ($ids as $id) {
            $created += DB::transaction(function () use ($id, $until): int {
                $series = ExpenseSeries::query()->whereKey($id)->lockForUpdate()->first();
                if (! $series || ! $series->isOpenEnded() || $series->isStopped()) {
                    return 0;
                }

                $template = $series->expenses()->with('tags')->reorder('occurrence', 'desc')->first();
                if (! $template) {
                    // Every occurrence was deleted: nothing left to copy, so the series ends here.
                    $series->update(['stopped_on' => CarbonImmutable::today()]);

                    return 0;
                }

                $attributes = $template->only(['kind', 'amount', 'category', 'description', 'employee_name', 'employee_role', 'notes']);
                $tagIds = $template->tags->pluck('id')->all();
                $start = CarbonImmutable::parse($series->starts_on);
                $index = $series->generated_count;
                $made = 0;

                while ($made < self::MAX_PER_RUN && ($date = $series->frequency->dateFor($start, $index))->lessThanOrEqualTo($until)) {
                    $this->createOccurrence($series, $attributes, $tagIds, $index, $date, $template->created_by);
                    $index++;
                    $made++;
                }

                if ($made > 0) {
                    $series->update(['generated_count' => $index]);
                }

                return $made;
            });
        }

        return $created;
    }

    /**
     * Stops a series: removes its occurrences dated after today (past ones stay) and, for an
     * open-ended one, creates no more.
     *
     * @return int occurrences removed
     */
    public function stop(ExpenseSeries $series): int
    {
        return DB::transaction(function () use ($series): int {
            $removed = Expense::query()->where('series_id', $series->id)->upcoming()->delete();
            $series->update(['stopped_on' => CarbonImmutable::today()]);

            if (! Expense::query()->where('series_id', $series->id)->exists()) {
                $series->delete();
            }

            return $removed;
        });
    }

    private function createOccurrence(ExpenseSeries $series, array $attributes, array $tagIds, int $index, CarbonImmutable $date, ?int $userId): Expense
    {
        $expense = new Expense($attributes + [
            'spent_on' => $date->toDateString(),
            'series_id' => $series->id,
            'occurrence' => $index + 1,
        ]);
        $expense->created_by = $userId;
        $expense->save();
        $expense->tags()->sync($tagIds);

        return $expense;
    }

    /**
     * Updates an expense, or the occurrences of its series that $scope reaches.
     * Only "this" can move the date; the others keep each occurrence on its own date.
     *
     * @return int how many expenses were updated
     */
    public function update(Expense $expense, array $data, string $scope = self::SCOPE_THIS): int
    {
        if ($expense->series_id === null || $scope === self::SCOPE_THIS) {
            $this->save($expense, $data);

            return 1;
        }

        return DB::transaction(function () use ($expense, $data, $scope): int {
            $attributes = $this->attributes($data);
            $tagIds = $this->tagIds($data['tags'] ?? []);
            $targets = $this->scoped($expense, $scope)->get();

            foreach ($targets as $target) {
                $target->fill($attributes)->save();
                $target->tags()->sync($tagIds);
            }

            return $targets->count();
        });
    }

    /** @return int how many expenses were deleted */
    public function delete(Expense $expense, string $scope = self::SCOPE_THIS): int
    {
        return DB::transaction(function () use ($expense, $scope): int {
            $seriesId = $expense->series_id;

            if ($seriesId === null || $scope === self::SCOPE_THIS) {
                $expense->delete();
                $deleted = 1;
            } else {
                $deleted = $this->scoped($expense, $scope)->delete();
                // "This and the next ones" ends an open-ended series: none are created after it.
                ExpenseSeries::query()->whereKey($seriesId)->whereNull('stopped_on')->update(['stopped_on' => CarbonImmutable::today()]);
            }

            // A series with nothing left goes too.
            if ($seriesId !== null && ! Expense::query()->where('series_id', $seriesId)->exists()) {
                ExpenseSeries::query()->whereKey($seriesId)->delete();
            }

            return $deleted;
        });
    }

    /** The occurrences of $expense's series that $scope reaches. */
    private function scoped(Expense $expense, string $scope): Builder
    {
        return Expense::query()
            ->where('series_id', $expense->series_id)
            ->when($scope === self::SCOPE_FOLLOWING, fn (Builder $q) => $q->where('occurrence', '>=', $expense->occurrence))
            ->orderBy('occurrence');
    }

    /** Fields shared by every occurrence (everything but the date). */
    private function attributes(array $data): array
    {
        $personnel = ExpenseKind::from($data['kind']) === ExpenseKind::Personnel;

        return [
            'kind' => $data['kind'],
            'amount' => round((float) $data['amount'], 2),
            'category' => $personnel ? null : ($data['category'] ?? null),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'employee_name' => $personnel ? trim((string) $data['employee_name']) : null,
            'employee_role' => $personnel ? $data['employee_role'] : null,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }

    /** @param  list<string>  $names */
    private function tagIds(array $names): array
    {
        return collect($names)
            ->map(fn (string $name): string => Tag::normalize($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->values()
            ->all();
    }
}
