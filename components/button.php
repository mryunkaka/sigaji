<?php

function ui_button(string $label, array $options = []): string
{
    $variant = $options['variant'] ?? 'primary';
    $type = $options['type'] ?? 'button';
    $icon = $options['icon'] ?? null;
    $attrs = $options['attrs'] ?? [];
    $extraClass = $options['class'] ?? '';

    $variants = [
        'primary' => 'bg-slate-900 text-white shadow-sm hover:bg-slate-700',
        'secondary' => 'bg-white/90 text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50',
        'success' => 'bg-emerald-500 text-white shadow-sm hover:bg-emerald-600',
        'danger' => 'bg-rose-500 text-white shadow-sm hover:bg-rose-600',
        'warning' => 'bg-blue-600 text-white shadow-sm hover:bg-blue-700',
    ];

    $attrString = '';
    foreach ($attrs as $key => $value) {
        $attrString .= ' ' . $key . '="' . e((string) $value) . '"';
    }

    $iconHtml = $icon ? '<span class="shrink-0">' . ui_icon($icon, 'h-4 w-4') . '</span>' : '';

    return '<button type="' . e($type) . '" class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition duration-200 ' . $variants[$variant] . ' ' . e($extraClass) . '"' . $attrString . '>' . $iconHtml . '<span>' . $label . '</span></button>';
}
