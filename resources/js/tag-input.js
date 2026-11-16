/**
 * Alpine state for <x-tag-input>: the chosen tag names, with existing tags offered as coloured
 * one-click chips and as autocomplete. Typing a new name and pressing Enter creates it on save.
 *
 * suggestions: [{ name, color }], most used first.
 */
const clean = (name) => String(name ?? '').replace(/^#+/, '').replace(/\s+/g, ' ').trim().slice(0, 40);
const fold = (s) => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase('pt-BR');
const same = (a, b) => fold(a) === fold(b);

// Full class names so Tailwind keeps them (same palette as resources/views/components/tag.blade.php).
export const TAG_COLORS = {
    blue: 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/30',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
    violet: 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/30',
    pink: 'bg-pink-50 text-pink-700 ring-pink-600/20 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-400/30',
    teal: 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-400/30',
    amber: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/10 dark:text-cyan-300 dark:ring-cyan-400/30',
    gray: 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-400/20',
};

export function tagInput({ selected = [], suggestions = [], quickPick = 8 } = {}) {
    const known = suggestions.map((s) => (typeof s === 'string' ? { name: s, color: 'gray' } : s));

    return {
        tags: [...selected],
        known,
        query: '',
        open: false,
        active: 0,
        quickPick,

        colorOf(name) {
            return TAG_COLORS[this.known.find((s) => same(s.name, name))?.color] ?? TAG_COLORS.indigo;
        },

        isNew(name) {
            return !this.known.some((s) => same(s.name, name));
        },

        has(name) {
            return this.tags.some((t) => same(t, name));
        },

        /** Most-used tags, shown as chips to toggle with one click. */
        get chips() {
            return this.known.slice(0, this.quickPick);
        },

        get options() {
            const q = clean(this.query);
            const matches = this.known
                .filter((s) => !this.has(s.name))
                .filter((s) => q === '' || fold(s.name).includes(fold(q)))
                .slice(0, 8)
                .map((s) => s.name);
            return q !== '' && this.isNew(q) && !this.has(q) ? [q, ...matches] : matches;
        },

        add(name) {
            const tag = clean(name);
            if (tag !== '' && !this.has(tag)) {
                // Reuse the existing spelling when it matches an existing tag.
                this.tags.push(this.known.find((s) => same(s.name, tag))?.name ?? tag);
            }
            this.query = '';
            this.active = 0;
        },

        toggle(name) {
            const index = this.tags.findIndex((t) => same(t, name));
            index === -1 ? this.add(name) : this.remove(index);
        },

        pick() {
            const option = this.options[this.active];
            this.add(option ?? this.query);
        },

        move(step) {
            const count = this.options.length;
            if (count) this.active = (this.active + step + count) % count;
        },

        remove(index) {
            this.tags.splice(index, 1);
        },

        removeLast() {
            this.tags.pop();
        },
    };
}
