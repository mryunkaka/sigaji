<?php

function ui_stat(string $label, string $value, string $hint, string $tone = 'emerald'): string
{
    $bar = [
        'emerald' => 'from-emerald-400 to-emerald-500',
        'sky' => 'from-sky-400 to-sky-500',
        'amber' => 'from-amber-400 to-amber-500',
        'rose' => 'from-rose-400 to-rose-500',
    ];

    return '
    <div class="rounded-[30px] border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_50px_rgba(15,23,42,.05)]">
        <div class="mb-4 h-1.5 w-16 rounded-full bg-gradient-to-r ' . ($bar[$tone] ?? $bar['emerald']) . '"></div>
        <p class="text-sm text-slate-500">' . e($label) . '</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">' . e($value) . '</p>
        <p class="mt-2 text-sm text-slate-500">' . e($hint) . '</p>
    </div>';
}
