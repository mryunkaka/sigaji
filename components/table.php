<?php

function ui_table(array $headers, string $bodyHtml, array $options = []): string
{
    $rowsPerPage = $options['rows_per_page'] ?? [10, 20, 50, 100, 'all'];
    $searchColumn = (int) ($options['search_column'] ?? 0);
    $numericColumns = $options['numeric_columns'] ?? [];
    $storageKey = $options['storage_key'] ?? null;
    $tableId = $options['table_id'] ?? ('table-' . bin2hex(random_bytes(4)));
    $thead = '';
    foreach ($headers as $index => $header) {
        $label = is_array($header) ? ($header['label'] ?? '') : $header;
        $sortable = is_array($header) ? (bool) ($header['sortable'] ?? true) : true;
        $raw = is_array($header) ? (bool) ($header['raw'] ?? false) : false;

        if ($sortable) {
            $headerContent = '<button type="button" class="inline-flex items-center gap-2 text-left transition hover:text-slate-900" data-table-sort="' . $index . '">
                <span>' . ($raw ? $label : e((string) $label)) . '</span>
                <span class="text-slate-400">' . ui_icon('arrows-up-down', 'h-4 w-4') . '</span>
            </button>';
        } else {
            $headerContent = $raw ? $label : e((string) $label);
        }

        $thead .= '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">' . $headerContent . '</th>';
    }

    $footerCells = '';
    foreach ($headers as $index => $header) {
        $footerCells .= '<td class="px-4 py-3 text-sm font-semibold text-slate-600" data-footer-cell="' . $index . '">'
            . ($index === 0 ? 'Total tampil: 0 data' : '&nbsp;') . '</td>';
    }

    $rowsPerPageOptions = '';
    foreach ($rowsPerPage as $value) {
        $label = $value === 'all' ? 'All' : (string) $value;
        $rowsPerPageOptions .= '<option value="' . e((string) $value) . '">' . e($label) . '</option>';
    }

    return '
    <div id="' . e($tableId) . '" class="data-table overflow-hidden rounded-[30px] border border-slate-200/80 bg-white/95 shadow-[0_18px_48px_rgba(15,23,42,.06)]"
        data-search-column="' . $searchColumn . '"
        data-numeric-columns="' . e(json_encode(array_values($numericColumns))) . '"
        data-storage-key="' . e((string) $storageKey) . '">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-600">Tampilkan</label>
                <select class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-100" data-table-limit>
                    ' . $rowsPerPageOptions . '
                </select>
            </div>
            <label class="relative block w-full sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">' . ui_icon('magnifying-glass', 'h-4 w-4') . '</span>
                <input type="search" class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-100" placeholder="Cari nama..." data-table-search>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/85 backdrop-blur"><tr>' . $thead . '</tr></thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">' . $bodyHtml . '</tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50/80"><tr>' . $footerCells . '</tr></tfoot>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500" data-table-meta>Menampilkan 0 dari 0 data</p>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50" data-table-prev>' . ui_icon('chevron-left', 'h-5 w-5') . '</button>
                <div class="min-w-[110px] text-center text-sm font-medium text-slate-600" data-table-page>Halaman 1 / 1</div>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50" data-table-next>' . ui_icon('chevron-right', 'h-5 w-5') . '</button>
            </div>
        </div>
    </div>';
}
