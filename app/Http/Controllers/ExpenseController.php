<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeRole;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseKind;
use App\Http\Requests\SaveExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseSeries;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Tag;
use App\Services\Finance\ExpenseService;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Despesas: staff day payments (Pessoal) and general costs (Despesas gerais), with tags and recurring series. */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

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
            ->when($group, fn ($q) => $q->where(self::groupField($kind), $group))
            ->with(['tags', 'creator', 'series', 'supplier', 'items.inventoryItem'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        $all = (clone $base)->with('tags')->get();

        // Scheduled occurrences of recurring expenses, soonest first.
        $upcoming = Expense::query()->where('kind', $kind)->upcoming()->with(['tags', 'series'])
            ->orderBy('spent_on')->orderBy('id')->limit(6)->get();
        $next30 = (float) Expense::query()->where('kind', $kind)->upcoming()
            ->where('spent_on', '<=', today()->addDays(30)->format('Y-m-d').' 23:59:59')->sum('amount');

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
            'upcoming' => $upcoming,
            'next30' => $next30,
            'counts' => self::tabCounts($period),
        ] + $this->formData(new Expense(['kind' => $kind, 'spent_on' => today()])));
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $kindQuery = ['kind' => $data['kind']] + $request->only(['period', 'from', 'to']);

        if ($frequency = $request->frequency()) {
            $withCount = ExpenseKind::from($data['kind'])->repeatsWithCount();
            $series = $this->expenses->createSeries($data, $frequency, $withCount ? (int) $data['occurrences'] : null, $request->user()->id);
            $first = $series->expenses->first();

            if (! $withCount) {
                return to_route('expenses.index', $kindQuery)->with('success', __('Repeating :frequency from :first, until you stop it.', [
                    'frequency' => mb_strtolower($frequency->label()),
                    'first' => $first->spent_on->format('d/m/Y'),
                ]));
            }

            $last = $series->expenses->last();

            return to_route('expenses.index', $kindQuery)->with('success', __(':count expenses of :amount scheduled (:frequency), from :first to :last.', [
                'count' => $series->occurrences,
                'amount' => Money::format($first->amount),
                'frequency' => mb_strtolower($frequency->label()),
                'first' => $first->spent_on->format('d/m/Y'),
                'last' => $last->spent_on->format('d/m/Y'),
            ]));
        }

        $expense = $this->expenses->save(new Expense, $data, $request->user()->id);

        return to_route('expenses.index', $kindQuery)
            ->with('success', __('Expense of :amount registered.', ['amount' => Money::format($expense->amount)]));
    }

    public function edit(Expense $expense): View|RedirectResponse
    {
        if ($expense->kind === ExpenseKind::Supplier) {
            return to_route('expenses.purchases.edit', $expense);
        }

        return view('expenses.edit', $this->formData($expense->load(['tags', 'series'])));
    }

    public function update(SaveExpenseRequest $request, Expense $expense): RedirectResponse
    {
        // A one-off expense set to repeat becomes the first occurrence of a new series.
        if ($expense->series_id === null && ($frequency = $request->frequency())) {
            $data = $request->validated();
            $withCount = ExpenseKind::from($data['kind'])->repeatsWithCount();
            $this->expenses->makeRecurring($expense, $data, $frequency, $withCount ? (int) $data['occurrences'] : null);

            return to_route('expenses.index', $expense->kind->value)->with('success', $withCount
                ? __('Now repeating :frequency, :count times from :first.', ['frequency' => mb_strtolower($frequency->label()), 'count' => (int) $data['occurrences'], 'first' => $expense->spent_on->format('d/m/Y')])
                : __('Repeating :frequency from :first, until you stop it.', ['frequency' => mb_strtolower($frequency->label()), 'first' => $expense->spent_on->format('d/m/Y')]));
        }

        $count = $this->expenses->update($expense, $request->validated(), $request->validated('scope') ?? ExpenseService::SCOPE_THIS);

        return to_route('expenses.index', $expense->kind->value)
            ->with('success', $count > 1 ? __(':count expenses updated.', ['count' => $count]) : __('Expense updated.'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $scope = $request->validate([
            'scope' => ['nullable', Rule::in([ExpenseService::SCOPE_THIS, ExpenseService::SCOPE_FOLLOWING, ExpenseService::SCOPE_ALL])],
        ])['scope'] ?? ExpenseService::SCOPE_THIS;

        $kind = $expense->kind->value;
        $count = $this->expenses->delete($expense, $scope);

        return back(fallback: route('expenses.index', $kind))
            ->with('success', $count > 1 ? __(':count expenses deleted.', ['count' => $count]) : __('Expense deleted.'));
    }

    /** Badge counts for the Expenses tabs (shared with the Recurring and Tags pages). */
    public static function tabCounts(?Period $period = null): array
    {
        // Same period as the list pages (this month unless the URL says otherwise), so badges agree.
        $period ??= Period::fromRequest(request());
        $inPeriod = fn (ExpenseKind $kind) => Expense::query()->where('kind', $kind)
            ->between($period->from, $period->to)->count();

        return [
            ExpenseKind::Personnel->value => $inPeriod(ExpenseKind::Personnel),
            ExpenseKind::Bill->value => $inPeriod(ExpenseKind::Bill),
            ExpenseKind::Supplier->value => $inPeriod(ExpenseKind::Supplier),
            ExpenseKind::General->value => $inPeriod(ExpenseKind::General),
            'series' => ExpenseSeries::query()->count(),
            'tags' => Tag::query()->count(),
        ];
    }

    /** Totals per role (personnel) or per category (general), in display order. */
    private function groups(ExpenseKind $kind, Collection $expenses): array
    {
        if ($kind === ExpenseKind::Supplier) {
            // Biggest suppliers of the period first.
            return $expenses->groupBy('supplier_id')
                ->map(fn (Collection $rows, $id): array => ['key' => (string) $id, 'label' => $rows->first()->supplier?->name ?? '—', 'total' => (float) $rows->sum('amount'), 'count' => $rows->count()])
                ->sortByDesc('total')->values()->all();
        }

        $cases = $kind === ExpenseKind::Personnel ? EmployeeRole::cases() : ExpenseCategory::forKind($kind);
        $field = self::groupField($kind);

        return collect($cases)
            ->map(function ($case) use ($expenses, $field): array {
                $matching = $expenses->filter(fn (Expense $e) => $e->{$field} === $case);

                return ['key' => $case->value, 'label' => $case->label(), 'total' => (float) $matching->sum('amount'), 'count' => $matching->count()];
            })
            // Personnel always shows its three roles; bills and general only categories that were used.
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

    /** Column the totals tiles group (and filter) by. */
    private static function groupField(ExpenseKind $kind): string
    {
        return match ($kind) {
            ExpenseKind::Personnel => 'employee_role',
            ExpenseKind::Supplier => 'supplier_id',
            default => 'category',
        };
    }

    /**
     * What the purchase form needs: suppliers with the items they sell, every inventory item with its
     * purchase unit (kg / L / un) and last price, and the lines to start with.
     */
    public static function purchaseFormData(Expense $purchase): array
    {
        $suppliers = Supplier::query()->with('items:id')->orderBy('name')->get();
        $inventory = InventoryItem::query()->orderBy('name')->get();

        $lines = old('items', $purchase->exists
            ? $purchase->items->map(fn ($line): array => [
                'inventory_item_id' => $line->inventory_item_id,
                'quantity' => (string) round($line->purchaseQuantity(), 3),
                'total' => (string) $line->total,
            ])->all()
            : []);

        return [
            'suppliers' => $suppliers,
            'purchaseState' => [
                'supplierId' => (string) old('supplier_id', $purchase->supplier_id),
                'lines' => array_values($lines),
                'suppliers' => $suppliers->mapWithKeys(fn (Supplier $s): array => [$s->id => $s->items->pluck('id')->all()]),
                'items' => $inventory->mapWithKeys(fn (InventoryItem $i): array => [$i->id => [
                    'name' => $i->name,
                    'unit' => $i->unit_of_measure->costDisplayUnit(),
                    'stockUnit' => $i->unit_of_measure->value,
                    'factor' => $i->unit_of_measure->costDisplayFactor(),
                    'price' => $i->costPerDisplayUnit(),
                    'stock' => (float) $i->current_stock,
                ]]),
                'errors' => session('errors')?->getBag('default')->getMessages() ?? [],
            ],
        ];
    }

    private function formData(Expense $expense): array
    {
        if ($expense->kind === ExpenseKind::Supplier) {
            return ['expense' => $expense, 'tagSuggestions' => self::tagSuggestions()] + self::purchaseFormData($expense);
        }

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
            'tagSuggestions' => self::tagSuggestions(),
        ];
    }

    /** Most used first, so the quick-pick chips show the tags that matter. */
    public static function tagSuggestions(): array
    {
        return Tag::query()->withCount('expenses')->orderByDesc('expenses_count')->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (Tag $t): array => ['name' => $t->name, 'color' => $t->color])
            ->all();
    }
}
