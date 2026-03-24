<?php

function ui_badge(string $label, string $tone = 'slate'): string
{
    $tones = [
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700',
        'sky' => 'bg-sky-100 text-sky-700',
        'slate' => 'bg-slate-100 text-slate-700',
    ];

    return '<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ' . ($tones[$tone] ?? $tones['slate']) . '">' . e($label) . '</span>';
}
