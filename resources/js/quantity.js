/**
 * Mirror of App\Support\Quantity (PHP) for live previews; keep both in sync.
 *
 * Stock is stored in base units (g, ml, un). On screen the unit adapts so there are at most
 * 3 digits before the decimal comma: 850 g, 19,43 kg, 1,25 t, 4 L. Brazilian separators.
 */
const SCALES = {
    g: [[1_000_000, 't'], [1_000, 'kg'], [1, 'g']],
    ml: [[1_000, 'L'], [1, 'ml']],
};

const DECIMALS = 3;

const round = (value) => Math.round(value * 10 ** DECIMALS) / 10 ** DECIMALS;

const number = (value) => {
    const text = value.toLocaleString('pt-BR', { maximumFractionDigits: DECIMALS });

    return text === '-0' ? '0' : text;
};

const join = (text, unit) => (unit ? `${text} ${unit}` : text);

function scale(value, unit) {
    const rounded = round(value);

    for (const [factor, label] of SCALES[unit] ?? []) {
        const scaled = round(rounded / factor);

        if (Math.abs(scaled) >= 1 || factor === 1) {
            return [scaled, label];
        }
    }

    return [rounded, unit];
}

/** Adaptive display: formatQuantity(19430, 'g') === '19,43 kg'. Returns '' for non-numbers. */
export function formatQuantity(value, unit = null) {
    const parsed = Number(value);

    if (value === '' || value === null || !Number.isFinite(parsed)) {
        return '';
    }

    const [scaled, label] = scale(parsed, unit);

    return join(number(scaled), label);
}

/** True when formatQuantity() would show a different unit than the base one. */
export function isScaledQuantity(value, unit) {
    const parsed = Number(value);

    return value !== '' && Number.isFinite(parsed) && scale(parsed, unit)[1] !== unit;
}
