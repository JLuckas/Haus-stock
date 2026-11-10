/**
 * Decimal field for Brazilian users (used by <x-decimal-input>).
 *
 * Browser <input type="number"> was unreliable here: its step validation rejected valid
 * quantities (step 1 + min 0.001 refused "2"), its arrows moved grams by 0.001, and comma
 * decimals depended on the browser. This field is plain text:
 *
 *   - accepts "1,5", "1.5", "1.500" (thousands), "1.234,5"
 *   - shows a comma ("45,9"), sends a dot to the server ("45.9") through a hidden input
 *   - optional −/+ buttons with a sensible step
 *
 * `value` (dot format, '' when empty) is x-modelable, so parent Alpine state keeps plain numbers.
 */

export function parseDecimal(text) {
    let s = String(text ?? '').trim().replace(/\s/g, '');
    if (s === '') return null;

    if (s.includes(',') && s.includes('.')) {
        s = s.replace(/\./g, '').replace(',', '.');          // 1.234,5
    } else if (s.includes(',')) {
        s = s.replace(',', '.');                              // 1,5
    } else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) {
        s = s.replace(/\./g, '');                             // 1.500 = mil e quinhentos
    }

    if (!/^-?\d*\.?\d+$/.test(s) && !/^-?\d+\.$/.test(s)) return null;

    const n = Number(s);
    return Number.isFinite(n) ? n : null;
}

const round = (n, decimals) => Math.round(n * 10 ** decimals) / 10 ** decimals;

export function decimalInput({ value = '', step = null, min = null, decimals = 3, fixed = 0 } = {}) {
    const toValue = (n) => (n === null ? '' : String(round(n, decimals)));
    const toDisplay = (v) => {
        const n = parseDecimal(v);
        return n === null ? '' : n.toLocaleString('pt-BR', { minimumFractionDigits: fixed, maximumFractionDigits: decimals, useGrouping: false });
    };

    return {
        value: toValue(parseDecimal(value)),
        display: '',

        init() {
            this.display = toDisplay(this.value);
            // Parent changed the model (x-modelable): reflect it unless it's what the user is typing.
            this.$watch('value', (v) => {
                if (toValue(parseDecimal(this.display)) !== v) this.display = toDisplay(v);
            });
        },

        typed() {
            this.value = toValue(parseDecimal(this.display));
        },

        tidy() {
            if (this.value !== '') this.display = toDisplay(this.value);
        },

        bump(direction) {
            let next = round((parseDecimal(this.display) ?? 0) + direction * step, decimals);
            if (min !== null) next = Math.max(min, next);
            this.value = toValue(next);
            this.display = toDisplay(this.value);
        },

        get invalid() {
            return this.display.trim() !== '' && parseDecimal(this.display) === null;
        },
    };
}
