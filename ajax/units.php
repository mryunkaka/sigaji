<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();

$pageSize = 25;
$currentPage = max(1, (int) request_value('page', 1));
$search = trim((string) request_value('search', ''));
$searchSql = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = ' WHERE un.nama_unit LIKE :search ';
    $searchParams['search'] = '%' . $search . '%';
}
$totalUnits = (int) (fetch_one('SELECT COUNT(*) AS total FROM units un' . $searchSql, $searchParams)['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalUnits / $pageSize));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $pageSize;

$units = fetch_all(
    'SELECT un.*,
            (SELECT COUNT(*) FROM users usr WHERE usr.unit_id = un.id) AS total_users
     FROM units un' . $searchSql . '
     ORDER BY un.nama_unit
     LIMIT ' . $pageSize . ' OFFSET ' . $offset,
    $searchParams
);

$renderUnitForm = static function (array $item, string $modalId, bool $isCreate = false): string {
    return '<form action="ajax/save_unit.php" method="post" enctype="multipart/form-data" data-ajax-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e((string) ($item['id'] ?? '')) . '">'
        . ui_input('nama_unit', 'Nama Unit', $item['nama_unit'] ?? '', 'text', ['required' => 'required'])
        . ui_input('no_hp_unit', 'Nomor HP Unit', $item['no_hp_unit'] ?? '', 'text')
        . ui_input('logo_unit', 'Logo Unit', '', 'file', ['accept' => 'image/*'])
        . '<div class="md:col-span-2">' . ui_textarea('alamat_unit', 'Alamat Unit', $item['alamat_unit'] ?? '') . '</div>'
        . '<div class="md:col-span-2 flex justify-end">' . ui_button($isCreate ? 'Tambah Unit' : 'Simpan Unit', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
};

$tableId = 'units-table';
$bulkDeleteFormId = 'units-bulk-delete';
$rows = '';
$modals = '';
$createModalId = 'unit-create';
foreach ($units as $item) {
    $viewModalId = 'unit-view-' . $item['id'];
    $editModalId = 'unit-edit-' . $item['id'];
    $deleteModalId = 'unit-delete-' . $item['id'];
    $logo = !empty($item['logo_unit']) ? '<img src="' . e($item['logo_unit']) . '" alt="' . e($item['nama_unit']) . '" class="h-12 w-12 rounded-2xl object-cover">' : '<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">' . e(substr($item['nama_unit'], 0, 2)) . '</div>';
    $isActiveUnit = (int) $item['id'] === (int) $authUser['unit_id'];
    $hasUsers = (int) ($item['total_users'] ?? 0) > 0;
    $selectTooltip = $isActiveUnit
        ? 'Unit aktif tidak bisa dihapus'
        : ($hasUsers ? 'Unit yang masih memiliki user tidak bisa dihapus' : '');
    $selectCell = ($isActiveUnit || $hasUsers)
        ? '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 opacity-40" disabled' . ($selectTooltip !== '' ? ' title="' . e($selectTooltip) . '"' : '') . '>'
        : '<input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select>';

    $rows .= '<tr>
        <td class="px-3 py-3 text-center">' . $selectCell . '</td>
        <td class="px-4 py-3">' . $logo . '</td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['nama_unit']) . '</td>
        <td class="px-4 py-3">' . e($item['alamat_unit'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['no_hp_unit'] ?: '-') . '</td>
        <td class="px-4 py-3"><div class="flex flex-nowrap items-center gap-2">'
        . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]])
        . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $editModalId]])
        . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]])
        . '</div></td>
    </tr>';

    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Informasi Unit', [
            'Nama Unit' => e($item['nama_unit']),
            'Alamat Unit' => nl2br(e($item['alamat_unit'] ?: '-')),
            'Nomor HP Unit' => e($item['no_hp_unit'] ?: '-'),
        ], 2)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_unit.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . e((string) $item['id']) . '">'
        . '<input type="hidden" name="modal_id" value="' . e($deleteModalId) . '">'
        . '<p class="text-sm text-slate-600">Hapus unit <strong>' . e($item['nama_unit']) . '</strong>? Unit yang masih memiliki user tidak dapat dihapus.</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $modals .= ui_modal($viewModalId, 'Detail Unit', $viewBody, ['max_width' => 'max-w-4xl']);
    $modals .= ui_modal($editModalId, 'Edit Unit', $renderUnitForm($item, $editModalId), ['max_width' => 'max-w-4xl']);
    $modals .= ui_modal($deleteModalId, 'Hapus Unit', $deleteBody, ['max_width' => 'max-w-xl']);
}

$modals .= ui_modal($createModalId, 'Tambah Unit', $renderUnitForm([], $createModalId, true), ['max_width' => 'max-w-4xl']);

$bulkDeleteForm = '<form id="' . e($bulkDeleteFormId) . '" action="ajax/delete_unit_bulk.php" method="post" data-ajax-form class="hidden">'
    . csrf_input()
    . '<input type="hidden" name="ids" value="">'
    . '</form>';

echo '<div class="space-y-6">';
echo ui_panel('Data Unit', '<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">'
    . '<div class="flex flex-wrap gap-3">'
    . ui_button('Tambah Unit', ['icon' => 'plus', 'variant' => 'primary', 'attrs' => ['data-open-modal' => $createModalId]])
    . ui_button('Hapus Permanen', [
        'icon' => 'trash',
        'variant' => 'danger',
        'attrs' => [
            'data-bulk-delete' => '1',
            'data-table-target' => $tableId,
            'data-form-target' => $bulkDeleteFormId,
            'data-bulk-item-label' => 'unit',
            'data-bulk-empty-message' => 'Pilih unit yang ingin dihapus.',
            'data-bulk-confirm-message' => 'Hapus permanen {count} unit terpilih?',
        ],
    ])
    . '</div>'
    . '</div>'
    . ui_table(
        [['label' => '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select-all>', 'sortable' => false, 'raw' => true], ['label' => 'Logo', 'sortable' => false], 'Nama Unit', 'Alamat', 'Nomor HP', ['label' => 'Aksi', 'sortable' => false]],
        $rows !== '' ? $rows : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada data unit.</td></tr>',
        [
            'storage_key' => 'units-list',
            'search_column' => 1,
            'table_id' => $tableId,
            'server_pagination' => [
                'section' => 'units',
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalUnits,
                'page_param' => 'page',
                'params' => ['search' => $search],
                'search' => $search,
            ],
        ]
    ) . $bulkDeleteForm,
    ['subtitle' => 'Mirror halaman unit lama untuk kelola nama, alamat, nomor HP, dan logo unit.']
);
echo '</div>';
echo $modals;
