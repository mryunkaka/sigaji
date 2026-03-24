<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();

$users = fetch_all(
    'SELECT *
     FROM users
     WHERE unit_id = :unit_id
     ORDER BY name',
    ['unit_id' => $authUser['unit_id']]
);

$renderUserForm = static function (array $item, string $modalId, int $unitId, bool $isCreate = false): string {
    $roleOptions = ['owner' => 'Owner', 'karyawan' => 'Karyawan'];
    $jenisKelaminOptions = ['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan'];
    $agamaOptions = ['Islam' => 'Islam', 'Kristen' => 'Kristen', 'Katolik' => 'Katolik', 'Hindu' => 'Hindu', 'Buddha' => 'Buddha', 'Konghucu' => 'Konghucu'];
    $statusOptions = ['Belum Menikah' => 'Belum Menikah', 'Menikah' => 'Menikah', 'Cerai' => 'Cerai'];

    return '<form action="ajax/save_user.php" method="post" enctype="multipart/form-data" data-ajax-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e((string) ($item['id'] ?? '')) . '">'
        . '<input type="hidden" name="unit_id" value="' . e((string) $unitId) . '">'
        . ui_input('name', 'Nama Lengkap', $item['name'] ?? '', 'text', ['required' => 'required'])
        . ui_input('email', 'Email', $item['email'] ?? '', 'email', ['required' => 'required'])
        . ui_input('no_hp', 'Nomor HP', $item['no_hp'] ?? '', 'text')
        . ui_input('jabatan', 'Jabatan', $item['jabatan'] ?? '', 'text')
        . ui_input('tempat_lahir', 'Tempat Lahir', $item['tempat_lahir'] ?? '', 'text')
        . ui_input('tanggal_lahir', 'Tanggal Lahir', $item['tanggal_lahir'] ?? '', 'date')
        . ui_select('jenis_kelamin', 'Jenis Kelamin', $jenisKelaminOptions, $item['jenis_kelamin'] ?? '')
        . ui_select('agama', 'Agama', $agamaOptions, $item['agama'] ?? '')
        . ui_select('status_perkawinan', 'Status Perkawinan', $statusOptions, $item['status_perkawinan'] ?? '')
        . ui_input('nik', 'NIK', $item['nik'] ?? '', 'text')
        . ui_input('npwp', 'NPWP', $item['npwp'] ?? '', 'text')
        . ui_select('role', 'Role', $roleOptions, $item['role'] ?? 'karyawan', ['required' => 'required'])
        . ui_input('tanggal_bergabung', 'Tanggal Bergabung', $item['tanggal_bergabung'] ?? '', 'date')
        . ui_input('foto', 'Foto', '', 'file', ['accept' => 'image/*'])
        . ui_input('password', $isCreate ? 'Password' : 'Password Baru', '', 'password', $isCreate ? ['required' => 'required'] : [])
        . '<div class="md:col-span-2">' . ui_textarea('alamat', 'Alamat', $item['alamat'] ?? '') . '</div>'
        . '<div class="md:col-span-2 flex justify-end">' . ui_button($isCreate ? 'Tambah User' : 'Simpan User', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
};

$rows = '';
$modals = '';
$createModalId = 'user-create';

foreach ($users as $item) {
    $viewModalId = 'user-view-' . $item['id'];
    $editModalId = 'user-edit-' . $item['id'];
    $deleteModalId = 'user-delete-' . $item['id'];
    $foto = !empty($item['foto']) ? '<img src="' . e($item['foto']) . '" alt="' . e($item['name']) . '" class="h-12 w-12 rounded-2xl object-cover">' : '<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-500">' . e(strtoupper(substr($item['name'], 0, 1))) . '</div>';

    $rows .= '<tr>
        <td class="px-4 py-3">' . $foto . '</td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['email']) . '</td>
        <td class="px-4 py-3">' . e($item['no_hp'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['jabatan'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e(ucfirst($item['role'])) . '</td>
        <td class="px-4 py-3">' . e($item['tanggal_bergabung'] ? format_date_id($item['tanggal_bergabung']) : '-') . '</td>
        <td class="px-4 py-3"><div class="flex flex-wrap gap-2">'
            . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]])
            . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $editModalId]])
            . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]])
            . '</div></td>
    </tr>';

    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Informasi Dasar', [
            'Nama Lengkap' => e($item['name']),
            'Email' => e($item['email']),
            'No. HP' => e($item['no_hp'] ?: '-'),
            'Jabatan' => e($item['jabatan'] ?: '-'),
            'Role' => e(ucfirst($item['role'])),
            'Tanggal Bergabung' => e($item['tanggal_bergabung'] ? format_date_id($item['tanggal_bergabung']) : '-'),
        ], 3)
        . ui_detail_section('Identitas', [
            'Tempat Lahir' => e($item['tempat_lahir'] ?: '-'),
            'Tanggal Lahir' => e($item['tanggal_lahir'] ? format_date_id($item['tanggal_lahir']) : '-'),
            'Jenis Kelamin' => e($item['jenis_kelamin'] ?: '-'),
            'Agama' => e($item['agama'] ?: '-'),
            'Status Perkawinan' => e($item['status_perkawinan'] ?: '-'),
            'NIK' => e($item['nik'] ?: '-'),
            'NPWP' => e($item['npwp'] ?: '-'),
        ], 3)
        . ui_detail_section('Alamat', ['Alamat' => nl2br(e($item['alamat'] ?: '-'))], 1)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_user.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . e((string) $item['id']) . '">'
        . '<input type="hidden" name="modal_id" value="' . e($deleteModalId) . '">'
        . '<p class="text-sm text-slate-600">Hapus user <strong>' . e($item['name']) . '</strong> secara permanen? Data terkait absensi, payroll, dan master gaji akan ikut terhapus bila ada.</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $modals .= ui_modal($viewModalId, 'Detail User', $viewBody, ['max_width' => 'max-w-5xl']);
    $modals .= ui_modal($editModalId, 'Edit User', $renderUserForm($item, $editModalId, (int) $authUser['unit_id']));
    $modals .= ui_modal($deleteModalId, 'Hapus User', $deleteBody, ['max_width' => 'max-w-xl']);
}

$modals .= ui_modal($createModalId, 'Tambah User', $renderUserForm([], $createModalId, (int) $authUser['unit_id'], true), ['max_width' => 'max-w-5xl']);

echo '<div class="space-y-6">';
echo ui_panel('Data User', '<div class="mb-4 flex justify-end">'
    . ui_button('Tambah User', ['icon' => 'plus', 'variant' => 'primary', 'attrs' => ['data-open-modal' => $createModalId]])
    . '</div>'
    . ui_table(
        [['label' => 'Foto', 'sortable' => false], 'Nama', 'Email', 'No. HP', 'Jabatan', 'Role', 'Tanggal Bergabung', ['label' => 'Aksi', 'sortable' => false]],
        $rows !== '' ? $rows : '<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Belum ada user pada unit aktif.</td></tr>',
        ['storage_key' => 'users-list', 'search_column' => 1]
    ),
    ['subtitle' => 'Mirror halaman user lama dengan scope per unit aktif.']
);
echo '</div>';
echo $modals;
