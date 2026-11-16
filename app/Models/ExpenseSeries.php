<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring expense: copies of one expense every `frequency`, from `starts_on`.
 *  - With `occurrences` (bills): exactly that many, all created at once.
 *  - Without (staff day payments): open-ended. Occurrences are created a few weeks ahead and
 *    keep coming until the series is stopped (`stopped_on`). See ExpenseService::materialize().
 * The copies are ordinary expenses (see Expense::series()); this row only groups them.
 *
 * @property int $id
 * @property RecurrenceFrequency $frequency
 * @property int|null $occurrences      null = open-ended
 * @property int $generated_count       occurrences created so far (the next one continues from here)
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon|null $stopped_on
 */
class ExpenseSeries extends Model
{
    protected $table = 'expense_series';

    protected $fillable = ['frequency', 'occurrences', 'generated_count', 'starts_on', 'stopped_on', 'created_by'];

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'occurrences' => 'integer',
            'generated_count' => 'integer',
            'starts_on' => 'date',
            'stopped_on' => 'date',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'series_id')->orderBy('occurrence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** No set number of times: it repeats until stopped. */
    public function isOpenEnded(): bool
    {
        return $this->occurrences === null;
    }

    public function isStopped(): bool
    {
        return $this->stopped_on !== null;
    }

    /** Open-ended series that still need new occurrences created as time passes. */
    public function scopeRunningOpenEnded(Builder $query): Builder
    {
        return $query->whereNull('occurrences')->whereNull('stopped_on');
    }
}
