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
    if (!appLoader) {
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
    appLoader.classList.add('hidden');
    window.setTimeout(() => {
      appLoader.style.display = 'none';
    }, 220);
    document.body.classList.remove('app-loading');
  };

  const finishAppLoader = () => {
    setLoaderStep('ready', 'done');
    setLoaderProgress(100, 'Semua asset, komponen, dan data berhasil dimuat.');
    window.setTimeout(hideAppLoader, 180);
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
    let serverParams = {};

    try {
      serverParams = JSON.parse(serverParamsRaw) || {};
    } catch (error) {
      serverParams = {};
    }

    const syncSelectionState = () => {
      if (!selectAll) {
        return;
      }
      const visibleCheckboxes = Array.from(tbody.querySelectorAll('[data-table-select]'));
      const checkedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
      selectAll.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    };

    if (serverSection) {
      let searchTimer = null;
      searchInput?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
          const nextParams = { ...serverParams, search: searchInput.value || '', page: 1, master_page: 1, payroll_page: 1 };
          loadSection(serverSection, nextParams);
        }, 300);
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

      syncSelectionState();
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
    const activeParams = sectionParams[section] || {};
    const query = new URLSearchParams(activeParams).toString();
    localStorage.setItem(STORAGE_KEY, section);
    window.location.hash = section;
    pageTitle.textContent = section.charAt(0).toUpperCase() + section.slice(1);
    setActiveNav(section);
    pageContent.classList.add('page-busy');
    pageContent.innerHTML = '<div class="soft-card p-8 text-sm text-slate-500">Memuat data...</div>';
    setLoaderStep('assets', 'done');
    setLoaderStep('components', 'active');
    setLoaderProgress(45, 'Menyusun komponen antarmuka...');
    try {
      window.setTimeout(() => {
        setLoaderStep('components', 'done');
        setLoaderStep('data', 'active');
        setLoaderProgress(70, 'Mengambil data halaman awal...');
      }, 80);
      const response = await fetch(`ajax/${section}.php${query ? '?' + query : ''}`, { credentials: 'same-origin' });
      pageContent.innerHTML = await response.text();
      initDataTables();
      appReady = true;
      setLoaderStep('data', 'done');
      setLoaderStep('ready', 'active');
      setLoaderProgress(92, 'Finalisasi tampilan siap pakai...');
    } finally {
      pageContent.classList.remove('page-busy');
      if (document.readyState === 'complete') {
        finishAppLoader();
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
    const itemLabel = bulkDelete.dataset.bulkItemLabel || 'data';
    const emptyMessage = bulkDelete.dataset.bulkEmptyMessage || `Pilih ${itemLabel} yang ingin dihapus.`;
    const confirmMessageTemplate = bulkDelete.dataset.bulkConfirmMessage || `Hapus permanen {count} ${itemLabel} terpilih?`;

    if (ids.length === 0) {
      showToast(emptyMessage, 'error');
      return;
    }

    const confirmMessage = confirmMessageTemplate.replace('{count}', String(ids.length));
    if (!window.confirm(confirmMessage)) {
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

  window.addEventListener('load', () => {
    setLoaderStep('shell', 'done');
    setLoaderStep('assets', 'active');
    setLoaderProgress(28, 'Asset stylesheet dan script siap digunakan...');
    if (appReady) {
      finishAppLoader();
      return;
    }
    showAppLoader();
  });

  loadSection(currentSection, sectionParams[currentSection] || null);
})();
