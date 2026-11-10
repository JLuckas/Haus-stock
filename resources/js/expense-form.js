/**
 * Alpine state for the expense form. Typing a known employee's name fills in their last role and
 * day rate (only into empty fields, never over what the user already chose).
 */
export function expenseForm({ kind, role = '', amount = '', employees = {} } = {}) {
    return {
        kind,
        role,
        amount,
        employees,

        employeeTyped(name) {
            const key = Object.keys(this.employees).find((n) => n.toLocaleLowerCase('pt-BR') === String(name).trim().toLocaleLowerCase('pt-BR'));
            if (!key) return;

            const known = this.employees[key];
            if (!this.role && known.role) this.role = known.role;
            if (!this.amount && known.amount) this.amount = known.amount;
        },
    };
}
