(() => {
  const STORAGE_KEY = 'sigaji.active.section';
  const PARAMS_KEY = 'sigaji.section.params';
  const TABLE_STATE_KEY = 'sigaji.table.state';
  const SIDEBAR_KEY = 'sigaji.sidebar.open';
  let currentSection = localStorage.getItem(STORAGE_KEY) || window.location.hash.replace('#', '') || 'dashboard';
  let sectionParams = {};
  let tableStates = {};

  try {
    sectionParams = JSON.parse(localStorage.getItem(PARAMS_KEY) || '{}') || {};
  } catch (error) {
    sectionParams = {};
  }

  try {
    tableStates = JSON.parse(localStorage.getItem(TABLE_STATE_KEY) || '{}') || {};
  } catch (error) {
    tableStates = {};
  }

  const anyModalOpen = () => document.querySelector('[data-modal].is-open') !== null;

  const openModalById = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
      return;
    }
    modal.classList.remove('hidden');
    modal.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
  };

  const closeModalById = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
      return;
    }
    modal.classList.add('hidden');
    modal.classList.remove('is-open');
    modal.querySelectorAll('[data-ajax-form]').forEach((form) => form.dataset.dirty = 'false');
    if (!anyModalOpen()) {
      document.body.classList.remove('overflow-hidden');
    }
  };

  const pageContent = document.getElementById('page-content');
  const pageTitle = document.getElementById('page-title');
  const toast = document.getElementById('toast');
  const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');

  if (!pageContent || !pageTitle || !toast) {
    return;
  }

  const getDefaultSidebarState = () => {
    const stored = localStorage.getItem(SIDEBAR_KEY);
    if (stored !== null) {
      return stored === 'true';
    }
    return window.innerWidth >= 1024;
  };

  const setSidebarState = (open) => {
    document.body.classList.toggle('sidebar-open', open);
    localStorage.setItem(SIDEBAR_KEY, open ? 'true' : 'false');
  };

  const toggleSidebarState = () => {
    setSidebarState(!document.body.classList.contains('sidebar-open'));
  };

  setSidebarState(getDefaultSidebarState());

  const showToast = (message, type = 'success') => {
    toast.className = 'mb-4 rounded-2xl border px-4 py-3 text-sm font-medium ' + (type === 'error'
      ? 'border-rose-200 bg-rose-50 text-rose-700'
      : 'border-emerald-200 bg-emerald-50 text-emerald-700');
    toast.textContent = message;
    toast.classList.remove('hidden');
    window.setTimeout(() => toast.classList.add('hidden'), 4000);
  };

  const setActiveNav = (section) => {
    document.querySelectorAll('.nav-link').forEach((item) => {
      item.classList.toggle('active', item.dataset.section === section);
    });
  };

  const persistSectionParams = () => {
    localStorage.setItem(PARAMS_KEY, JSON.stringify(sectionParams));
  };

  const persistTableStates = () => {
    localStorage.setItem(TABLE_STATE_KEY, JSON.stringify(tableStates));
  };

  const getSectionParams = (section) => sectionParams[section] || null;

  const parseNumericValue = (text) => {
    const normalized = text.replace(/\s+/g, ' ').trim();
    if (!/\d/.test(normalized)) {
      return null;
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
      return null;
    }
    const cleaned = normalized.replace(/[^0-9,-]/g, '').replace(/\./g, '').replace(',', '.');
    if (cleaned === '' || cleaned === '-' || cleaned === '--') {
      return null;
    }
    const value = Number(cleaned);
    return Number.isFinite(value) ? value : null;
  };

  const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value);

  const initDataTables = () => {
    document.querySelectorAll('.data-table').forEach((wrapper) => {
      if (wrapper.dataset.bound === 'true') {
        return;
      }
      wrapper.dataset.bound = 'true';

      const table = wrapper.querySelector('table');
      const tbody = table?.querySelector('tbody');
      if (!table || !tbody) {
        return;
      }

      const allRows = Array.from(tbody.querySelectorAll('tr')).filter((row) => row.children.length > 1);
      const searchInput = wrapper.querySelector('[data-table-search]');
      const limitSelect = wrapper.querySelector('[data-table-limit]');
      const prevButton = wrapper.querySelector('[data-table-prev]');
      const nextButton = wrapper.querySelector('[data-table-next]');
      const meta = wrapper.querySelector('[data-table-meta]');
      const pageLabel = wrapper.querySelector('[data-table-page]');
      const footerCells = Array.from(wrapper.querySelectorAll('[data-footer-cell]'));
      const selectAll = wrapper.querySelector('[data-table-select-all]');
      const searchColumn = Number(wrapper.dataset.searchColumn || 0);
      const numericColumns = JSON.parse(wrapper.dataset.numericColumns || '[]');
      const stateKey = wrapper.dataset.storageKey || `${currentSection}-table`;
      const savedState = tableStates[stateKey] || {};
      let currentPage = Number(savedState.currentPage || 1);
      let sortIndex = savedState.sortIndex ?? null;
      let sortDirection = savedState.sortDirection || 'asc';

      if (searchInput && typeof savedState.search === 'string') {
        searchInput.value = savedState.search;
      }
      if (limitSelect && savedState.limit) {
        limitSelect.value = String(savedState.limit);
      }

      const saveState = () => {
        tableStates[stateKey] = {
          currentPage,
          sortIndex,
          sortDirection,
          search: searchInput?.value || '',
          limit: limitSelect?.value || '10',
        };
        persistTableStates();
      };

      const syncSelectionState = () => {
        if (!selectAll) {
          return;
        }
        const visibleCheckboxes = Array.from(tbody.querySelectorAll('[data-table-select]'));
        const checkedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
        selectAll.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
      };

      const render = () => {
        const query = (searchInput?.value || '').toLowerCase().trim();
        let filteredRows = allRows.filter((row) => {
          if (!query) {
            return true;
          }
          const cell = row.children[searchColumn];
          return (cell?.textContent || '').toLowerCase().includes(query);
        });

        if (sortIndex !== null) {
          filteredRows.sort((a, b) => {
            const aCell = a.children[sortIndex];
            const bCell = b.children[sortIndex];
            const aText = (aCell?.dataset.sortValue || aCell?.textContent || '').trim();
            const bText = (bCell?.dataset.sortValue || bCell?.textContent || '').trim();
            const aNum = parseNumericValue(aText);
            const bNum = parseNumericValue(bText);

            let compare = 0;
            if (aNum !== null && bNum !== null) {
              compare = aNum - bNum;
            } else {
              compare = aText.localeCompare(bText, 'id', { numeric: true, sensitivity: 'base' });
            }

            return sortDirection === 'asc' ? compare : compare * -1;
          });
        }

        const limitValue = limitSelect?.value || '10';
        const pageSize = limitValue === 'all' ? filteredRows.length || 1 : Number(limitValue);
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);

        const start = limitValue === 'all' ? 0 : (currentPage - 1) * pageSize;
        const visibleRows = limitValue === 'all' ? filteredRows : filteredRows.slice(start, start + pageSize);

        tbody.innerHTML = '';
        if (visibleRows.length === 0) {
          const colCount = table.querySelectorAll('thead th').length;
          tbody.innerHTML = `<tr><td colspan="${colCount}" class="px-4 py-8 text-center text-slate-500">Data tidak ditemukan.</td></tr>`;
        } else {
          visibleRows.forEach((row) => tbody.appendChild(row));
        }

        if (meta) {
          meta.textContent = `Menampilkan ${visibleRows.length} dari ${filteredRows.length} data`;
        }
        if (pageLabel) {
          pageLabel.textContent = `Halaman ${currentPage} / ${totalPages}`;
        }
        if (prevButton) {
          prevButton.disabled = currentPage <= 1 || limitValue === 'all';
        }
        if (nextButton) {
          nextButton.disabled = currentPage >= totalPages || limitValue === 'all';
        }

        footerCells.forEach((cell, index) => {
          if (index === 0) {
            cell.textContent = `Total tampil: ${visibleRows.length} data`;
            return;
          }
          if (!numericColumns.includes(index)) {
            cell.innerHTML = '&nbsp;';
            return;
          }
          const total = visibleRows.reduce((sum, row) => {
            const value = parseNumericValue((row.children[index]?.textContent || '').trim());
            return sum + (value ?? 0);
          }, 0);
          cell.textContent = formatNumber(total);
        });

        syncSelectionState();
        saveState();
      };

      wrapper.querySelectorAll('[data-table-sort]').forEach((button) => {
        button.addEventListener('click', () => {
          const nextIndex = Number(button.dataset.tableSort);
          if (sortIndex === nextIndex) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
          } else {
            sortIndex = nextIndex;
            sortDirection = 'asc';
          }
          currentPage = 1;
          render();
        });
      });

      searchInput?.addEventListener('input', () => {
        currentPage = 1;
        render();
      });

      limitSelect?.addEventListener('change', () => {
        currentPage = 1;
        render();
      });

      prevButton?.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage -= 1;
          render();
        }
      });

      nextButton?.addEventListener('click', () => {
        const limitValue = limitSelect?.value || '10';
        if (limitValue === 'all') {
          return;
        }
        currentPage += 1;
        render();
      });

      selectAll?.addEventListener('change', () => {
        tbody.querySelectorAll('[data-table-select]').forEach((checkbox) => {
          checkbox.checked = selectAll.checked;
        });
        syncSelectionState();
      });

      tbody.addEventListener('change', (event) => {
        if (event.target.matches('[data-table-select]')) {
          syncSelectionState();
        }
      });

      render();
    });
  };

  const loadSection = async (section, params = null) => {
    currentSection = section;
    if (params !== null) {
      sectionParams[section] = params;
      persistSectionParams();
    }
    const activeParams = sectionParams[section] || {};
    const query = new URLSearchParams(activeParams).toString();
    localStorage.setItem(STORAGE_KEY, section);
    window.location.hash = section;
    pageTitle.textContent = section.charAt(0).toUpperCase() + section.slice(1);
    setActiveNav(section);
    pageContent.innerHTML = '<div class="soft-card p-8 text-sm text-slate-500">Memuat data...</div>';
    const response = await fetch(`ajax/${section}.php${query ? '?' + query : ''}`, { credentials: 'same-origin' });
    pageContent.innerHTML = await response.text();
    initDataTables();
  };

  const submitAjaxForm = async (form) => {
    const formData = new FormData(form);
    const response = await fetch(form.action, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
    });

    const raw = await response.text();
    let result;

    try {
      result = JSON.parse(raw);
    } catch (error) {
      showToast('Respons server tidak valid. Periksa error PHP pada endpoint.', 'error');
      console.error('Non-JSON response:', raw);
      return;
    }

    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
      if (result.closeModal) {
        closeModalById(result.closeModal);
      }
      if (result.reloadSection) {
        loadSection(result.reloadSection, getSectionParams(result.reloadSection));
      }
    }
  };

  document.addEventListener('click', (event) => {
    const passwordToggle = event.target.closest('[data-toggle-password]');
    if (passwordToggle) {
      const target = document.getElementById(passwordToggle.dataset.target);
      if (!target) {
        return;
      }

      const hidden = target.type === 'password';
      target.type = hidden ? 'text' : 'password';
      passwordToggle.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', hidden);
      passwordToggle.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', !hidden);
      passwordToggle.setAttribute('aria-label', hidden ? 'Sembunyikan password' : 'Tampilkan password');
      return;
    }

    const nav = event.target.closest('.nav-link');
    if (nav) {
      loadSection(nav.dataset.section);
      setSidebarState(false);
      return;
    }

    const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
      toggleSidebarState();
      return;
    }

    const sidebarClose = event.target.closest('[data-sidebar-close]');
    if (sidebarClose) {
      setSidebarState(false);
      return;
    }

    if (sidebarBackdrop && event.target === sidebarBackdrop) {
      setSidebarState(false);
      return;
    }

    const openModal = event.target.closest('[data-open-modal]');
    if (openModal) {
      openModalById(openModal.dataset.openModal);
      return;
    }

    const closeModal = event.target.closest('[data-close-modal]');
    if (closeModal) {
      closeModalById(closeModal.dataset.closeModal);
      return;
    }

    const backdrop = event.target.closest('[data-modal]');
    if (backdrop && event.target === backdrop) {
      closeModalById(backdrop.id);
      return;
    }

    const bulkDelete = event.target.closest('[data-bulk-delete]');
    if (!bulkDelete) {
      return;
    }

    const table = document.getElementById(bulkDelete.dataset.tableTarget || '');
    const form = document.getElementById(bulkDelete.dataset.formTarget || '');
    const input = form?.querySelector('[name="ids"]');

    if (!table || !form || !input) {
      showToast('Form hapus massal tidak ditemukan.', 'error');
      return;
    }

    const ids = Array.from(table.querySelectorAll('[data-table-select]:checked'))
      .map((checkbox) => checkbox.value)
      .filter(Boolean);

    if (ids.length === 0) {
      showToast('Pilih data absensi yang ingin dihapus.', 'error');
      return;
    }

    if (!window.confirm(`Hapus permanen ${ids.length} data absensi?`)) {
      return;
    }

    input.value = ids.join(',');
    form.dataset.dirty = 'false';
    submitAjaxForm(form);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-section-filter]')) {
      event.preventDefault();
      const section = form.dataset.section || currentSection;
      const params = Object.fromEntries(new FormData(form).entries());
      loadSection(section, params);
      return;
    }
    if (!form.matches('[data-ajax-form]')) {
      return;
    }
    event.preventDefault();
    form.dataset.dirty = 'false';
    submitAjaxForm(form);
  });

  document.addEventListener('input', (event) => {
    const form = event.target.closest('[data-ajax-form]');
    if (form) {
      form.dataset.dirty = 'true';
    }
  });

  document.addEventListener('change', (event) => {
    const trigger = event.target.closest('[data-absensi-calc]');
    if (!trigger) {
      return;
    }

    const form = trigger.closest('[data-absensi-form]');
    if (!form) {
      return;
    }

    const userSelect = form.querySelector('[name="user_id"]');
    const shift = form.querySelector('[name="shift"]')?.value || '';
    const jamMasuk = form.querySelector('[name="jam_masuk"]')?.value || '';
    const totalField = form.querySelector('[name="total_menit_terlambat"]');
    const jumlahField = form.querySelector('[name="jumlah_potongan"]');

    if (!userSelect || !totalField || !jumlahField) {
      return;
    }

    const selectedOption = userSelect.options[userSelect.selectedIndex];
    const jabatan = (selectedOption?.dataset.jabatan || '').toLowerCase();
    const potonganTerlambat = Number(selectedOption?.dataset.potonganTerlambat || 1000);

    let jamMasukSah = null;
    if (jabatan !== 'security' && shift === '1') jamMasukSah = '08:00';
    if (jabatan !== 'security' && shift === '2') jamMasukSah = '16:00';
    if (jabatan !== 'security' && shift === '3') jamMasukSah = '23:00';
    if (jabatan === 'security' && shift === '1') jamMasukSah = '07:00';
    if (jabatan === 'security' && shift === '2') jamMasukSah = '15:00';
    if (jabatan === 'security' && shift === '3') jamMasukSah = '00:00';

    let totalMenit = 0;
    if (jamMasuk && jamMasukSah) {
      const [actualHour, actualMinute] = jamMasuk.split(':').map(Number);
      const [targetHour, targetMinute] = jamMasukSah.split(':').map(Number);
      const actual = (actualHour * 60) + actualMinute;
      const target = (targetHour * 60) + targetMinute;
      totalMenit = Math.max(0, actual - target);
    }

    totalField.value = String(totalMenit);
    jumlahField.value = String(totalMenit * potonganTerlambat);
  });

  window.addEventListener('hashchange', () => {
    const nextSection = window.location.hash.replace('#', '');
    if (nextSection && nextSection !== currentSection && !anyModalOpen()) {
      loadSection(nextSection, sectionParams[nextSection] || null);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth < 1024) {
      setSidebarState(false);
      return;
    }

    if (localStorage.getItem(SIDEBAR_KEY) === null) {
      setSidebarState(true);
    }
  });

  loadSection(currentSection, sectionParams[currentSection] || null);
})();
