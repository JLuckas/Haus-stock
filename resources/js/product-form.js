import { parseDecimal } from './decimal-input';

/**
 * Alpine state for the product form and its live "Cost and price" panel.
 *
 * As ingredients and products are added the panel adds up the cost of ONE unit:
 *   ingredient line = quantity (g / ml / un) × the item's cost per g / ml / un
 *   product line    = quantity × that product's own cost (its whole recipe, all levels)
 * and recommends the price that reaches the target margin: cost ÷ (1 − margin).
 *
 * Costs come from the server (Inventory costs and saved product recipes); nothing is saved
 * until the form is submitted.
 */

const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const round2 = (n) => Math.round(n * 100) / 100;

export function money(value) {
    return value === null || value === undefined || !Number.isFinite(value) ? '—' : brl.format(value).replace(/ /g, ' ');
}

export function percent(value) {
    return value === null || value === undefined || !Number.isFinite(value)
        ? '—'
        : `${value.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
}

/** Next price ending in ,90 at or above the value (28,57 → 28,90; 28,95 → 29,90). */
export function charmPrice(value) {
    if (value === null || value <= 0) return null;
    return round2(Math.ceil(round2(value - 0.9)) + 0.9);
}

export function productForm({
    mode = 'recipe',
    ingredients = [],
    components = [],
    standaloneId = '',
    units = {},
    errors = {},
    itemCosts = {},
    itemNames = {},
    productCosts = {},
    productNames = {},
    price = '',
    targetMargin = '',
    defaultMargin = 65,
} = {}) {
    return {
        mode,
        ingredients: ingredients.map((row, i) => ({ ...row, _key: i })),
        components: components.map((row, i) => ({ ...row, _key: 500 + i })),
        standaloneId: standaloneId === null ? '' : String(standaloneId),
        nextKey: 1000,
        units,
        errors,
        itemCosts,
        itemNames,
        productCosts,
        productNames,
        price: price === null ? '' : String(price),
        targetMargin: targetMargin === null ? '' : String(targetMargin),
        defaultMargin: Number(defaultMargin),

        money,
        percent,

        add(list, row) {
            this[list].push({ ...row, _key: this.nextKey++ });
        },

        remove(list, index) {
            this[list].splice(index, 1);
        },

        error(list, index, ...fields) {
            return fields.map((f) => (this.errors[`${list}.${index}.${f}`] || [])[0]).filter(Boolean).join(' ');
        },

        /** One row per filled line of the recipe being edited, with its cost (null = unknown). */
        get lines() {
            if (this.mode === 'standalone') {
                if (!this.standaloneId) return [];
                const unitCost = this.itemCosts[this.standaloneId] ?? null;
                return [{ key: 's', label: `1 un ${this.itemNames[this.standaloneId] ?? ''}`, cost: unitCost, note: null }];
            }

            const rows = [];

            for (const line of this.ingredients) {
                const id = String(line.inventory_item_id || '');
                if (!id) continue;
                const qty = parseDecimal(line.quantity_needed) ?? 0;
                const unitCost = this.itemCosts[id] ?? null;
                rows.push({
                    key: `i${line._key}`,
                    label: `${window.formatQuantity ? window.formatQuantity(qty, this.units[id] || '') : qty} ${this.itemNames[id] ?? ''}`,
                    cost: unitCost === null ? null : qty * unitCost,
                    note: null,
                    missing: unitCost === null ? [this.itemNames[id]] : [],
                });
            }

            for (const line of this.components) {
                const id = String(line.product_id || '');
                if (!id) continue;
                const qty = parseDecimal(line.quantity) ?? 0;
                const info = this.productCosts[id] ?? { cost: null, missing: [], unmapped: true };
                rows.push({
                    key: `c${line._key}`,
                    label: `${qty.toLocaleString('pt-BR')} × ${this.productNames[id] ?? ''}`,
                    cost: info.unmapped ? null : qty * (info.cost ?? 0),
                    note: info.unmapped ? 'unmapped' : (info.missing.length ? 'partial' : null),
                    missing: info.missing,
                });
            }

            return rows;
        },

        get hasLines() {
            return this.lines.length > 0;
        },

        get totalCost() {
            return this.lines.reduce((sum, row) => sum + (row.cost ?? 0), 0);
        },

        /** Ingredients without a cost anywhere in the recipe: the real cost is higher. */
        get missingCosts() {
            const names = new Set();
            for (const row of this.lines) {
                (row.missing ?? []).forEach((n) => n && names.add(n));
                if (row.cost === null && row.note !== 'unmapped' && !(row.missing ?? []).length) names.add(row.label);
            }
            return [...names];
        },

        get hasUnmapped() {
            return this.lines.some((row) => row.note === 'unmapped');
        },

        get priceValue() {
            return parseDecimal(this.price) ?? 0;
        },

        get profit() {
            return this.priceValue - this.totalCost;
        },

        get marginPercent() {
            return this.priceValue > 0 ? (this.profit / this.priceValue) * 100 : null;
        },

        get foodCostPercent() {
            return this.priceValue > 0 ? (this.totalCost / this.priceValue) * 100 : null;
        },

        /** The product's own target, or the store's when left empty. */
        get target() {
            const own = parseDecimal(this.targetMargin);
            return own === null ? this.defaultMargin : own;
        },

        get usesDefaultTarget() {
            return parseDecimal(this.targetMargin) === null;
        },

        get recommendedPrice() {
            if (this.totalCost <= 0 || this.target >= 100 || this.target < 0) return null;
            return round2(this.totalCost / (1 - this.target / 100));
        },

        get roundedPrice() {
            return charmPrice(this.recommendedPrice);
        },

        /** 'empty' | 'no-price' | 'loss' | 'below' | 'ok' */
        get status() {
            if (!this.hasLines || this.totalCost <= 0) return 'empty';
            if (this.priceValue <= 0) return 'no-price';
            if (this.profit < 0) return 'loss';
            return this.marginPercent < this.target ? 'below' : 'ok';
        },

        /** Width of the margin bar (0–100) and where the target marker sits. */
        get marginBar() {
            return Math.max(0, Math.min(100, this.marginPercent ?? 0));
        },

        get targetMarker() {
            return Math.max(0, Math.min(100, this.target));
        },

        usePrice(value) {
            if (value !== null) this.price = String(round2(value));
        },
    };
}
