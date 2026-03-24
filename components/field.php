<?php

function ui_input(string $name, string $label, $value = '', string $type = 'text', array $attrs = []): string
{
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</span>
        <input type="' . e($type) . '" name="' . e($name) . '" value="' . e((string) $value) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>
    </label>';
}

function ui_select(string $name, string $label, array $options, $selected = null, array $attrs = []): string
{
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</span>
        <select name="' . e($name) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>
            ' . option_html($options, $selected) . '
        </select>
    </label>';
}

function ui_textarea(string $name, string $label, $value = '', array $attrs = []): string
{
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</span>
        <textarea name="' . e($name) . '" class="min-h-[96px] w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>' . e((string) $value) . '</textarea>
    </label>';
}
