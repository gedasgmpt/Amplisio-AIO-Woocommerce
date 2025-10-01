import './style.css';

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('amplisio-aio-settings-root');

  if (!root || !window.amplisioAioSettings) {
    return;
  }

  const data = window.amplisioAioSettings;
  const state = {
    settings: { ...data.settings },
    modules: [...data.modules],
    message: '',
    status: 'idle',
  };

  const render = () => {
    root.innerHTML = '';

    const form = document.createElement('form');
    form.className = 'amplisio-aio-form';

    const intro = document.createElement('p');
    intro.textContent = data.i18n.intro || 'Control Amplisio AIO behaviour for your store.';
    form.appendChild(intro);

    const generalFieldset = document.createElement('fieldset');
    generalFieldset.innerHTML = `<legend>${data.i18n.general || 'General settings'}</legend>`;

    const storeColorLabel = document.createElement('label');
    storeColorLabel.className = 'amplisio-field';
    storeColorLabel.innerHTML = `
      <span>${data.i18n.accentColor || 'Accent color'}</span>
      <input type="text" name="accentColor" value="${state.settings.accentColor || ''}" placeholder="#2d6cdf" />
    `;
    generalFieldset.appendChild(storeColorLabel);

    form.appendChild(generalFieldset);

    const modulesFieldset = document.createElement('fieldset');
    modulesFieldset.innerHTML = `<legend>${data.i18n.modules || 'Modules'}</legend>`;

    state.modules.forEach((module) => {
      const wrapper = document.createElement('label');
      wrapper.className = 'amplisio-toggle';

      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = !!module.enabled;
      checkbox.dataset.slug = module.slug;
      checkbox.addEventListener('change', () => {
        module.enabled = checkbox.checked;
      });

      const toggleLabel = document.createElement('span');
      toggleLabel.innerHTML = `
        <strong>${module.name}</strong>
        <small>${module.description}</small>
      `;

      wrapper.appendChild(checkbox);
      wrapper.appendChild(toggleLabel);
      modulesFieldset.appendChild(wrapper);
    });

    form.appendChild(modulesFieldset);

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'button button-primary';
    submit.textContent = data.i18n.save;
    submit.disabled = state.status === 'saving';
    form.appendChild(submit);

    if (state.message) {
      const notice = document.createElement('div');
      notice.className = `notice ${state.status === 'error' ? 'notice-error' : 'notice-success'}`;
      notice.innerHTML = `<p>${state.message}</p>`;
      form.appendChild(notice);
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      await saveSettings(new FormData(form));
    });

    root.appendChild(form);
  };

  const saveSettings = async (formData) => {
    state.status = 'saving';
    state.message = '';
    render();

    const payload = {
      settings: {
        accentColor: formData.get('accentColor') || '',
      },
      modules: state.modules.map((module) => ({ slug: module.slug, enabled: !!module.enabled })),
    };

    try {
      const response = await fetch(data.restUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': data.nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        throw new Error('Request failed');
      }

      const result = await response.json();
      state.settings = result.settings;
      state.modules = result.modules;
      state.status = 'success';
      state.message = data.i18n.success;
    } catch (error) {
      state.status = 'error';
      state.message = data.i18n.error;
    }

    render();
  };

  render();
});
