<?php

function ui_modal(string $id, string $title, string $body): string
{
    return '
    <div id="' . e($id) . '" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" data-modal>
        <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">' . e($title) . '</h3>
                <button type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" data-close-modal="' . e($id) . '">x</button>
            </div>
            <div class="max-h-[80vh] overflow-y-auto px-6 py-5">' . $body . '</div>
        </div>
    </div>';
}
