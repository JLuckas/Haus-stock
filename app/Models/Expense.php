<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected function casts(): array
    {
        return [
            'kind' => ExpenseKind::class,
            'spent_on' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'employee_role' => EmployeeRole::class,
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Main line in lists: the employee for personnel, the description (or category) otherwise. */
    public function title(): string
    {
        return $this->kind === ExpenseKind::Personnel
            ? (string) $this->employee_name
            : ($this->description ?: ($this->category?->label() ?? '—'));
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        // spent_on may be stored with a time part ("2026-11-09 00:00:00"), so cover whole days.
        return $query->whereBetween('spent_on', [$from->format('Y-m-d').' 00:00:00', $to->format('Y-m-d').' 23:59:59']);
    }
}
