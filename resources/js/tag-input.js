/** Alpine state for <x-tag-input>: a list of tag names with suggestions. */
const clean = (name) => String(name ?? '').replace(/^#+/, '').replace(/\s+/g, ' ').trim().slice(0, 40);
const same = (a, b) => a.toLocaleLowerCase('pt-BR') === b.toLocaleLowerCase('pt-BR');

export function tagInput({ selected = [], suggestions = [] } = {}) {
    return {
        tags: [...selected],
        suggestions,
        query: '',
        open: false,

        get options() {
            const q = clean(this.query);
            const matches = this.suggestions
                .filter((s) => !this.tags.some((t) => same(t, s)))
                .filter((s) => q === '' || s.toLocaleLowerCase('pt-BR').includes(q.toLocaleLowerCase('pt-BR')))
                .slice(0, 8);
            const exists = [...this.suggestions, ...this.tags].some((s) => same(s, q));
            return q !== '' && !exists ? [q, ...matches] : matches;
        },

        add(name) {
            const tag = clean(name);
            if (tag !== '' && !this.tags.some((t) => same(t, tag))) {
                // Reuse the existing spelling when it matches an existing tag.
                this.tags.push(this.suggestions.find((s) => same(s, tag)) ?? tag);
            }
            this.query = '';
        },

        remove(index) {
            this.tags.splice(index, 1);
        },

        removeLast() {
            this.tags.pop();
        },
    };
}
