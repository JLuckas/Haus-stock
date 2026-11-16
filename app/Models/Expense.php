<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operating expense: a staff day payment (personnel) or a general cost (rent, bills…).
 * Counted in the Resultado on its `spent_on` date.
 *
 * @property int $id
 * @property ExpenseKind $kind
 * @property \Illuminate\Support\Carbon $spent_on
 * @property string $amount
 * @property ExpenseCategory|null $category
 * @property string|null $description
 * @property string|null $employee_name
 * @property EmployeeRole|null $employee_role
 * @property string|null $notes
 * @property int|null $series_id      Set when the expense is one occurrence of a recurring series.
 * @property int|null $occurrence     1-based position in the series.
 */
class Expense extends Model
{
    protected $fillable = [
        'kind',
        'spent_on',
        'amount',
        'category',
        'description',
        'employee_name',
        'employee_role',
        'notes',
        'created_by',
        'series_id',
        'occurrence',
        'supplier_id',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ExpenseKind::class,
            'spent_on' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'employee_role' => EmployeeRole::class,
            'occurrence' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Lines of a supplier purchase. */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('id');
    }

    /** A supplier purchase whose items are already in stock. */
    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ExpenseSeries::class, 'series_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Main line in lists: the employee for personnel, the description (or category) otherwise. */
    public function title(): string
    {
        return match ($this->kind) {
            ExpenseKind::Personnel => (string) $this->employee_name,
            ExpenseKind::Supplier => (string) ($this->supplier?->name ?? $this->description ?? '—'),
            default => $this->description ?: ($this->category?->label() ?? '—'),
        };
    }

    /** Dated after today: a future occurrence of a recurring expense (not counted in the Resultado yet). */
    public function isScheduled(): bool
    {
        return $this->spent_on->isAfter(today());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('spent_on', '>', today()->format('Y-m-d').' 23:59:59');
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        // spent_on may be stored with a time part ("2026-11-09 00:00:00"), so cover whole days.
        return $query->whereBetween('spent_on', [$from->format('Y-m-d').' 00:00:00', $to->format('Y-m-d').' 23:59:59']);
    }
}
