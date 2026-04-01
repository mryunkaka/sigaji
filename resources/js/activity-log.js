import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import '../css/activity-log-datatables.css';

const initializeActivityLogTable = () => {
  const table = document.getElementById('activity-log-table');
  if (!table) {
    return;
  }

  new DataTable(table, {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'desc']],
    autoWidth: false,
    language: {
      search: 'Cari:',
      searchPlaceholder: 'Cari user / aksi / detail',
      lengthMenu: 'Tampilkan _MENU_ data',
      info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
      infoEmpty: 'Tidak ada data',
      zeroRecords: 'Data tidak ditemukan',
      emptyTable: 'Belum ada aktivitas pada rentang ini.',
      paginate: {
        first: 'Awal',
        last: 'Akhir',
        next: 'Berikutnya',
        previous: 'Sebelumnya',
      },
    },
    columnDefs: [
      { targets: [4], orderable: false },
    ],
  });
};

document.addEventListener('DOMContentLoaded', initializeActivityLogTable);
