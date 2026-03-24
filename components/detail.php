<?php

function ui_detail_grid(array $items, int $columns = 2): string
{
    $gridClass = match ($columns) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    };

    $html = '<div class="grid gap-3 ' . $gridClass . '">';
    foreach ($items as $label => $value) {
        $display = $value === null || $value === '' ? '-' : $value;
        $html .= '<div class="rounded-[24px] border border-slate-200 bg-slate-50/80 p-4 shadow-[0_10px_30px_rgba(15,23,42,.04)]">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">' . e((string) $label) . '</p>
            <div class="mt-2 text-sm font-medium text-slate-900">' . $display . '</div>
        </div>';
    }
    $html .= '</div>';

    return $html;
}

function ui_detail_section(string $title, array $items, int $columns = 2): string
{
    return '<section class="space-y-3">
        <div>
            <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">' . e($title) . '</h4>
        </div>
        ' . ui_detail_grid($items, $columns) . '
    </section>';
}
