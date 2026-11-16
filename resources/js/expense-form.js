import { parseDecimal } from './decimal-input';
import { money } from './product-form';

/**
 * Alpine state for the expense form.
 *  - Typing a known employee's name fills in their last role and day rate (only into empty fields).
 *  - "Repeat": previews every date of a recurring expense and the total, using the same rules as
 *    App\Enums\RecurrenceFrequency (dates counted from the first; Jan 31 monthly → Feb 28 → Mar 31).
 */
const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const br = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
const daysIn = (year, month) => new Date(year, month + 1, 0).getDate();

function addMonthsNoOverflow(start, months) {
    const target = new Date(start.getFullYear(), start.getMonth() + months, 1);
    target.setDate(Math.min(start.getDate(), daysIn(target.getFullYear(), target.getMonth())));
    return target;
}

function addDays(start, days) {
    return new Date(start.getFullYear(), start.getMonth(), start.getDate() + days);
}

export function occurrenceDate(start, frequency, index) {
    switch (frequency) {
        case 'daily': return addDays(start, index);
        case 'weekly': return addDays(start, 7 * index);
        case 'biweekly': return addDays(start, 14 * index);
        case 'monthly': return addMonthsNoOverflow(start, index);
        case 'quarterly': return addMonthsNoOverflow(start, 3 * index);
        case 'yearly': return addMonthsNoOverflow(start, 12 * index);
        default: return start;
    }
}

// How many upcoming dates to preview for a series with no number of times (staff payments).
const OPEN_ENDED_PREVIEW = 4;

const FREQUENCY_LABELS = { daily: 'daily', weekly: 'weekly', biweekly: 'every 2 weeks', monthly: 'monthly', quarterly: 'every 3 months', yearly: 'yearly' };

export function expenseForm({ kind, role = '', amount = '', employees = {}, spentOn = '', repeat = 'none', occurrences = '', maxOccurrences = 366, withCount = true, frequencyLabels = FREQUENCY_LABELS } = {}) {
    return {
        kind,
        role,
        amount,
        employees,
        spentOn,
        repeat,
        occurrences: occurrences === '' ? '' : String(occurrences),
        maxOccurrences,
        withCount,
        frequencyLabels,

        employeeTyped(name) {
            const key = Object.keys(this.employees).find((n) => n.toLocaleLowerCase('pt-BR') === String(name).trim().toLocaleLowerCase('pt-BR'));
            if (!key) return;

            const known = this.employees[key];
            if (!this.role && known.role) this.role = known.role;
            if (!this.amount && known.amount) this.amount = known.amount;
        },

        get repeating() {
            return this.repeat !== 'none';
        },

        get count() {
            const n = Math.floor(parseDecimal(this.occurrences) ?? 0);
            return n >= 2 && n <= this.maxOccurrences ? n : null;
        },

        /** Every date of the series; with no number of times, just the first few (empty until it can be computed). */
        get dates() {
            const length = this.withCount ? this.count : OPEN_ENDED_PREVIEW;
            if (!this.repeating || !length || !/^\d{4}-\d{2}-\d{2}$/.test(this.spentOn)) return [];
            const [y, m, d] = this.spentOn.split('-').map(Number);
            const start = new Date(y, m - 1, d);
            return Array.from({ length }, (_, i) => occurrenceDate(start, this.repeat, i));
        },

        get frequencyLabel() {
            return this.frequencyLabels[this.repeat] ?? '';
        },

        /** Short list for the preview: first 3 … last. */
        get previewDates() {
            const all = this.dates.map(br);
            if (!this.withCount) return all.length ? [...all, '…'] : [];
            return all.length <= 5 ? all : [...all.slice(0, 3), '…', all[all.length - 1]];
        },

        get lastDate() {
            const all = this.dates;
            return all.length ? br(all[all.length - 1]) : '';
        },

        get seriesTotal() {
            const value = parseDecimal(this.amount);
            return value && this.count ? money(value * this.count) : '—';
        },

        get amountText() {
            const value = parseDecimal(this.amount);
            return value ? money(value) : 'R$ —';
        },

        iso,
    };
}
