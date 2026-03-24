<?php

function ui_modal(string $id, string $title, string $body): string
{
    return '
    <div id="' . e($id) . '" class="modal-overlay fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/55 p-3 sm:p-4" data-modal>
        <div class="modal-card modal-scroll w-full max-w-3xl overflow-hidden rounded-[28px] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <h3 class="text-lg font-semibold text-slate-900">' . e($title) . '</h3>
                <button type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" data-close-modal="' . e($id) . '">x</button>
            </div>
            <div class="max-h-[calc(100vh-7rem)] overflow-y-auto px-5 py-5 sm:px-6">' . $body . '</div>
        </div>
    </div>';
}
