<?php

function ui_table(array $headers, string $bodyHtml, array $options = []): string
{
    $rowsPerPage = $options['rows_per_page'] ?? [10, 20, 50, 100, 'all'];
    $searchColumn = (int) ($options['search_column'] ?? 0);
    $numericColumns = $options['numeric_columns'] ?? [];
    $storageKey = $options['storage_key'] ?? null;
    $tableId = $options['table_id'] ?? ('table-' . bin2hex(random_bytes(4)));
    $serverPagination = $options['server_pagination'] ?? null;
    $serverSearchValue = '';
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

        $extraThClass = $index === 0 ? ' w-10 px-3' : ' px-4';
        $thead .= '<th class="whitespace-nowrap py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500' . $extraThClass . '">' . $headerContent . '</th>';
    }

    $footerCells = '';
    foreach ($headers as $index => $header) {
        $extraTdClass = $index === 0 ? ' w-10 px-3' : ' px-4';
        $footerCells .= '<td class="whitespace-nowrap py-3 text-sm font-semibold text-slate-600' . $extraTdClass . '" data-footer-cell="' . $index . '">'
            . ($index === 0 ? 'Total tampil: 0 data' : '&nbsp;') . '</td>';
    }

    $rowsPerPageOptions = '';
    $limitId = $tableId . '-limit';
    $searchId = $tableId . '-search';
    foreach ($rowsPerPage as $value) {
        $label = $value === 'all' ? 'All' : (string) $value;
        $rowsPerPageOptions .= '<option value="' . e((string) $value) . '">' . e($label) . '</option>';
    }

    $topControls = '';
    if ($serverPagination === null) {
        $topControls .= '<div class="flex items-center gap-3">
                <label for="' . e($limitId) . '" class="text-sm font-medium text-slate-600">Tampilkan</label>
                <select id="' . e($limitId) . '" name="' . e($tableId . '_limit') . '" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-100" data-table-limit>
                    ' . $rowsPerPageOptions . '
                </select>
            </div>';
    } else {
        $topControls .= '<div></div>';
    }

    $paginationMeta = '<p class="whitespace-nowrap text-sm text-slate-500" data-table-meta>Menampilkan 0 dari 0 data</p>';
    $paginationControls = '<div class="flex items-center gap-2">
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50" data-table-prev>' . ui_icon('chevron-left', 'h-5 w-5') . '</button>
                <div class="min-w-[110px] whitespace-nowrap text-center text-sm font-medium text-slate-600" data-table-page>Halaman 1 / 1</div>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50" data-table-next>' . ui_icon('chevron-right', 'h-5 w-5') . '</button>
            </div>';

    if ($serverPagination !== null) {
        $section = (string) ($serverPagination['section'] ?? '');
        $currentPage = max(1, (int) ($serverPagination['current_page'] ?? 1));
        $totalPages = max(1, (int) ($serverPagination['total_pages'] ?? 1));
        $totalItems = max(0, (int) ($serverPagination['total_items'] ?? 0));
        $params = is_array($serverPagination['params'] ?? null) ? $serverPagination['params'] : [];
        $serverSearchValue = (string) ($serverPagination['search'] ?? '');
        $pageParam = (string) ($serverPagination['page_param'] ?? 'page');
        $prevParams = $params;
        $nextParams = $params;
        $prevParams[$pageParam] = max(1, $currentPage - 1);
        $nextParams[$pageParam] = min($totalPages, $currentPage + 1);

        $paginationMeta = '<p class="whitespace-nowrap text-sm text-slate-500">Menampilkan halaman ' . e((string) $currentPage) . ' dari ' . e((string) $totalPages) . ' • total ' . e((string) $totalItems) . ' data</p>';
        $paginationControls = '<div class="flex items-center gap-2">'
            . '<button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"' . ($currentPage <= 1 ? ' disabled' : ' data-load-section="' . e($section) . '" data-section-params="' . e(json_encode($prevParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"') . '>Sebelumnya</button>'
            . '<div class="min-w-[120px] whitespace-nowrap text-center text-sm font-medium text-slate-600">Halaman ' . e((string) $currentPage) . ' / ' . e((string) $totalPages) . '</div>'
            . '<button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"' . ($currentPage >= $totalPages ? ' disabled' : ' data-load-section="' . e($section) . '" data-section-params="' . e(json_encode($nextParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"') . '>Berikutnya</button>'
            . '</div>';
    }

    return '
    <div id="' . e($tableId) . '" class="data-table relative max-w-full overflow-hidden rounded-[30px] border border-slate-200/80 bg-white/95 shadow-[0_18px_48px_rgba(15,23,42,.06)]"
        data-search-column="' . $searchColumn . '"
        data-numeric-columns="' . e(json_encode(array_values($numericColumns))) . '"
        data-storage-key="' . e((string) $storageKey) . '"'
        . ($serverPagination !== null ? ' data-server-section="' . e($section) . '" data-server-params="' . e(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"' : '')
        . '>
        <div class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 px-4 text-sm font-medium text-slate-500 backdrop-blur-sm" data-table-loader>
            Menyiapkan tabel...
        </div>
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            ' . $topControls . '
            <div class="relative block w-full sm:max-w-xs">
                <label for="' . e($searchId) . '" class="sr-only">Cari nama</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">' . ui_icon('magnifying-glass', 'h-4 w-4') . '</span>
                <input id="' . e($searchId) . '" name="' . e($tableId . '_search') . '" type="search" value="' . e($serverSearchValue) . '" class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-100" placeholder="Cari nama..." data-table-search>
            </div>
        </div>
        <div class="max-w-full overflow-x-auto overflow-y-hidden">
            <table class="min-w-full table-fixed divide-y divide-slate-200 [&_tbody_td]:max-w-[180px] [&_tbody_td]:overflow-hidden [&_tbody_td]:text-ellipsis [&_tbody_td]:whitespace-nowrap [&_tbody_td]:align-middle [&_thead_th]:whitespace-nowrap [&_tfoot_td]:whitespace-nowrap [&_tbody_td:first-child]:w-10 [&_tbody_td:first-child]:px-3 [&_tbody_td:first-child]:text-center [&_thead_th:first-child]:w-10 [&_thead_th:first-child]:px-3 [&_thead_th:first-child]:text-center [&_tfoot_td:first-child]:w-10 [&_tfoot_td:first-child]:px-3">
                <thead class="bg-slate-50/85 backdrop-blur"><tr>' . $thead . '</tr></thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">' . $bodyHtml . '</tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50/80"><tr>' . $footerCells . '</tr></tfoot>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            ' . $paginationMeta . '
            ' . $paginationControls . '
        </div>
    </div>';
}
