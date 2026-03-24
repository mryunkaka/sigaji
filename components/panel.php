<?php

function ui_panel(string $title, string $body, array $options = []): string
{
    $subtitle = $options['subtitle'] ?? '';

    return '
    <section class="rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)] backdrop-blur-sm">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">' . e($title) . '</h2>
                ' . ($subtitle !== '' ? '<p class="mt-1 text-sm text-slate-500">' . e($subtitle) . '</p>' : '') . '
            </div>
        </div>
        ' . $body . '
    </section>';
}
