<?php

function ui_table(array $headers, string $bodyHtml): string
{
    $thead = '';
    foreach ($headers as $header) {
        $thead .= '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">' . e($header) . '</th>';
    }

    return '
    <div class="overflow-hidden rounded-[30px] border border-slate-200/80 bg-white/95 shadow-[0_18px_48px_rgba(15,23,42,.06)]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/85 backdrop-blur"><tr>' . $thead . '</tr></thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">' . $bodyHtml . '</tbody>
            </table>
        </div>
    </div>';
}
