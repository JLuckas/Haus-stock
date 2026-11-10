<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Http\Requests\SaveExpenseRequest;
use App\Models\Expense;
use App\Models\Tag;
use App\Services\Finance\ExpenseService;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Despesas: staff day payments (Pessoal) and general costs (Despesas gerais), with tags. */
class ExpenseController extends Controller
{
    public function index(Request $request, string $kind): View
    {
        $kind = ExpenseKind::from($kind);
        $period = Period::fromRequest($request);
        $tag = $request->filled('tag') ? Tag::query()->where('slug', $request->query('tag'))->first() : null;
        $group = $request->query('group');

        $base = Expense::query()
            ->where('kind', $kind)
            ->between($period->from, $period->to)
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tag->id)));

        $expenses = (clone $base)
            ->when($group, fn ($q) => $q->where($kind === ExpenseKind::Personnel ? 'employee_role' : 'category', $group))
            ->with(['tags', 'creator'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        $all = (clone $base)->with('tags')->get();

        return view('expenses.index', [
            'kind' => $kind,
            'period' => $period,
            'activeTag' => $tag,
            'activeGroup' => $group,
            'days' => $expenses->groupBy(fn (Expense $e): string => $e->spent_on->toDateString()),
            'total' => (float) $all->sum('amount'),
            'count' => $all->count(),
            'groups' => $this->groups($kind, $all),
            'tagTotals' => $this->tagTotals($all),
            'counts' => [
                ExpenseKind::Personnel->value => Expense::query()->where('kind', ExpenseKind::Personnel)->between($period->from, $period->to)->count(),
                ExpenseKind::General->value => Expense::query()->where('kind', ExpenseKind::General)->between($period->from, $period->to)->count(),
            ],
        ] + $this->formData(new Expense(['kind' => $kind, 'spent_on' => today()])));
    }

    public function store(SaveExpenseRequest $request, ExpenseService $expenses): RedirectResponse
    {
        $expense = $expenses->save(new Expense, $request->validated(), $request->user()->id);

        return to_route('expenses.index', ['kind' => $expense->kind->value] + $request->only(['period', 'from', 'to']))
            ->with('success', __('Expense of :amount registered.', ['amount' => \App\Support\Money::format($expense->amount)]));
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.edit', $this->formData($expense->load('tags')));
    }

    public function update(SaveExpenseRequest $request, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        $expenses->save($expense, $request->validated());

        return to_route('expenses.index', $expense->kind->value)->with('success', __('Expense updated.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $kind = $expense->kind->value;
        $expense->delete();

        return back(fallback: route('expenses.index', $kind))->with('success', __('Expense deleted.'));
    }

    /** Totals per role (personnel) or per category (general), in display order. */
    private function groups(ExpenseKind $kind, Collection $expenses): array
    {
        $cases = $kind === ExpenseKind::Personnel ? EmployeeRole::cases() : ExpenseCategory::cases();
        $field = $kind === ExpenseKind::Personnel ? 'employee_role' : 'category';

        return collect($cases)
            ->map(function ($case) use ($expenses, $field): array {
                $matching = $expenses->filter(fn (Expense $e) => $e->{$field} === $case);

                return ['key' => $case->value, 'label' => $case->label(), 'total' => (float) $matching->sum('amount'), 'count' => $matching->count()];
            })
            // Personnel always shows its three roles; general only categories that were used.
            ->filter(fn (array $g) => $kind === ExpenseKind::Personnel || $g['count'] > 0)
            ->values()
            ->all();
    }

    /** @return list<array{tag: Tag, total: float, count: int}> */
    private function tagTotals(Collection $expenses): array
    {
        return $expenses
            ->flatMap(fn (Expense $e) => $e->tags->map(fn (Tag $t) => ['tag' => $t, 'amount' => (float) $e->amount]))
            ->groupBy(fn (array $row) => $row['tag']->id)
            ->map(fn (Collection $rows) => ['tag' => $rows->first()['tag'], 'total' => $rows->sum('amount'), 'count' => $rows->count()])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function formData(Expense $expense): array
    {
        // Previous employees with their last role and amount, so typing a name can pre-fill the rest.
        $employees = Expense::query()
            ->where('kind', ExpenseKind::Personnel)
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->limit(500)
            ->get(['employee_name', 'employee_role', 'amount'])
            ->unique(fn (Expense $e) => mb_strtolower((string) $e->employee_name))
            ->mapWithKeys(fn (Expense $e) => [$e->employee_name => ['role' => $e->employee_role?->value, 'amount' => (string) $e->amount]])
            ->all();

        return [
            'expense' => $expense,
            'knownEmployees' => $employees,
            'tagSuggestions' => Tag::query()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
