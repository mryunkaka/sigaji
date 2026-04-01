(() => {
  const STORAGE_KEY = 'sigaji.active.section';
  const PARAMS_KEY = 'sigaji.section.params';
  const PARAMS_MIGRATION_KEY = 'sigaji.section.params.migration';
  const PARAMS_MIGRATION_VERSION = 'closing-period-26-25-v3';
  const TABLE_STATE_KEY = 'sigaji.table.state';
  const SIDEBAR_KEY = 'sigaji.sidebar.open';
  const CLOSING_PERIOD_SECTIONS = ['dashboard', 'absensi', 'gaji'];
  let currentSection = window.location.hash.replace('#', '') || localStorage.getItem(STORAGE_KEY) || 'dashboard';
  let sectionParams = {};
  let tableStates = {};
  let tableSelections = {};

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

  if (localStorage.getItem(PARAMS_MIGRATION_KEY) !== PARAMS_MIGRATION_VERSION) {
    CLOSING_PERIOD_SECTIONS.forEach((section) => {
      if (!sectionParams[section] || typeof sectionParams[section] !== 'object') {
        return;
      }

      delete sectionParams[section].start_date;
      delete sectionParams[section].end_date;

      if (Object.keys(sectionParams[section]).length === 0) {
        delete sectionParams[section];
      }
    });

    localStorage.setItem(PARAMS_KEY, JSON.stringify(sectionParams));
    localStorage.setItem(PARAMS_MIGRATION_KEY, PARAMS_MIGRATION_VERSION);
  }

  const anyModalOpen = () => document.querySelector('[data-modal].is-open') !== null;
  const getActiveModal = () => {
    const openModals = Array.from(document.querySelectorAll('[data-modal].is-open'));
    return openModals.at(-1) || null;
  };

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
  const appLoader = document.getElementById('app-loader');
  const appLoaderBar = document.getElementById('app-loader-bar');
  const appLoaderPercent = document.getElementById('app-loader-percent');
  const appLoaderStatus = document.getElementById('app-loader-status');
  const loaderSteps = {
    shell: document.getElementById('loader-step-shell'),
    assets: document.getElementById('loader-step-assets'),
    components: document.getElementById('loader-step-components'),
    data: document.getElementById('loader-step-data'),
    ready: document.getElementById('loader-step-ready'),
  };
  let appReady = false;
  let loaderProgress = 0;
  let bootLoaderDismissed = false;
  let sectionRequestController = null;

  if (!pageContent || !pageTitle || !toast) {
    window.addEventListener('load', () => {
      appLoader?.classList.add('hidden');
      document.body.classList.remove('app-loading');
    });
    return;
  }

  document.body.classList.add('app-loading');

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

  const showAppLoader = () => {
    if (!appLoader || bootLoaderDismissed) {
      return;
    }
    appLoader.style.display = 'flex';
    appLoader.classList.remove('hidden');
    document.body.classList.add('app-loading');
  };

  const hideAppLoader = () => {
    if (!appLoader) {
      return;
    }
    bootLoaderDismissed = true;
    appLoader.classList.add('hidden');
    window.setTimeout(() => {
      appLoader.style.display = 'none';
    }, 220);
    document.body.classList.remove('app-loading');
  };

  const finishAppLoader = () => {
    if (!appLoader || bootLoaderDismissed) {
      return;
    }
    setLoaderStep('ready', 'done');
    setLoaderProgress(100, 'Semua asset, komponen, dan data berhasil dimuat.');
    window.setTimeout(hideAppLoader, 180);
  };

  const dismissBootLoaderEarly = () => {
    if (bootLoaderDismissed) {
      return;
    }

    setLoaderStep('shell', 'done');
    setLoaderStep('assets', 'done');
    setLoaderStep('components', 'active');
    setLoaderProgress(Math.max(loaderProgress, 38), 'Antarmuka siap. Memuat data halaman...');
    window.setTimeout(() => {
      hideAppLoader();
    }, 120);
  };

  const setLoaderProgress = (value, status = null) => {
    loaderProgress = Math.max(loaderProgress, Math.min(100, value));
    if (appLoaderBar) {
      appLoaderBar.style.width = `${loaderProgress}%`;
    }
    if (appLoaderPercent) {
      appLoaderPercent.textContent = `${Math.round(loaderProgress)}%`;
    }
    if (status && appLoaderStatus) {
      appLoaderStatus.textContent = status;
    }
  };

  const setLoaderStep = (name, state = 'active') => {
    Object.entries(loaderSteps).forEach(([key, element]) => {
      if (!element) {
        return;
      }
      if (key === name) {
        element.classList.toggle('is-active', state === 'active');
        element.classList.toggle('is-done', state === 'done');
      } else if (state === 'active' && !element.classList.contains('is-done')) {
        element.classList.remove('is-active');
      }
    });
  };

  setLoaderStep('shell', 'active');
  setLoaderProgress(10, 'Menyiapkan shell aplikasi...');

  const showToast = (message, type = 'success') => {
    toast.className = 'mb-4 rounded-2xl border px-4 py-3 text-sm font-medium ' + (type === 'error'
      ? 'border-rose-200 bg-rose-50 text-rose-700'
      : 'border-emerald-200 bg-emerald-50 text-emerald-700');
    toast.textContent = message;
    toast.classList.remove('hidden');
    window.setTimeout(() => toast.classList.add('hidden'), 4000);
  };

  const ensureProgressOverlay = () => {
    let overlay = document.getElementById('upload-progress-overlay');
    if (overlay) {
      return overlay;
    }

    overlay = document.createElement('div');
    overlay.id = 'upload-progress-overlay';
    overlay.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/55 p-4';
    overlay.innerHTML = `
      <div class="w-full max-w-xl rounded-[28px] bg-white p-6 shadow-2xl">
        <div class="mb-5 flex items-center gap-3">
          <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-slate-200 border-t-sky-500"></span>
          <div>
            <p class="text-lg font-semibold text-slate-900" data-upload-progress-title>Import Absensi</p>
            <p class="text-sm text-slate-500" data-upload-progress-status>Menyiapkan upload file...</p>
          </div>
        </div>
        <div class="mb-3 h-3 overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-emerald-500 transition-[width] duration-200" data-upload-progress-bar style="width:0%"></div>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-500" data-upload-progress-note>Mohon tunggu, proses import sedang berjalan.</span>
          <span class="font-semibold text-slate-700" data-upload-progress-percent>0%</span>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    return overlay;
  };

  const updateProgressOverlay = ({ title, status, note, percent }) => {
    const overlay = ensureProgressOverlay();
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    document.body.classList.add('overflow-hidden');

    overlay.querySelector('[data-upload-progress-title]')?.replaceChildren(document.createTextNode(title));
    overlay.querySelector('[data-upload-progress-status]')?.replaceChildren(document.createTextNode(status));
    overlay.querySelector('[data-upload-progress-note]')?.replaceChildren(document.createTextNode(note));
    overlay.querySelector('[data-upload-progress-percent]')?.replaceChildren(document.createTextNode(`${Math.round(percent)}%`));
    const bar = overlay.querySelector('[data-upload-progress-bar]');
    if (bar) {
      bar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
    }
  };

  const hideProgressOverlay = () => {
    const overlay = document.getElementById('upload-progress-overlay');
    if (!overlay) {
      return;
    }
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    if (!anyModalOpen()) {
      document.body.classList.remove('overflow-hidden');
    }
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

  const isFullDocumentResponse = (content) => /<!doctype html|<html[\s>]/i.test(String(content || ''));

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
  const normalizeSearchText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();

  const recalculatePayrollForm = (form) => {
    if (!form) {
      return;
    }

    const readNumber = (name) => {
      const field = form.querySelector(`[name="${name}"]`);
      if (!field) {
        return 0;
      }
      const value = Number(field.value || 0);
      return Number.isFinite(value) ? value : 0;
    };

    const earningFields = [
      'gaji_pokok',
      'tunjangan_bbm',
      'tunjangan_makan',
      'tunjangan_jabatan',
      'tunjangan_kehadiran',
      'tunjangan_lainnya',
      'lembur',
    ];
    const deductionFields = [
      'potongan_kehadiran',
      'potongan_khusus',
      'potongan_ijin',
      'potongan_terlambat',
      'pot_bpjs_jht',
      'pot_bpjs_kes',
    ];

    const gajiKotor = earningFields.reduce((sum, name) => sum + readNumber(name), 0);
    const totalPotongan = deductionFields.reduce((sum, name) => sum + readNumber(name), 0);
    const gajiBersih = gajiKotor - totalPotongan;

    const updateOutput = (name, value) => {
      const field = form.querySelector(`[name="${name}"]`);
      if (field) {
        field.value = String(value);
      }
    };

    updateOutput('gaji_kotor', gajiKotor);
    updateOutput('total_potongan', totalPotongan);
    updateOutput('gaji_bersih', gajiBersih);
  };

  const normalizeAttendanceValue = (value) => String(value || '').trim().toLowerCase();

  const parseAttendanceTime = (value) => {
    const time = String(value || '').trim().slice(0, 5);
    if (!/^\d{2}:\d{2}$/.test(time)) {
      return null;
    }

    const [hour, minute] = time.split(':').map(Number);
    if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
      return null;
    }

    return { hour, minute };
  };

  const parseShiftRules = (value) => {
    try {
      const parsed = JSON.parse(String(value || '{}'));
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  };

  const calculateAttendanceLateMinutes = ({ status, jamMasuk, shift, jabatan, toleranceMinutes, scheduledJamMasuk }) => {
    if (normalizeAttendanceValue(status) !== 'hadir' || !jamMasuk || !shift) {
      return 0;
    }

    const parsedTime = parseAttendanceTime(jamMasuk);
    if (!parsedTime) {
      return 0;
    }

    const { hour, minute } = parsedTime;
    let arrival = (hour * 60) + minute;
    const role = normalizeAttendanceValue(jabatan);
    const tolerance = Math.max(0, Number(toleranceMinutes || 0));
    const scheduledTime = parseAttendanceTime(scheduledJamMasuk);

    if (scheduledTime) {
      let target = (scheduledTime.hour * 60) + scheduledTime.minute;
      if (target >= (18 * 60) && arrival < (12 * 60)) {
        arrival += 1440;
      }
      return Math.max(0, arrival - (target + tolerance));
    }

    if (role === 'security') {
      let target = null;
      if (shift === '1') target = 8 * 60;
      if (shift === '2') target = 16 * 60;
      if (shift === '3') target = (23 * 60) + 59;

      if (target === null) {
        return 0;
      }

      if (shift === '3') {
        if (hour < 23 || (hour === 23 && minute < 59)) {
          return 0;
        }

        if (hour < 12) {
          arrival += 1440;
        }
      }

      return Math.max(0, arrival - (target + tolerance));
    }

    if (role === 'general') {
      if (shift === '1') {
        return Math.max(0, arrival - ((7 * 60) + tolerance));
      }

      if (shift === '2') {
        if (arrival < (7 * 60)) {
          arrival += 1440;
        }

        return Math.max(0, arrival - ((19 * 60) + tolerance));
      }

      return 0;
    }

    let target = null;
    if (shift === '1') target = 7 * 60;
    if (shift === '2') target = 15 * 60;
    if (shift === '3') target = 23 * 60;

    if (target === null) {
      return 0;
    }

    if (shift === '3' && arrival < (7 * 60)) {
      arrival += 1440;
    }

    return Math.max(0, arrival - (target + tolerance));
  };

  const applyAttendanceDefaults = (form, selectedOption) => {
    if (!form || !selectedOption || !selectedOption.value) {
      return;
    }

    const shiftField = form.querySelector('[name="shift"]');
    const jamMasukField = form.querySelector('[name="jam_masuk"]');
    const jamKeluarField = form.querySelector('[name="jam_keluar"]');
    const defaultShift = String(selectedOption.dataset.defaultShift || '');
    const defaultJamMasuk = String(selectedOption.dataset.defaultJamMasuk || '');
    const defaultJamKeluar = String(selectedOption.dataset.defaultJamKeluar || '');
    const shiftRules = parseShiftRules(selectedOption.dataset.shiftRules || '{}');
    const selectedShift = String(shiftField?.value || defaultShift || '');
    const matchedRule = shiftRules[selectedShift] || null;

    if (shiftField && defaultShift && shiftField.value === '') {
      shiftField.value = defaultShift;
    }

    if (jamMasukField) {
      const nextJamMasuk = matchedRule?.jam_masuk || defaultJamMasuk;
      if (nextJamMasuk && (jamMasukField.value === '' || jamMasukField.dataset.autoFilled === 'true')) {
        jamMasukField.value = nextJamMasuk;
        jamMasukField.dataset.autoFilled = 'true';
      }
    }

    if (jamKeluarField) {
      const nextJamKeluar = matchedRule?.jam_keluar || defaultJamKeluar;
      if (nextJamKeluar && (jamKeluarField.value === '' || jamKeluarField.dataset.autoFilled === 'true')) {
        jamKeluarField.value = nextJamKeluar;
        jamKeluarField.dataset.autoFilled = 'true';
      }
    }
  };

  const setTableLoaderState = (wrapper, loading) => {
    const loader = wrapper.querySelector('[data-table-loader]');
    if (!loader) {
      return;
    }
    loader.classList.toggle('hidden', !loading);
    loader.setAttribute('aria-hidden', loading ? 'false' : 'true');
  };

  const initSingleDataTable = (wrapper) => {
    const table = wrapper.querySelector('table');
    const tbody = table?.querySelector('tbody');
    const serverSection = wrapper.dataset.serverSection || '';
    const serverParamsRaw = wrapper.dataset.serverParams || '{}';
    if (!table || !tbody) {
      wrapper.dataset.bound = 'true';
      setTableLoaderState(wrapper, false);
      return;
    }

    const allRows = Array.from(tbody.querySelectorAll('tr')).filter((row) => row.children.length > 1);
    const searchInput = wrapper.querySelector('[data-table-search]');
    const searchForm = wrapper.querySelector('[data-table-search-form]');
    const limitSelect = wrapper.querySelector('[data-table-limit]');
    const prevButton = wrapper.querySelector('[data-table-prev]');
    const nextButton = wrapper.querySelector('[data-table-next]');
    const meta = wrapper.querySelector('[data-table-meta]');
    const pageLabel = wrapper.querySelector('[data-table-page]');
    const selectAllCheckbox = wrapper.querySelector('[data-table-select-all]');
    const selectionBar = wrapper.querySelector('[data-table-selection-bar]');
    const selectionCount = wrapper.querySelector('[data-table-selection-count]');
    const selectAllResultsButton = wrapper.querySelector('[data-table-select-all-results]');
    const selectAllCount = wrapper.querySelector('[data-table-select-all-count]');
    const clearSelectionButton = wrapper.querySelector('[data-table-clear-selection]');
    const searchColumn = Number(wrapper.dataset.searchColumn || 0);
    const stateKey = wrapper.dataset.storageKey || `${currentSection}-table`;
    const savedState = tableStates[stateKey] || {};
    let currentPage = Number(savedState.currentPage || 1);
    let sortIndex = savedState.sortIndex ?? null;
    let sortDirection = savedState.sortDirection || 'asc';
    let serverParams = {};
    let currentFilteredCount = Number(wrapper.dataset.totalItems || allRows.length);

    try {
      serverParams = JSON.parse(serverParamsRaw) || {};
    } catch (error) {
      serverParams = {};
    }

    const selectionSignature = `${serverSection || 'local'}|${JSON.stringify(serverParams)}|${searchInput?.value || ''}`;
    const createSelectionState = () => ({
      signature: selectionSignature,
      allFiltered: false,
      includedIds: new Set(),
      excludedIds: new Set(),
    });

    let selectionState = tableSelections[stateKey];
    if (!selectionState || selectionState.signature !== selectionSignature || !(selectionState.includedIds instanceof Set) || !(selectionState.excludedIds instanceof Set)) {
      selectionState = createSelectionState();
      tableSelections[stateKey] = selectionState;
    }

    const getVisibleCheckboxes = () => Array.from(tbody.querySelectorAll('[data-table-select]')).filter((checkbox) => !checkbox.disabled);
    const getSelectedCount = () => {
      if (selectionState.allFiltered) {
        return Math.max(0, currentFilteredCount - selectionState.excludedIds.size);
      }
      return selectionState.includedIds.size;
    };

    const isSelected = (id) => {
      if (selectionState.allFiltered) {
        return !selectionState.excludedIds.has(id);
      }
      return selectionState.includedIds.has(id);
    };

    const syncVisibleSelection = () => {
      getVisibleCheckboxes().forEach((checkbox) => {
        checkbox.checked = isSelected(checkbox.value);
      });
    };

    const syncHeaderSelection = () => {
      if (!selectAllCheckbox) {
        return;
      }
      const visibleCheckboxes = getVisibleCheckboxes();
      const checkedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
      selectAllCheckbox.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
      selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    };

    const updateSelectionUI = () => {
      const selectedCount = getSelectedCount();
      const visibleCount = getVisibleCheckboxes().length;
      const hasMoreFilteredResults = currentFilteredCount > visibleCount;

      if (selectionBar) {
        selectionBar.classList.toggle('hidden', selectedCount === 0);
      }
      if (selectionCount) {
        selectionCount.textContent = `${formatNumber(selectedCount)} records selected`;
      }
      if (selectAllCount) {
        selectAllCount.textContent = formatNumber(currentFilteredCount);
      }
      if (selectAllResultsButton) {
        const shouldShowSelectAll = !selectionState.allFiltered && selectedCount > 0 && hasMoreFilteredResults;
        selectAllResultsButton.classList.toggle('hidden', !shouldShowSelectAll);
      }
      if (clearSelectionButton) {
        clearSelectionButton.classList.toggle('hidden', selectedCount === 0);
      }
    };

    const resetSelectionState = () => {
      selectionState = createSelectionState();
      tableSelections[stateKey] = selectionState;
      syncVisibleSelection();
      syncHeaderSelection();
      updateSelectionUI();
    };

    const applyCheckboxChange = (checkbox, checked) => {
      const id = checkbox.value;
      if (!id) {
        return;
      }

      if (selectionState.allFiltered) {
        if (checked) {
          selectionState.excludedIds.delete(id);
        } else {
          selectionState.excludedIds.add(id);
        }
      } else if (checked) {
        selectionState.includedIds.add(id);
      } else {
        selectionState.includedIds.delete(id);
      }

      syncHeaderSelection();
      updateSelectionUI();
    };

    const toggleVisibleSelection = (checked) => {
      getVisibleCheckboxes().forEach((checkbox) => {
        checkbox.checked = checked;
        applyCheckboxChange(checkbox, checked);
      });
    };

    const populateBulkDeleteForm = (form) => {
      const idsInput = form.querySelector('[name="ids"]');
      if (!idsInput) {
        return false;
      }

      const ensureInput = (name) => {
        let input = form.querySelector(`[name="${name}"]`);
        if (!input) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          form.appendChild(input);
        }
        return input;
      };

      const selectionMode = ensureInput('selection_mode');
      const selectionParams = ensureInput('selection_params');
      const excludedIds = ensureInput('excluded_ids');

      if (selectionState.allFiltered) {
        const activeSectionParams = getSectionParams(currentSection) || {};
        const params = {
          ...activeSectionParams,
          ...serverParams,
          search: searchInput?.value || serverParams.search || activeSectionParams.search || '',
        };
        idsInput.value = '';
        selectionMode.value = 'all_filtered';
        selectionParams.value = JSON.stringify(params);
        excludedIds.value = Array.from(selectionState.excludedIds).join(',');
      } else {
        idsInput.value = Array.from(selectionState.includedIds).join(',');
        selectionMode.value = 'ids';
        selectionParams.value = '';
        excludedIds.value = '';
      }

      return true;
    };

    wrapper.__tableSelection = {
      clear: resetSelectionState,
      getSelectedCount,
      populateBulkDeleteForm,
    };

    if (serverSection) {
      const triggerServerSearch = () => {
        const nextParams = { ...serverParams, search: searchInput?.value || '', page: 1, master_page: 1, payroll_page: 1 };
        loadSection(serverSection, nextParams);
      };

      searchForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        triggerServerSearch();
      });

      searchInput?.addEventListener('search', () => {
        if ((searchInput.value || '') === '') {
          triggerServerSearch();
        }
      });

      selectAllCheckbox?.addEventListener('change', () => {
        toggleVisibleSelection(selectAllCheckbox.checked);
      });

      selectAllResultsButton?.addEventListener('click', () => {
        selectionState.allFiltered = true;
        selectionState.includedIds.clear();
        selectionState.excludedIds.clear();
        syncVisibleSelection();
        syncHeaderSelection();
        updateSelectionUI();
      });

      clearSelectionButton?.addEventListener('click', () => {
        resetSelectionState();
      });

      tbody.addEventListener('change', (event) => {
        if (!event.target.matches('[data-table-select]')) {
          return;
        }
        applyCheckboxChange(event.target, event.target.checked);
      });

      syncVisibleSelection();
      syncHeaderSelection();
      updateSelectionUI();
      setTableLoaderState(wrapper, false);
      wrapper.dataset.bound = 'true';
      return;
    }

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

    const render = () => {
      const rawQuery = searchInput?.value || '';
      const query = normalizeSearchText(rawQuery);
      const exactQuery = /\s$/.test(rawQuery);
      let filteredRows = allRows.filter((row) => {
        if (!query) {
          return true;
        }
        const cell = row.children[searchColumn];
        const cellText = normalizeSearchText(cell?.dataset.searchText || cell?.textContent || '');
        return exactQuery ? cellText === query : cellText.includes(query);
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

      currentFilteredCount = filteredRows.length;
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

      syncVisibleSelection();
      syncHeaderSelection();
      updateSelectionUI();
      saveState();
      setTableLoaderState(wrapper, false);
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
      resetSelectionState();
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

    selectAllCheckbox?.addEventListener('change', () => {
      toggleVisibleSelection(selectAllCheckbox.checked);
    });

    selectAllResultsButton?.addEventListener('click', () => {
      selectionState.allFiltered = true;
      selectionState.includedIds.clear();
      selectionState.excludedIds.clear();
      syncVisibleSelection();
      syncHeaderSelection();
      updateSelectionUI();
    });

    clearSelectionButton?.addEventListener('click', () => {
      resetSelectionState();
    });

    tbody.addEventListener('change', (event) => {
      if (!event.target.matches('[data-table-select]')) {
        return;
      }
      applyCheckboxChange(event.target, event.target.checked);
    });

    render();
    wrapper.dataset.bound = 'true';
  };

  const initDataTables = () => {
    const queue = Array.from(document.querySelectorAll('.data-table')).filter((wrapper) => wrapper.dataset.bound !== 'true' && wrapper.dataset.bound !== 'pending');
    const processNext = () => {
      const wrapper = queue.shift();
      if (!wrapper) {
        return;
      }
      wrapper.dataset.bound = 'pending';
      setTableLoaderState(wrapper, true);
      window.requestAnimationFrame(() => {
        initSingleDataTable(wrapper);
        window.setTimeout(processNext, 0);
      });
    };

    processNext();
  };

  const setRemoteModalContent = (modal, title, body) => {
    modal.querySelector('[data-modal-title]')?.replaceChildren(document.createTextNode(title));
    const bodyTarget = modal.querySelector('[data-modal-body]');
    if (bodyTarget) {
      bodyTarget.innerHTML = body;
    }
  };

  const setRemoteModalLoading = (modal, accent = 'sky', message = 'Memuat data...') => {
    const title = message;
    const spinnerClass = accent === 'emerald' ? 'border-t-emerald-500' : 'border-t-sky-500';
    const body = `<div class="flex items-center gap-3 text-sm text-slate-500"><span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-slate-200 ${spinnerClass}"></span><span>${message}</span></div>`;
    setRemoteModalContent(modal, title, body);
  };

  const parseJsonResponse = (raw) => {
    try {
      return JSON.parse(raw);
    } catch (error) {
      const start = raw.indexOf('{');
      const end = raw.lastIndexOf('}');
      if (start !== -1 && end !== -1 && end > start) {
        return JSON.parse(raw.slice(start, end + 1));
      }
      throw error;
    }
  };

  const handleUnauthenticated = (result) => {
    if (!result?.unauthenticated) {
      return false;
    }
    window.location.href = result.redirect || 'index.php';
    return true;
  };

  const openRemoteModal = async (trigger) => {
    const modalId = trigger.dataset.modalTarget || '';
    const url = trigger.dataset.modalUrl || '';
    const modal = document.getElementById(modalId);
    if (!modal || !url) {
      showToast('Modal detail tidak ditemukan.', 'error');
      return;
    }

    const accent = modalId.includes('edit') ? 'emerald' : 'sky';
    const loadingMessage = modalId.includes('edit') ? 'Memuat form absensi...' : 'Memuat detail absensi...';
    setRemoteModalLoading(modal, accent, loadingMessage);
    openModalById(modalId);

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const raw = await response.text();
      let result;
      try {
        result = parseJsonResponse(raw);
      } catch (parseError) {
        throw new Error(response.ok ? 'Respons modal tidak valid.' : raw.slice(0, 200));
      }
      if (handleUnauthenticated(result)) {
        return;
      }
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Gagal memuat modal.');
      }
      setRemoteModalContent(modal, result.title || 'Detail', result.body || '');
    } catch (error) {
      setRemoteModalContent(modal, 'Gagal Memuat', `<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">${error.message}</div>`);
    }
  };

  const submitUploadFormWithProgress = (form) => new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const formData = new FormData(form);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let visualProgress = 0;
    let processingTimer = null;

    const setVisualProgress = (nextPercent, status, note) => {
      visualProgress = Math.max(visualProgress, Math.min(100, nextPercent));
      updateProgressOverlay({
        title: 'Import Absensi',
        status,
        note,
        percent: visualProgress,
      });
    };

    const startProcessingAnimation = () => {
      if (processingTimer) {
        return;
      }
      processingTimer = window.setInterval(() => {
        if (visualProgress >= 95) {
          window.clearInterval(processingTimer);
          processingTimer = null;
          return;
        }
        setVisualProgress(
          Math.min(95, visualProgress + (visualProgress < 85 ? 3 : 1)),
          'Memproses data absensi...',
          'File sudah terkirim. Server sedang membaca dan menyimpan data.'
        );
      }, 180);
    };

    setVisualProgress(5, 'Menyiapkan upload file...', 'Validasi file dan koneksi sedang disiapkan.');

    xhr.open(form.method || 'POST', form.action, true);
    xhr.withCredentials = true;
    if (csrf) {
      xhr.setRequestHeader('X-CSRF-Token', csrf);
    }
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) {
        setVisualProgress(25, 'Mengunggah file absensi...', 'Progress upload sedang dihitung.');
        return;
      }
      const uploadPercent = 10 + ((event.loaded / event.total) * 70);
      setVisualProgress(uploadPercent, 'Mengunggah file absensi...', 'File sedang dikirim ke server.');
      if (event.loaded === event.total) {
        startProcessingAnimation();
      }
    });

    xhr.addEventListener('load', () => {
      if (processingTimer) {
        window.clearInterval(processingTimer);
      }
      let result;
      try {
        result = parseJsonResponse(xhr.responseText);
      } catch (error) {
        hideProgressOverlay();
        reject(new Error('Respons server tidak valid. Periksa error PHP pada endpoint.'));
        return;
      }

      if (result.confirmImportConflict) {
        hideProgressOverlay();
        resolve(result);
        return;
      }

      if (xhr.status < 200 || xhr.status >= 300 || !result.success) {
        if (handleUnauthenticated(result)) {
          return;
        }
        hideProgressOverlay();
        reject(new Error(result.message || 'Upload absensi gagal diproses.'));
        return;
      }

      setVisualProgress(100, 'Import berhasil.', 'Data absensi berhasil diproses dan disimpan.');
      window.setTimeout(() => {
        hideProgressOverlay();
        resolve(result);
      }, 350);
    });

    xhr.addEventListener('error', () => {
      if (processingTimer) {
        window.clearInterval(processingTimer);
      }
      hideProgressOverlay();
      reject(new Error('Koneksi terputus saat upload absensi.'));
    });

    xhr.addEventListener('abort', () => {
      if (processingTimer) {
        window.clearInterval(processingTimer);
      }
      hideProgressOverlay();
      reject(new Error('Upload absensi dibatalkan.'));
    });

    xhr.send(formData);
  });

  const loadSection = async (section, params = null) => {
    currentSection = section;
    if (params !== null) {
      sectionParams[section] = params;
      persistSectionParams();
    }
    if (sectionRequestController) {
      sectionRequestController.abort();
    }
    const controller = new AbortController();
    sectionRequestController = controller;
    const { signal } = controller;
    let timeoutId = null;
    const activeParams = sectionParams[section] || {};
    const query = new URLSearchParams(activeParams).toString();
    localStorage.setItem(STORAGE_KEY, section);
    window.location.hash = section;
    pageTitle.textContent = section.charAt(0).toUpperCase() + section.slice(1);
    setActiveNav(section);
    pageContent.classList.add('page-busy');
    pageContent.innerHTML = '<div class="soft-card p-8 text-sm text-slate-500">Memuat data...</div>';
    if (!bootLoaderDismissed) {
      window.requestAnimationFrame(() => {
        dismissBootLoaderEarly();
      });
    }
    setLoaderStep('assets', 'done');
    setLoaderStep('components', 'active');
    setLoaderProgress(45, 'Menyusun komponen antarmuka...');
    try {
      window.setTimeout(() => {
        setLoaderStep('components', 'done');
        setLoaderStep('data', 'active');
        setLoaderProgress(70, 'Mengambil data halaman awal...');
      }, 80);
      timeoutId = window.setTimeout(() => {
        controller.abort('timeout');
      }, 12000);
      const response = await fetch(`ajax/${section}.php${query ? '?' + query : ''}`, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal,
      });
      window.clearTimeout(timeoutId);
      if (!response.ok) {
        throw new Error(`Gagal memuat halaman (${response.status}).`);
      }
      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      const responseText = await response.text();

      if (contentType.includes('application/json')) {
        const result = parseJsonResponse(responseText);
        if (handleUnauthenticated(result)) {
          return;
        }
        throw new Error(result.message || 'Respons halaman tidak valid.');
      }

      if (isFullDocumentResponse(responseText)) {
        const looksLikeLoginPage = /name=\"action\"\s+value=\"login\"|sign in/i.test(responseText);
        if (looksLikeLoginPage) {
          window.location.href = 'index.php';
          return;
        }

        window.location.reload();
        return;
      }

      pageContent.innerHTML = responseText;
      initDataTables();
      appReady = true;
      setLoaderStep('data', 'done');
      setLoaderStep('ready', 'active');
      setLoaderProgress(92, 'Finalisasi tampilan siap pakai...');
      if (!bootLoaderDismissed) {
        finishAppLoader();
      }
    } catch (error) {
      if (signal.aborted) {
        if (error?.name === 'AbortError') {
          if (signal.reason === 'timeout') {
            pageContent.innerHTML = '<div class="rounded-[28px] border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700">Halaman terlalu lama dimuat. Silakan coba lagi.</div>';
            showToast('Memuat halaman terlalu lama. Coba lagi.', 'error');
          }
          return;
        }
      }
      pageContent.innerHTML = `<div class="rounded-[28px] border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700">${error.message}</div>`;
      showToast(error.message, 'error');
    } finally {
      if (timeoutId !== null) {
        window.clearTimeout(timeoutId);
      }
      pageContent.classList.remove('page-busy');
      if (!bootLoaderDismissed && document.readyState === 'complete' && appReady) {
        finishAppLoader();
      }
      if (sectionRequestController === controller) {
        sectionRequestController = null;
      }
    }
  };

  const submitAjaxForm = async (form) => {
    let result;

    try {
      if (form.dataset.uploadProgress === 'import-absensi') {
        result = await submitUploadFormWithProgress(form);
      } else {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        const raw = await response.text();
        try {
          result = parseJsonResponse(raw);
        } catch (error) {
          showToast('Respons server tidak valid. Periksa error PHP pada endpoint.', 'error');
          console.error('Non-JSON response:', raw);
          return;
        }
        if (handleUnauthenticated(result)) {
          return;
        }
      }
    } catch (error) {
      showToast(error.message, 'error');
      return;
    }

    if (result.confirmImportConflict) {
      const confirmed = window.confirm(result.confirmImportConflict.message || 'Kode absensi ini sudah ada di database. Lanjutkan?');
      if (!confirmed) {
        showToast('Import dibatalkan.', 'error');
        return;
      }

      const retryForm = new FormData();
      retryForm.append('_csrf', document.querySelector('meta[name="csrf-token"]')?.content || '');
      retryForm.append('import_token', result.confirmImportConflict.token || '');
      retryForm.append('allow_code_takeover', '1');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: retryForm,
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        result = parseJsonResponse(await response.text());
        if (handleUnauthenticated(result)) {
          return;
        }
      } catch (error) {
        showToast(error.message, 'error');
        return;
      }
    }

    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
      const clearSelectionTarget = form.dataset.clearSelectionTarget || '';
      if (clearSelectionTarget) {
        document.getElementById(clearSelectionTarget)?.__tableSelection?.clear?.();
      }
      if (result.closeModal) {
        closeModalById(result.closeModal);
      }
      if (result.reloadSection) {
        const reloadParams = result.reloadParams && typeof result.reloadParams === 'object'
          ? result.reloadParams
          : getSectionParams(result.reloadSection);
        loadSection(result.reloadSection, reloadParams);
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

    const sectionPager = event.target.closest('[data-load-section]');
    if (sectionPager) {
      let nextParams = {};
      try {
        nextParams = JSON.parse(sectionPager.dataset.sectionParams || '{}') || {};
      } catch (error) {
        nextParams = {};
      }
      loadSection(sectionPager.dataset.loadSection, nextParams);
      return;
    }

    const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
      if (!appReady) {
        return;
      }
      toggleSidebarState();
      return;
    }

    const sidebarClose = event.target.closest('[data-sidebar-close]');
    if (sidebarClose) {
      if (!appReady) {
        return;
      }
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

    const openRemoteModalTrigger = event.target.closest('[data-open-remote-modal]');
    if (openRemoteModalTrigger) {
      openRemoteModal(openRemoteModalTrigger);
      return;
    }

    const repeatableAdd = event.target.closest('[data-repeatable-add]');
    if (repeatableAdd) {
      const table = repeatableAdd.closest('[data-repeatable-table]');
      const body = table?.querySelector('[data-repeatable-body]');
      const template = table?.querySelector('[data-repeatable-template]');
      if (!body || !template) {
        return;
      }

      body.insertAdjacentHTML('beforeend', template.innerHTML);
      return;
    }

    const repeatableRemove = event.target.closest('[data-repeatable-remove]');
    if (repeatableRemove) {
      const row = repeatableRemove.closest('[data-repeatable-row]');
      const body = row?.parentElement;
      const rowCount = body ? body.querySelectorAll('[data-repeatable-row]').length : 0;
      if (!row || !body) {
        return;
      }

      if (rowCount <= 1) {
        row.querySelectorAll('input, select, textarea').forEach((field) => {
          field.value = '';
        });
        return;
      }

      row.remove();
      return;
    }

    const generateMonthOption = event.target.closest('[data-generate-month-option]');
    if (generateMonthOption) {
      const picker = generateMonthOption.closest('[data-generate-month-picker]');
      const form = generateMonthOption.closest('form');
      const input = form?.querySelector('[data-generate-month-input]');
      if (!picker || !input) {
        return;
      }

      input.value = generateMonthOption.dataset.generateMonthOption || '';
      picker.querySelectorAll('[data-generate-month-option]').forEach((button) => {
        button.classList.remove('border-emerald-400', 'bg-emerald-50', 'ring-4', 'ring-emerald-100');
        button.classList.add('border-slate-200', 'bg-white');
      });
      generateMonthOption.classList.remove('border-slate-200', 'bg-white');
      generateMonthOption.classList.add('border-emerald-400', 'bg-emerald-50', 'ring-4', 'ring-emerald-100');
      return;
    }

    const closeModal = event.target.closest('[data-close-modal]');
    if (closeModal) {
      closeModalById(closeModal.dataset.closeModal);
      return;
    }

    const bulkDelete = event.target.closest('[data-bulk-delete]');
    if (!bulkDelete) {
      return;
    }

    const table = document.getElementById(bulkDelete.dataset.tableTarget || '');
    const form = document.getElementById(bulkDelete.dataset.formTarget || '');
    const controller = table?.__tableSelection;

    if (!table || !form || !controller) {
      showToast('Form hapus massal tidak ditemukan.', 'error');
      return;
    }

    const itemLabel = bulkDelete.dataset.bulkItemLabel || 'data';
    const emptyMessage = bulkDelete.dataset.bulkEmptyMessage || `Pilih ${itemLabel} yang ingin dihapus.`;
    const confirmMessageTemplate = bulkDelete.dataset.bulkConfirmMessage || `Hapus permanen {count} ${itemLabel} terpilih?`;
    const selectedCount = controller.getSelectedCount();

    if (selectedCount === 0) {
      showToast(emptyMessage, 'error');
      return;
    }

    const confirmMessage = confirmMessageTemplate.replace('{count}', formatNumber(selectedCount));
    if (!window.confirm(confirmMessage)) {
      return;
    }

    if (!controller.populateBulkDeleteForm(form)) {
      showToast('Data seleksi tidak dapat diproses.', 'error');
      return;
    }

    form.dataset.clearSelectionTarget = table.id;
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

    if (event.target.matches('[data-force-uppercase]')) {
      event.target.value = String(event.target.value || '').toUpperCase();
    }

    if (event.target.matches('[name="jam_masuk"], [name="jam_keluar"]')) {
      event.target.dataset.autoFilled = 'false';
    }

    const payrollField = event.target.closest('[data-payroll-calc]');
    if (payrollField) {
      recalculatePayrollForm(payrollField.closest('[data-payroll-form]'));
    }
  });

  document.addEventListener('change', (event) => {
    const sectionLoadOnChange = event.target.closest('[data-section-load-on-change]');
    if (sectionLoadOnChange) {
      const section = sectionLoadOnChange.dataset.sectionLoadOnChange || currentSection;
      const paramName = sectionLoadOnChange.dataset.sectionLoadParam || sectionLoadOnChange.name;
      if (paramName) {
        const nextParams = {
          ...(getSectionParams(section) || {}),
          [paramName]: sectionLoadOnChange.value,
        };
        loadSection(section, nextParams);
        return;
      }
    }

    const payrollField = event.target.closest('[data-payroll-calc]');
    if (payrollField) {
      recalculatePayrollForm(payrollField.closest('[data-payroll-form]'));
    }

    const trigger = event.target.closest('[data-absensi-calc]');
    if (!trigger) {
      return;
    }

    const form = trigger.closest('[data-absensi-form]');
    if (!form) {
      return;
    }

    const userSelect = form.querySelector('[name="user_id"]');
    const shiftField = form.querySelector('[name="shift"]');
    const jamMasukField = form.querySelector('[name="jam_masuk"]');
    const status = form.querySelector('[name="status"]')?.value || '';
    const shift = shiftField?.value || '';
    const jamMasuk = jamMasukField?.value || '';
    const totalField = form.querySelector('[name="total_menit_terlambat"]');
    const jumlahField = form.querySelector('[name="jumlah_potongan"]');

    if (!userSelect || !totalField || !jumlahField) {
      return;
    }

    const selectedOption = userSelect.options[userSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      totalField.value = '0';
      jumlahField.value = '0';
      return;
    }

    if (trigger === userSelect || trigger === shiftField) {
      applyAttendanceDefaults(form, selectedOption);
    }

    const jabatan = (selectedOption?.dataset.jabatan || '').toLowerCase();
    const potonganTerlambat = Number(selectedOption?.dataset.potonganTerlambat || 1000);
    const toleransiTerlambat = Number(selectedOption?.dataset.toleransiTerlambat || 0);
    const shiftRules = parseShiftRules(selectedOption?.dataset.shiftRules || '{}');
    const scheduledJamMasuk = shiftRules[String(shiftField?.value || '')]?.jam_masuk || selectedOption?.dataset.defaultJamMasuk || '';
    const totalMenit = calculateAttendanceLateMinutes({
      status,
      jamMasuk: jamMasukField?.value || '',
      shift: shiftField?.value || '',
      jabatan,
      toleranceMinutes: toleransiTerlambat,
      scheduledJamMasuk,
    });

    totalField.value = String(totalMenit);
    jumlahField.value = String(totalMenit * potonganTerlambat);
  });

  window.addEventListener('hashchange', () => {
    const nextSection = window.location.hash.replace('#', '');
    if (nextSection && nextSection !== currentSection && !anyModalOpen()) {
      loadSection(nextSection, sectionParams[nextSection] || null);
    }
  });

  window.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    const activeModal = getActiveModal();
    if (!activeModal) {
      return;
    }

    closeModalById(activeModal.id);
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

  window.addEventListener('load', () => {
    setLoaderStep('shell', 'done');
    setLoaderStep('assets', 'active');
    setLoaderProgress(28, 'Asset stylesheet dan script siap digunakan...');
    if (appReady) {
      finishAppLoader();
      return;
    }
    dismissBootLoaderEarly();
  });

  loadSection(currentSection, sectionParams[currentSection] || null);
})();
