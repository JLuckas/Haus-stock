/**
 * Command palette (Ctrl/⌘ K, or "/" outside a text field): type to jump to any page or start
 * a common action. Items come from App\Support\Navigation::commands().
 *
 * Matching ignores case and accents ("baixa" finds "Dar baixa", "despesa" finds "Despesas").
 */
const fold = (text) => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export function commandPalette(commands = []) {
    return {
        open: false,
        query: '',
        active: 0,
        commands: commands.map((c) => ({ ...c, _search: fold(`${c.label} ${c.group}`) })),

        init() {
            window.addEventListener('keydown', (event) => {
                const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName) || event.target.isContentEditable;
                if ((event.key === 'k' || event.key === 'K') && (event.metaKey || event.ctrlKey)) {
                    event.preventDefault();
                    this.open ? this.close() : this.show();
                } else if (event.key === '/' && !typing && !this.open) {
                    event.preventDefault();
                    this.show();
                }
            });
        },

        show() {
            this.open = true;
            this.query = '';
            this.active = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            this.open = false;
        },

        get results() {
            const terms = fold(this.query).split(/\s+/).filter(Boolean);
            return terms.length ? this.commands.filter((c) => terms.every((t) => c._search.includes(t))) : this.commands;
        },

        /** Results split into their groups, keeping each item's index in the flat list for keyboard focus. */
        get grouped() {
            const groups = [];
            this.results.forEach((item, index) => {
                let group = groups.find((g) => g.label === item.group);
                if (!group) groups.push((group = { label: item.group, items: [] }));
                group.items.push({ ...item, index });
            });
            return groups;
        },

        move(step) {
            const count = this.results.length;
            if (!count) return;
            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.$root.querySelector(`[data-index="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }));
        },

        go(item = this.results[this.active]) {
            if (!item) return;
            this.close();
            window.location.href = item.href;
        },
    };
}
