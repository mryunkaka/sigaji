<?php

function ui_input(string $name, string $label, $value = '', string $type = 'text', array $attrs = []): string
{
    static $inputCounter = 0;
    $inputCounter++;
    $id = $attrs['id'] ?? ('field-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '-' . $inputCounter);
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <div class="block">
        <label for="' . e($id) . '" class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</label>
        <input id="' . e($id) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e((string) $value) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>
    </div>';
}

function ui_select(string $name, string $label, array $options, $selected = null, array $attrs = []): string
{
    static $selectCounter = 0;
    $selectCounter++;
    $id = $attrs['id'] ?? ('field-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '-' . $selectCounter);
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <div class="block">
        <label for="' . e($id) . '" class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</label>
        <select id="' . e($id) . '" name="' . e($name) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>
            ' . option_html($options, $selected) . '
        </select>
    </div>';
}

function ui_textarea(string $name, string $label, $value = '', array $attrs = []): string
{
    static $textareaCounter = 0;
    $textareaCounter++;
    $id = $attrs['id'] ?? ('field-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '-' . $textareaCounter);
    $attrString = '';
    foreach ($attrs as $key => $val) {
        $attrString .= ' ' . $key . '="' . e((string) $val) . '"';
    }

    return '
    <div class="block">
        <label for="' . e($id) . '" class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</label>
        <textarea id="' . e($id) . '" name="' . e($name) . '" class="min-h-[96px] w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100"' . $attrString . '>' . e((string) $value) . '</textarea>
    </div>';
}
