(() => {
  const STORAGE_KEY = 'sigaji.active.section';
  let currentSection = localStorage.getItem(STORAGE_KEY) || window.location.hash.replace('#', '') || 'dashboard';

  const anyModalOpen = () => document.querySelector('[data-modal].is-open') !== null;
  const formBeingEdited = () => document.querySelector('[data-ajax-form][data-dirty="true"]') !== null;

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

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (toggle) {
      const target = document.getElementById(toggle.dataset.target);
      if (!target) {
        return;
      }

      const hidden = target.type === 'password';
      target.type = hidden ? 'text' : 'password';
      toggle.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', hidden);
      toggle.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', !hidden);
      toggle.setAttribute('aria-label', hidden ? 'Sembunyikan password' : 'Tampilkan password');
      return;
    }

    const nav = event.target.closest('.nav-link');
    if (nav) {
      loadSection(nav.dataset.section);
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
    }
  });

  const pageContent = document.getElementById('page-content');
  const pageTitle = document.getElementById('page-title');
  const toast = document.getElementById('toast');

  if (!pageContent || !pageTitle || !toast) {
    return;
  }

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

  const loadSection = async (section) => {
    currentSection = section;
    localStorage.setItem(STORAGE_KEY, section);
    window.location.hash = section;
    pageTitle.textContent = section.charAt(0).toUpperCase() + section.slice(1);
    setActiveNav(section);
    pageContent.innerHTML = '<div class="soft-card p-8 text-sm text-slate-500">Memuat data...</div>';
    const response = await fetch(`ajax/${section}.php`, { credentials: 'same-origin' });
    pageContent.innerHTML = await response.text();
  };

  const submitAjaxForm = async (form) => {
    const formData = new FormData(form);
    const response = await fetch(form.action, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
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
        loadSection(result.reloadSection);
      }
    }
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
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

  window.setInterval(() => {
    if (document.visibilityState === 'visible' && !anyModalOpen() && !formBeingEdited()) {
      loadSection(currentSection);
    }
  }, 45000);

  window.addEventListener('hashchange', () => {
    const nextSection = window.location.hash.replace('#', '');
    if (nextSection && nextSection !== currentSection && !anyModalOpen()) {
      loadSection(nextSection);
    }
  });

  loadSection(currentSection);
})();
