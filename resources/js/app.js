(() => {
  const pageContent = document.getElementById('page-content');
  const pageTitle = document.getElementById('page-title');
  const toast = document.getElementById('toast');

  if (!pageContent || !pageTitle || !toast) {
    return;
  }

  let currentSection = 'dashboard';

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
    const result = await response.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
      if (result.closeModal) {
        document.getElementById(result.closeModal)?.classList.add('hidden');
      }
      if (result.reloadSection) {
        loadSection(result.reloadSection);
      }
    }
  };

  document.addEventListener('click', (event) => {
    const nav = event.target.closest('.nav-link');
    if (nav) {
      loadSection(nav.dataset.section);
    }

    const openModal = event.target.closest('[data-open-modal]');
    if (openModal) {
      document.getElementById(openModal.dataset.openModal)?.classList.remove('hidden');
    }

    const closeModal = event.target.closest('[data-close-modal]');
    if (closeModal) {
      document.getElementById(closeModal.dataset.closeModal)?.classList.add('hidden');
    }
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-ajax-form]')) {
      return;
    }
    event.preventDefault();
    submitAjaxForm(form);
  });

  window.setInterval(() => {
    if (document.visibilityState === 'visible') {
      loadSection(currentSection);
    }
  }, 45000);

  loadSection(currentSection);
})();
