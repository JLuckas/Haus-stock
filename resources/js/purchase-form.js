import { parseDecimal } from './decimal-input';
import { money } from './product-form';

/**
 * Alpine state for a supplier purchase (Despesas → Fornecedores).
 *  - Choosing a supplier fills the lines with the items they usually sell (when nothing was typed yet).
 *  - Quantities are in the purchase unit (kg, L or un); a line's total starts as quantity × last price
 *    paid, until the person types their own total.
 *  - Shows the price per unit and the stock each item will have once the purchase is activated.
 */
export function purchaseForm({ supplierId = '', lines = [], suppliers = {}, items = {}, errors = {} } = {}) {
    let key = 0;
    const blank = (itemId = '') => ({ _key: key++, inventory_item_id: itemId === '' ? '' : String(itemId), quantity: '', total: '', touched: false });

    return {
        supplierId: supplierId === null ? '' : String(supplierId),
        lines: lines.length
            ? lines.map((l) => ({ ...blank(l.inventory_item_id), quantity: String(l.quantity ?? ''), total: String(l.total ?? ''), touched: (l.total ?? '') !== '' }))
            : [blank()],
        suppliers,
        items,
        errors,
        money,

        item(line) {
            return this.items[line.inventory_item_id] ?? null;
        },

        unit(line) {
            return this.item(line)?.unit ?? '—';
        },

        /** Every line still empty: safe to replace with the supplier's items. */
        get untouched() {
            return this.lines.every((l) => l.quantity === '' && l.total === '');
        },

        supplierChanged() {
            const known = this.suppliers[this.supplierId] ?? [];
            if (known.length && this.untouched) {
                this.lines = known.map((id) => blank(id));
            }
        },

        add() {
            this.lines.push(blank());
        },

        remove(index) {
            this.lines.splice(index, 1);
            if (!this.lines.length) this.lines.push(blank());
        },

        /** Keep the total at quantity × last price until the person types a total themselves. */
        autoTotal(line) {
            const qty = parseDecimal(line.quantity);
            const price = this.item(line)?.price;
            if (line.touched || qty === null || price === null || price === undefined) return;
            line.total = String(Math.round(qty * price * 100) / 100);
        },

        unitPrice(line) {
            const qty = parseDecimal(line.quantity);
            const total = parseDecimal(line.total);
            return qty && total !== null ? money(total / qty) + '/' + this.unit(line) : '';
        },

        stockAfter(line) {
            const item = this.item(line);
            const qty = parseDecimal(line.quantity);
            if (!item || !qty) return '';
            const after = item.stock + qty * item.factor;
            return window.formatQuantity ? window.formatQuantity(after, item.stockUnit) : String(after);
        },

        get total() {
            return this.lines.reduce((sum, l) => sum + (parseDecimal(l.total) ?? 0), 0);
        },

        error(index, ...fields) {
            return fields.map((f) => (this.errors[`items.${index}.${f}`] || [])[0]).filter(Boolean).join(' ');
        },
    };
}
