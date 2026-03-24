<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();

$units = fetch_all('SELECT * FROM units ORDER BY nama_unit');

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

$rows = '';
$modals = '';
$createModalId = 'unit-create';
foreach ($units as $item) {
    $viewModalId = 'unit-view-' . $item['id'];
    $editModalId = 'unit-edit-' . $item['id'];
    $deleteModalId = 'unit-delete-' . $item['id'];
    $logo = !empty($item['logo_unit']) ? '<img src="' . e($item['logo_unit']) . '" alt="' . e($item['nama_unit']) . '" class="h-12 w-12 rounded-2xl object-cover">' : '<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">' . e(substr($item['nama_unit'], 0, 2)) . '</div>';

    $rows .= '<tr>
        <td class="px-4 py-3">' . $logo . '</td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['nama_unit']) . '</td>
        <td class="px-4 py-3">' . e($item['alamat_unit'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['no_hp_unit'] ?: '-') . '</td>
        <td class="px-4 py-3"><div class="flex flex-wrap gap-2">'
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

echo '<div class="space-y-6">';
echo ui_panel('Data Unit', '<div class="mb-4 flex justify-end">'
    . ui_button('Tambah Unit', ['icon' => 'plus', 'variant' => 'primary', 'attrs' => ['data-open-modal' => $createModalId]])
    . '</div>'
    . ui_table(
        [['label' => 'Logo', 'sortable' => false], 'Nama Unit', 'Alamat', 'Nomor HP', ['label' => 'Aksi', 'sortable' => false]],
        $rows !== '' ? $rows : '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada data unit.</td></tr>',
        ['storage_key' => 'units-list', 'search_column' => 1]
    ),
    ['subtitle' => 'Mirror halaman unit lama untuk kelola nama, alamat, nomor HP, dan logo unit.']
);
echo '</div>';
echo $modals;
