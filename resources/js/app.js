import './bootstrap';

import Alpine from 'alpinejs';
import { formatQuantity, isScaledQuantity } from './quantity';
import { themeSwitcher } from './theme';
import { decimalInput, parseDecimal } from './decimal-input';
import { tagInput } from './tag-input';
import { expenseForm } from './expense-form';
import { productForm } from './product-form';
import { commandPalette } from './command-palette';

// Available in Alpine expressions, e.g. x-text="formatQuantity(qty, 'g')".
window.formatQuantity = formatQuantity;
window.isScaledQuantity = isScaledQuantity;
window.themeSwitcher = themeSwitcher;
window.decimalInput = decimalInput;
window.parseDecimal = parseDecimal;
window.tagInput = tagInput;
window.expenseForm = expenseForm;
window.productForm = productForm;
window.commandPalette = commandPalette;

window.Alpine = Alpine;

Alpine.start();
