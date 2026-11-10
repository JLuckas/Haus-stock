<?php

namespace App\Services\Finance;

use App\Enums\ExpenseKind;
use App\Models\Expense;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Creates or updates an expense and its tags (unknown tag names are created).
     *
     * @param  array<string, mixed>  $data  Validated input (see SaveExpenseRequest).
     */
    public function save(Expense $expense, array $data, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $userId): Expense {
            $personnel = ExpenseKind::from($data['kind']) === ExpenseKind::Personnel;

            $expense->fill([
                'kind' => $data['kind'],
                'spent_on' => $data['spent_on'],
                'amount' => round((float) $data['amount'], 2),
                'category' => $personnel ? null : ($data['category'] ?? null),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'employee_name' => $personnel ? trim((string) $data['employee_name']) : null,
                'employee_role' => $personnel ? $data['employee_role'] : null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);

            if (! $expense->exists) {
                $expense->created_by = $userId;
            }

            $expense->save();

            $tagIds = collect($data['tags'] ?? [])
                ->map(fn (string $name): string => Tag::normalize($name))
                ->filter()
                ->unique(fn (string $name): string => mb_strtolower($name))
                ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
                ->values()
                ->all();

            $expense->tags()->sync($tagIds);

            return $expense->load('tags');
        });
    }
}
