{{--
    Decimal field that works with Brazilian typing ("1,5", "1.500") — replaces <input type="number">.
    The visible input has no name; a hidden input sends the dot-format value ("1.5").

    Props:
      name           field name (static)
      bind-name      Alpine expression for a dynamic name, e.g. "`items[${index}][quantity]`"
      bind-disabled  Alpine expression; when true nothing is submitted (like a disabled input)
      value          initial value (ignored when x-model is used: the model wins)
      decimals       max decimals kept (3 = grams/ml precision, 2 = money)
      fixed          decimals always shown (2 for money: "32,00")
      step           shows −/+ buttons moving by this amount (e.g. 1 for units)
      min            lower bound for the −/+ buttons
    Other attributes (id, class, placeholder, aria-label, required…) go on the visible input;
    x-model goes on the wrapper so parent Alpine state gets the plain number.
--}}
@props(['name' => null, 'bindName' => null, 'bindDisabled' => null, 'value' => '', 'decimals' => 3, 'fixed' => 0, 'step' => null, 'min' => null])

@php
    $model = $attributes->get('x-model');
    $inputAttributes = $attributes->except(['x-model']);
    $config = ['value' => (string) $value, 'step' => $step !== null ? (float) $step : null, 'min' => $min !== null ? (float) $min : null, 'decimals' => (int) $decimals, 'fixed' => (int) $fixed];
    $button = 'inline-flex shrink-0 items-center justify-center border border-gray-300 bg-gray-50 px-3 text-lg leading-none text-gray-600 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300 dark:hover:bg-gray-700';
@endphp

<div class="contents" x-data="decimalInput(@js($config))" @if ($model) x-modelable="value" x-model="{{ $model }}" @endif>
    @if ($step !== null)
        <button type="button" tabindex="-1" class="{{ $button }} rounded-l-lg border-r-0" x-on:click="bump(-1)"
                @if ($bindDisabled) :disabled="{{ $bindDisabled }}" @endif aria-label="{{ __('Decrease') }}">−</button>
    @endif

    <input type="text" inputmode="decimal" autocomplete="off" spellcheck="false"
           x-model="display" x-on:input="typed()" x-on:blur="tidy()"
           :aria-invalid="invalid.toString()"
           :class="invalid && '!border-red-500 !ring-red-500'"
           @if ($bindDisabled) :disabled="{{ $bindDisabled }}" @endif
           {{ $inputAttributes->merge(['class' => 'field-control tabular-nums '.($step !== null ? 'rounded-none text-center' : '')]) }}>

    @if ($step !== null)
        <button type="button" tabindex="-1" class="{{ $button }} rounded-r-lg border-l-0" x-on:click="bump(1)"
                @if ($bindDisabled) :disabled="{{ $bindDisabled }}" @endif aria-label="{{ __('Increase') }}">+</button>
    @endif

    <input type="hidden" :value="value"
           @if ($bindName) :name="{{ $bindName }}" @elseif ($name) name="{{ $name }}" @endif
           @if ($bindDisabled) :disabled="{{ $bindDisabled }}" @endif>
</div>
