import { parseDecimal } from './decimal-input';
import { money } from './product-form';

/**
 * Alpine state for "New order" (resources/views/orders/create.blade.php).
 *
 * Each line can be customized the way the customer asked:
 *  - ingredients: keep, remove, or swap for another of the same category (Cheddar → Mussarela),
 *    plus extras (+ 30 g Bacon);
 *  - products inside a box: keep, remove, or swap for another of the same category
 *    (Coca → Guaraná), plus more products (+ 1 Cheeseburger).
 * The stock preview applies the same rules as App\Services\Stock\BillOfMaterials::customized():
 * box changes first, then ingredient changes on the whole recipe.
 */
let key = 0;

const add = (target, source, factor) => {
    for (const [id, qty] of Object.entries(source ?? {})) target[id] = (target[id] ?? 0) + qty * factor;
};

export function orderForm({ products = {}, items = {}, plans = {}, categories = {}, lines = [], errors = {} } = {}) {
    const fromOld = (line) => {
        const restored = {
            _key: key++, product_id: String(line.product_id ?? ''), quantity: String(line.quantity ?? '1'),
            unit_price: line.unit_price ?? '', open: false,
            ingredientChoice: {}, componentChoice: {}, extras: [], added: [],
        };
        for (const c of line.customizations ?? []) {
            if (c.type === 'swap_ingredient') restored.ingredientChoice[c.from] = String(c.to);
            if (c.type === 'remove_ingredient') restored.ingredientChoice[c.item] = 'remove';
            if (c.type === 'add_ingredient') restored.extras.push({ _key: key++, item: String(c.item ?? ''), quantity: String(c.quantity ?? '') });
            if (c.type === 'swap_product') restored.componentChoice[c.from] = String(c.to);
            if (c.type === 'remove_product') restored.componentChoice[c.product] = 'remove';
            if (c.type === 'add_product') restored.added.push({ _key: key++, product: String(c.product ?? ''), quantity: String(c.quantity ?? '1') });
        }
        restored.open = (line.customizations ?? []).length > 0;
        return restored;
    };

    return {
        products,
        items,
        plans,
        categories,
        errors,
        lines: lines.map(fromOld),
        money,

        addLine() {
            this.lines.push(fromOld({ product_id: '', quantity: '1' }));
        },

        removeLine(index) {
            this.lines.splice(index, 1);
            if (!this.lines.length) this.addLine();
        },

        /** Choosing another product starts its changes from scratch. */
        productChanged(line) {
            Object.assign(line, { ingredientChoice: {}, componentChoice: {}, extras: [], added: [], unit_price: '' });
        },

        qty(line) {
            return parseDecimal(line.quantity) ?? 0;
        },

        unitPrice(line) {
            const typed = parseDecimal(line.unit_price);
            return typed ?? this.products[line.product_id]?.price ?? 0;
        },

        subtotal(line) {
            return this.unitPrice(line) * this.qty(line);
        },

        get total() {
            return this.lines.reduce((sum, line) => sum + this.subtotal(line), 0);
        },

        plan(line) {
            return this.plans[line.product_id] ?? null;
        },

        /** Products directly inside (a box's burger, fries, drink) with how many of each. */
        components(line) {
            return Object.entries(this.plan(line)?.components ?? {}).map(([id, qty]) => ({ id, qty, name: this.products[id]?.name ?? '?' }));
        },

        /** One unit's ingredients after the box changes (swapped / removed / added products). */
        productStage(line) {
            const plan = this.plan(line);
            if (!plan?.items) return {};
            const stage = { ...plan.items };
            for (const [id, qty] of Object.entries(plan.components ?? {})) {
                const choice = line.componentChoice[id];
                if (!choice) continue;
                add(stage, this.plans[id]?.items, -qty);
                if (choice !== 'remove') add(stage, this.plans[choice]?.items, qty);
            }
            for (const extra of line.added) {
                const n = parseDecimal(extra.quantity) ?? 0;
                if (extra.product && n > 0) add(stage, this.plans[extra.product]?.items, n);
            }
            for (const id of Object.keys(stage)) if (Math.abs(stage[id]) < 1e-9) delete stage[id];
            return stage;
        },

        /** The ingredients shown for keep / remove / swap, by name. */
        ingredients(line) {
            return Object.entries(this.productStage(line))
                .map(([id, qty]) => ({ id, qty, name: this.items[id]?.name ?? '?', unit: this.items[id]?.unit ?? '' }))
                .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
        },

        /** One unit's final consumption: box changes, then ingredient choices and extras. */
        finalPlan(line) {
            const stage = this.productStage(line);
            for (const [id, choice] of Object.entries(line.ingredientChoice)) {
                if (!choice || stage[id] === undefined) continue;
                if (choice !== 'remove') stage[choice] = (stage[choice] ?? 0) + stage[id];
                delete stage[id];
            }
            for (const extra of line.extras) {
                const n = parseDecimal(extra.quantity) ?? 0;
                if (extra.item && n > 0) stage[extra.item] = (stage[extra.item] ?? 0) + n;
            }
            return stage;
        },

        categoryName(id) {
            return this.categories[id]?.name ?? '';
        },

        /** Ingredients that can replace this one: same category (same unit when it has none). */
        swapItems(itemId) {
            const from = this.items[itemId];
            if (!from) return [];
            return Object.entries(this.items)
                .filter(([id, item]) => id !== String(itemId) && (from.category ? item.category === from.category : item.unit === from.unit))
                .map(([id, item]) => ({ id, name: item.name }))
                .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
        },

        /** Products that can replace one inside a box: same category (any, when it has none). */
        swapProducts(productId, parentId) {
            const from = this.products[productId];
            if (!from) return [];
            return Object.entries(this.products)
                .filter(([id, p]) => id !== String(productId) && id !== String(parentId) && this.plans[id]?.items && (!from.category || p.category === from.category))
                .map(([id, p]) => ({ id, name: p.name }))
                .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
        },

        /** Products that can be added to a line: anything with a valid recipe but the line's own product. */
        addableProducts(line) {
            return Object.entries(this.products)
                .filter(([id]) => id !== String(line.product_id) && this.plans[id]?.items)
                .map(([id, p]) => ({ id, name: p.name }));
        },

        addExtra(line) {
            line.extras.push({ _key: key++, item: '', quantity: '' });
        },

        addProduct(line) {
            line.added.push({ _key: key++, product: '', quantity: '1' });
        },

        /** The changes sent with the order (shape read by App\Services\Stock\OrderCustomizer). */
        changes(line) {
            const out = [];
            for (const [from, choice] of Object.entries(line.componentChoice)) {
                if (choice === 'remove') out.push({ type: 'remove_product', product: from });
                else if (choice) out.push({ type: 'swap_product', from, to: choice });
            }
            for (const extra of line.added) if (extra.product) out.push({ type: 'add_product', product: extra.product, quantity: extra.quantity || '1' });
            const stage = this.productStage(line);
            for (const [from, choice] of Object.entries(line.ingredientChoice)) {
                if (!choice || stage[from] === undefined) continue;
                if (choice === 'remove') out.push({ type: 'remove_ingredient', item: from });
                else out.push({ type: 'swap_ingredient', from, to: choice });
            }
            for (const extra of line.extras) if (extra.item) out.push({ type: 'add_ingredient', item: extra.item, quantity: extra.quantity });
            return out;
        },

        /** Short labels for the line's summary: "Cheddar → Mussarela", "Sem Cebola", "+ 30 g Bacon"… */
        describe(line) {
            const name = (dict, id) => dict[id]?.name ?? '?';
            const fq = (q, u) => (window.formatQuantity ? window.formatQuantity(q, u) : `${q} ${u}`);
            return this.changes(line).map((c) => {
                switch (c.type) {
                    case 'swap_ingredient': return `${name(this.items, c.from)} → ${name(this.items, c.to)}`;
                    case 'remove_ingredient': return `− ${name(this.items, c.item)}`;
                    case 'add_ingredient': return `+ ${fq(parseDecimal(c.quantity) ?? 0, this.items[c.item]?.unit)} ${name(this.items, c.item)}`;
                    case 'swap_product': return `${name(this.products, c.from)} → ${name(this.products, c.to)}`;
                    case 'remove_product': return `− ${name(this.products, c.product)}`;
                    case 'add_product': return `+ ${c.quantity} × ${name(this.products, c.product)}`;
                    default: return '';
                }
            });
        },

        /** Everything the order will take from stock. */
        get preview() {
            const totals = {};
            for (const line of this.lines) {
                add(totals, this.finalPlan(line), this.qty(line));
            }
            return Object.entries(totals)
                .filter(([, qty]) => qty > 1e-9)
                .map(([id, qty]) => ({ id, qty, name: this.items[id]?.name ?? '?', unit: this.items[id]?.unit ?? '' }))
                .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
        },

        problem(index, line) {
            return [
                (this.errors[`items.${index}.product_id`] || [])[0],
                (this.errors[`items.${index}.quantity`] || [])[0],
                ...(this.errors[`items.${index}.customizations`] || []),
                this.plan(line)?.error,
            ].filter(Boolean).join(' ');
        },
    };
}
