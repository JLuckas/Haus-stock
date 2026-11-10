<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A reporting period chosen with ?period=today|7d|30d|month|last_month|custom (&from=&to=).
 * Shared by the Resultado and Despesas pages and their <x-period-filter>.
 */
final readonly class Period
{
    public const PRESETS = ['today', '7d', '30d', 'month', 'last_month'];

    private const MAX_CUSTOM_DAYS = 366;

    public function __construct(
        public string $key,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /** Validates the query string (redirecting back with errors) and resolves the dates. */
    public static function fromRequest(Request $request, string $default = 'month'): self
    {
        $data = validator($request->query(), [
            'period' => ['nullable', Rule::in([...self::PRESETS, 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [], [
            'from' => __('start date'),
            'to' => __('end date'),
        ])->after(function (Validator $validator) use ($request): void {
            if ($request->query('period') === 'custom' && $validator->errors()->isEmpty()
                && CarbonImmutable::parse($request->query('from'))->diffInDays(CarbonImmutable::parse($request->query('to'))) >= self::MAX_CUSTOM_DAYS) {
                $validator->errors()->add('to', __('Choose a period of at most :days days.', ['days' => self::MAX_CUSTOM_DAYS]));
            }
        })->validate();

        $key = $data['period'] ?? $default;
        $today = CarbonImmutable::today();

        [$from, $to] = match ($key) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            '30d' => [$today->subDays(29), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => [CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['to'])],
        };

        return new self($key, $from->startOfDay(), $to->endOfDay());
    }

    /** @return array<string, string> preset key => label */
    public static function presetLabels(): array
    {
        return [
            'today' => __('Today'),
            '7d' => __('Last 7 days'),
            '30d' => __('Last 30 days'),
            'month' => __('This month'),
            'last_month' => __('Last month'),
        ];
    }

    public function label(): string
    {
        return $this->key === 'custom'
            ? $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y')
            : self::presetLabels()[$this->key];
    }

    /** Query parameters that reproduce this period in a link. */
    public function query(): array
    {
        return $this->key === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->key];
    }
}
