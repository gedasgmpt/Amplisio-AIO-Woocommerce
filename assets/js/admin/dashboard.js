const {
  root: apiRoot,
  nonce,
  modules: initialModules,
  themeFallbacks = {},
  themeCssVariables: baseThemeVariables = {},
} = window.amplisioAioData || {};
const root = document.getElementById('amplisio-aio-dashboard');

if (root && apiRoot) {
  const initialAbandoned = {
    settings: null,
    loading: false,
    saving: false,
    preview: null,
    message: '',
    testEmail: '',
    sendingTest: false,
  };

  const state = {
    cards: [],
    theme: window.amplisioAioData.settings.theme || {},
    modules: initialModules,
    loading: false,
    saving: false,
    abandoned: { ...initialAbandoned },
  };

  const translate = (text) => {
    if (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
      return window.wp.i18n.__(text, 'amplisio-aio');
    }

    return text;
  };

  const fetchJSON = async (path, options = {}) => {
    const response = await fetch(`${apiRoot}/${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      throw new Error('Request failed');
    }

    return response.json();
  };

  const withAbandoned = (partial) => ({
    abandoned: {
      ...state.abandoned,
      ...partial,
    },
  });

  const setState = (partial) => {
    Object.assign(state, partial);
    render();
  };

  const resolveThemeVariable = (key, override) => {
    const value = typeof override === 'string' ? override.trim() : '';
    if (value) {
      return value;
    }

    return baseThemeVariables[key] || '';
  };

  const applyThemeVariables = (node) => {
    const variables = {
      '--amplisio-font-family': resolveThemeVariable('--amplisio-font-family', state.theme.fontFamily),
      '--amplisio-primary': resolveThemeVariable('--amplisio-primary', state.theme.primaryColor),
      '--amplisio-accent': resolveThemeVariable('--amplisio-accent', state.theme.accentColor),
      '--amplisio-radius': resolveThemeVariable('--amplisio-radius', state.theme.radius),
    };

    Object.entries(variables).forEach(([name, value]) => {
      if (value) {
        node.style.setProperty(name, value);
      } else {
        node.style.removeProperty(name);
      }
    });
  };

  const isAbandonedEnabled = () =>
    state.modules.some((module) => module.id === 'abandoned_cart' && module.enabled);

  const loadDashboard = async () => {
    try {
      setState({ loading: true });
      const data = await fetchJSON('dashboard');
      setState({ cards: data.cards || [], theme: data.theme || state.theme, loading: false });
      if (isAbandonedEnabled()) {
        await loadAbandonedSettings();
      } else {
        setState(withAbandoned({ ...initialAbandoned }));
      }
    } catch (error) {
      console.error(error);
      setState({ loading: false });
    }
  };

  const loadAbandonedSettings = async () => {
    if (!isAbandonedEnabled()) {
      setState(withAbandoned({ ...initialAbandoned }));
      return;
    }

    try {
      setState(withAbandoned({ loading: true, message: '', preview: null }));
      const settings = await fetchJSON('abandoned-cart/sequences');
      setState(withAbandoned({ settings, loading: false }));
    } catch (error) {
      console.error(error);
      setState(withAbandoned({ loading: false, message: translate('Unable to load abandoned cart settings.') }));
    }
  };

  const updateSettings = async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const modulesPayload = {};

    state.modules.forEach((module) => {
      const field = form.querySelector(`[data-module-id="${module.id}"]`);
      modulesPayload[module.id] = field ? field.checked : module.enabled;
    });

    const payload = {
      theme: {
        fontFamily: (formData.get('fontFamily') || '').trim(),
        primaryColor: (formData.get('primaryColor') || '').trim(),
        accentColor: (formData.get('accentColor') || '').trim(),
        radius: (formData.get('radius') || '').trim(),
      },
      modules: modulesPayload,
    };

    try {
      setState({ saving: true });
      const updated = await fetchJSON('settings', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      const modules = state.modules.map((module) => ({
        ...module,
        enabled: Boolean(modulesPayload[module.id]),
      }));
      setState({
        theme: updated.theme,
        modules,
        saving: false,
      });
      if (!modulesPayload.abandoned_cart) {
        setState(withAbandoned({ ...initialAbandoned }));
      }
      await loadDashboard();
    } catch (error) {
      console.error(error);
      setState({ saving: false });
    }
  };

  const updateSequenceField = (sequenceId, field, value) => {
    if (!state.abandoned.settings) {
      return;
    }

    const settings = {
      ...state.abandoned.settings,
      sequences: state.abandoned.settings.sequences.map((sequence) =>
        sequence.id === sequenceId
          ? {
              ...sequence,
              [field]: value,
            }
          : sequence
      ),
    };

    setState(withAbandoned({ settings }));
  };

  const updateTimingField = (field, value) => {
    if (!state.abandoned.settings) {
      return;
    }

    const settings = {
      ...state.abandoned.settings,
      [field]: value,
    };

    setState(withAbandoned({ settings }));
  };

  const saveSequences = async (event) => {
    event.preventDefault();
    if (!state.abandoned.settings) {
      return;
    }

    try {
      setState(withAbandoned({ saving: true, message: '' }));
      const updated = await fetchJSON('abandoned-cart/sequences', {
        method: 'POST',
        body: JSON.stringify(state.abandoned.settings),
      });
      setState(withAbandoned({ settings: updated, saving: false, message: translate('Sequences saved.') }));
    } catch (error) {
      console.error(error);
      setState(withAbandoned({ saving: false, message: translate('Unable to save sequences.') }));
    }
  };

  const previewSequence = async (sequenceId) => {
    try {
      setState(withAbandoned({ message: '', preview: { loading: true } }));
      const preview = await fetchJSON('abandoned-cart/preview', {
        method: 'POST',
        body: JSON.stringify({ sequenceId }),
      });
      setState(withAbandoned({ preview, message: '' }));
    } catch (error) {
      console.error(error);
      setState(withAbandoned({ preview: null, message: translate('Unable to generate preview.') }));
    }
  };

  const sendTest = async (sequenceId) => {
    if (!state.abandoned.testEmail) {
      setState(withAbandoned({ message: translate('Enter a test email before sending.'), preview: state.abandoned.preview }));
      return;
    }

    try {
      setState(withAbandoned({ sendingTest: true, message: '' }));
      await fetchJSON('abandoned-cart/test', {
        method: 'POST',
        body: JSON.stringify({ sequenceId, email: state.abandoned.testEmail }),
      });
      setState(withAbandoned({ sendingTest: false, message: translate('Test email sent.') }));
    } catch (error) {
      console.error(error);
      setState(withAbandoned({ sendingTest: false, message: translate('Unable to send test email.') }));
    }
  };

  const createCard = (card) => {
    const article = document.createElement('article');
    article.className = 'amplisio-card';
    article.setAttribute('role', 'group');
    article.setAttribute('aria-label', card.title);

    const title = document.createElement('h3');
    title.textContent = card.title;
    title.className = 'amplisio-card__title';
    article.appendChild(title);

    const value = document.createElement('p');
    value.className = 'amplisio-card__value';
    value.textContent = card.value;
    article.appendChild(value);

    const description = document.createElement('p');
    description.className = 'amplisio-card__description';
    description.textContent = card.description;
    article.appendChild(description);

    return article;
  };

  const renderCards = () => {
    const section = document.createElement('section');
    section.className = 'amplisio-grid';
    section.setAttribute('aria-live', 'polite');

    if (state.loading) {
      const loader = document.createElement('p');
      loader.textContent = translate('Loading metrics…');
      loader.className = 'amplisio-dashboard__loading';
      section.appendChild(loader);
      return section;
    }

    if (!state.cards.length) {
      const empty = document.createElement('p');
      empty.textContent = translate('No data available yet.');
      empty.className = 'amplisio-dashboard__empty';
      section.appendChild(empty);
      return section;
    }

    state.cards.forEach((card) => {
      section.appendChild(createCard(card));
    });

    return section;
  };

  const renderThemeForm = () => {
    const fieldset = document.createElement('fieldset');
    fieldset.className = 'amplisio-fieldset';

    const legend = document.createElement('legend');
    legend.textContent = translate('Theme');
    fieldset.appendChild(legend);

    const fields = [
      {
        label: translate('Font family'),
        name: 'fontFamily',
        type: 'text',
        value: state.theme.fontFamily || '',
        placeholder: themeFallbacks.fontFamily || '"Inter", sans-serif',
      },
      {
        label: translate('Primary color'),
        name: 'primaryColor',
        type: 'text',
        value: state.theme.primaryColor || '',
        placeholder: themeFallbacks.primaryColor || '#2563eb',
      },
      {
        label: translate('Accent color'),
        name: 'accentColor',
        type: 'text',
        value: state.theme.accentColor || '',
        placeholder: themeFallbacks.accentColor || '#ec4899',
      },
      {
        label: translate('Border radius'),
        name: 'radius',
        type: 'text',
        value: state.theme.radius || '',
        placeholder: themeFallbacks.radius || '12px',
      },
    ];

    fields.forEach((field) => {
      const wrapper = document.createElement('label');
      wrapper.className = 'amplisio-field';
      wrapper.textContent = field.label;
      const input = document.createElement('input');
      input.name = field.name;
      input.type = field.type;
      input.value = field.value || '';
      if (field.placeholder) {
        input.placeholder = field.placeholder;
      }
      input.autocomplete = 'off';
      input.spellcheck = false;
      input.setAttribute('aria-label', field.label);
      wrapper.appendChild(input);
      fieldset.appendChild(wrapper);
    });

    return fieldset;
  };

  const renderModuleToggles = () => {
    const fieldset = document.createElement('fieldset');
    fieldset.className = 'amplisio-fieldset';

    const legend = document.createElement('legend');
    legend.textContent = translate('Modules');
    fieldset.appendChild(legend);

    state.modules.forEach((module) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'amplisio-toggle';

      const input = document.createElement('input');
      input.type = 'checkbox';
      input.id = `module-${module.id}`;
      input.checked = module.enabled;
      input.dataset.moduleId = module.id;
      input.setAttribute('role', 'switch');
      input.setAttribute('aria-checked', String(module.enabled));
      input.addEventListener('change', () => {
        input.setAttribute('aria-checked', String(input.checked));
      });

      const label = document.createElement('label');
      label.setAttribute('for', input.id);
      label.textContent = module.name;

      wrapper.appendChild(input);
      wrapper.appendChild(label);
      fieldset.appendChild(wrapper);
    });

    return fieldset;
  };

  const createSequenceField = (sequence, label, name, config = {}) => {
    const { type = 'text', options = [] } = config;
    const field = document.createElement('label');
    field.className = 'amplisio-field amplisio-field--stacked';
    field.textContent = label;

    let input;
    if ('textarea' === type) {
      input = document.createElement('textarea');
    } else if ('select' === type) {
      input = document.createElement('select');
      options.forEach((option) => {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        if (sequence[name] === option) {
          opt.selected = true;
        }
        input.appendChild(opt);
      });
    } else {
      input = document.createElement('input');
      input.type = type;
    }

    if ('checkbox' === type) {
      input.checked = Boolean(sequence[name]);
      input.addEventListener('change', (event) => {
        updateSequenceField(sequence.id, name, event.target.checked);
      });
    } else if ('number' === type) {
      input.value = sequence[name] ?? 0;
      input.addEventListener('input', (event) => {
        const numeric = event.target.valueAsNumber;
        const value = Number.isNaN(numeric) ? 0 : Math.max(0, numeric);
        updateSequenceField(sequence.id, name, value);
      });
    } else if ('select' === type) {
      input.addEventListener('change', (event) => {
        updateSequenceField(sequence.id, name, event.target.value);
      });
    } else {
      input.value = sequence[name] ?? '';
      input.addEventListener('input', (event) => {
        updateSequenceField(sequence.id, name, event.target.value);
      });
    }

    input.setAttribute('aria-label', label);
    input.dataset.sequenceField = `${sequence.id}-${name}`;

    field.appendChild(input);
    return field;
  };

  const renderSequence = (sequence) => {
    const container = document.createElement('article');
    container.className = 'amplisio-sequence';

    const header = document.createElement('header');
    header.className = 'amplisio-sequence__header';
    const title = document.createElement('h4');
    title.textContent = sequence.name || sequence.id;
    header.appendChild(title);
    container.appendChild(header);

    container.appendChild(createSequenceField(sequence, translate('Delay (minutes)'), 'delay', { type: 'number' }));
    container.appendChild(createSequenceField(sequence, translate('Email subject'), 'subject'));

    const bodyField = createSequenceField(sequence, translate('Email body'), 'body', { type: 'textarea' });
    container.appendChild(bodyField);

    const variables = document.createElement('p');
    variables.className = 'amplisio-sequence__variables';
    variables.textContent = translate('Available variables: {{first_name}}, {{cart_link}}, {{coupon}}');
    container.appendChild(variables);

    const couponWrapper = document.createElement('div');
    couponWrapper.className = 'amplisio-sequence__coupon';

    const couponToggle = createSequenceField(sequence, translate('Generate coupon automatically'), 'autoCoupon', { type: 'checkbox' });
    couponWrapper.appendChild(couponToggle);

    couponWrapper.appendChild(
      createSequenceField(sequence, translate('Coupon type'), 'couponType', {
        type: 'select',
        options: ['percent', 'fixed_cart', 'fixed_product'],
      })
    );
    couponWrapper.appendChild(createSequenceField(sequence, translate('Coupon amount'), 'couponAmount', { type: 'number' }));
    couponWrapper.appendChild(
      createSequenceField(sequence, translate('Coupon expiry (days)'), 'couponExpiryDays', { type: 'number' })
    );

    container.appendChild(couponWrapper);

    const actions = document.createElement('div');
    actions.className = 'amplisio-sequence__actions';

    const previewButton = document.createElement('button');
    previewButton.type = 'button';
    previewButton.className = 'amplisio-button amplisio-button--ghost';
    previewButton.textContent = translate('Preview');
    previewButton.addEventListener('click', () => previewSequence(sequence.id));
    actions.appendChild(previewButton);

    const testButton = document.createElement('button');
    testButton.type = 'button';
    testButton.className = 'amplisio-button amplisio-button--ghost';
    testButton.textContent = state.abandoned.sendingTest
      ? translate('Sending…')
      : translate('Send test');
    testButton.disabled = state.abandoned.sendingTest;
    testButton.addEventListener('click', () => sendTest(sequence.id));
    actions.appendChild(testButton);

    container.appendChild(actions);

    return container;
  };

  const renderTimingControls = () => {
    if (!state.abandoned.settings) {
      return document.createDocumentFragment();
    }

    const container = document.createElement('article');
    container.className = 'amplisio-sequence amplisio-sequence--settings';

    const heading = document.createElement('h4');
    heading.textContent = translate('Sequence timing');
    container.appendChild(heading);

    const abandonField = document.createElement('label');
    abandonField.className = 'amplisio-field amplisio-field--stacked';
    abandonField.textContent = translate('Mark abandoned after (minutes)');
    const abandonInput = document.createElement('input');
    abandonInput.type = 'number';
    abandonInput.min = '5';
    abandonInput.value = state.abandoned.settings.abandonAfterMinutes;
    abandonInput.addEventListener('input', (event) => {
      const numeric = event.target.valueAsNumber;
      const value = Number.isNaN(numeric) ? state.abandoned.settings.abandonAfterMinutes : Math.max(5, numeric);
      updateTimingField('abandonAfterMinutes', value);
    });
    abandonField.appendChild(abandonInput);
    container.appendChild(abandonField);

    const expireField = document.createElement('label');
    expireField.className = 'amplisio-field amplisio-field--stacked';
    expireField.textContent = translate('Expire abandoned carts after (days)');
    const expireInput = document.createElement('input');
    expireInput.type = 'number';
    expireInput.min = '1';
    expireInput.value = state.abandoned.settings.expireAfterDays;
    expireInput.addEventListener('input', (event) => {
      const numeric = event.target.valueAsNumber;
      const value = Number.isNaN(numeric) ? state.abandoned.settings.expireAfterDays : Math.max(1, numeric);
      updateTimingField('expireAfterDays', value);
    });
    expireField.appendChild(expireInput);
    container.appendChild(expireField);

    return container;
  };

  const renderAbandonedSequences = () => {
    const panel = document.createElement('section');
    panel.className = 'amplisio-panel';

    const heading = document.createElement('h3');
    heading.textContent = translate('Abandoned cart sequences');
    panel.appendChild(heading);

    if (!isAbandonedEnabled()) {
      const disabled = document.createElement('p');
      disabled.textContent = translate('Enable the abandoned cart module to manage recovery emails.');
      disabled.className = 'amplisio-panel__notice';
      panel.appendChild(disabled);
      return panel;
    }

    if (state.abandoned.loading) {
      const loading = document.createElement('p');
      loading.textContent = translate('Loading sequences…');
      loading.className = 'amplisio-panel__notice';
      panel.appendChild(loading);
      return panel;
    }

    if (!state.abandoned.settings) {
      const empty = document.createElement('p');
      empty.textContent = translate('No sequences configured yet.');
      empty.className = 'amplisio-panel__notice';
      panel.appendChild(empty);
      return panel;
    }

    const form = document.createElement('form');
    form.className = 'amplisio-panel__form';
    form.addEventListener('submit', saveSequences);

    form.appendChild(renderTimingControls());

    state.abandoned.settings.sequences.forEach((sequence) => {
      form.appendChild(renderSequence(sequence));
    });

    const testField = document.createElement('label');
    testField.className = 'amplisio-field amplisio-field--stacked';
    testField.textContent = translate('Test email address');
    const testInput = document.createElement('input');
    testInput.type = 'email';
    testInput.value = state.abandoned.testEmail;
    testInput.placeholder = translate('name@example.com');
    testInput.setAttribute('aria-label', translate('Test email address'));
    testInput.addEventListener('input', (event) => {
      setState(withAbandoned({ testEmail: event.target.value }));
    });
    testField.appendChild(testInput);
    form.appendChild(testField);

    const save = document.createElement('button');
    save.type = 'submit';
    save.className = 'amplisio-button';
    save.textContent = state.abandoned.saving ? translate('Saving…') : translate('Save sequences');
    save.disabled = state.abandoned.saving;
    form.appendChild(save);

    if (state.abandoned.message) {
      const message = document.createElement('p');
      message.className = 'amplisio-panel__message';
      message.textContent = state.abandoned.message;
      form.appendChild(message);
    }

    if (state.abandoned.preview && state.abandoned.preview.loading) {
      const loading = document.createElement('p');
      loading.className = 'amplisio-panel__notice';
      loading.textContent = translate('Generating preview…');
      form.appendChild(loading);
    }

    if (state.abandoned.preview && !state.abandoned.preview.loading) {
      const preview = document.createElement('section');
      preview.className = 'amplisio-preview';

      const subject = document.createElement('h4');
      subject.textContent = `${translate('Subject')}: ${state.abandoned.preview.subject || ''}`;
      preview.appendChild(subject);

      const body = document.createElement('div');
      body.className = 'amplisio-preview__body';
      body.innerHTML = state.abandoned.preview.body || '';
      preview.appendChild(body);

      form.appendChild(preview);
    }

    panel.appendChild(form);

    return panel;
  };

  const render = () => {
    root.innerHTML = '';

    const layout = document.createElement('div');
    layout.className = 'amplisio-dashboard__layout';
    applyThemeVariables(layout);

    const cardsSection = renderCards();
    layout.appendChild(cardsSection);

    const form = document.createElement('form');
    form.className = 'amplisio-form';
    form.addEventListener('submit', updateSettings);

    form.appendChild(renderThemeForm());
    form.appendChild(renderModuleToggles());

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'amplisio-button';
    submit.textContent = state.saving ? translate('Saving…') : translate('Save changes');
    submit.disabled = state.saving;
    form.appendChild(submit);

    layout.appendChild(form);

    if (isAbandonedEnabled()) {
      layout.appendChild(renderAbandonedSequences());
    }

    root.appendChild(layout);
  };

  render();
  loadDashboard();
}
