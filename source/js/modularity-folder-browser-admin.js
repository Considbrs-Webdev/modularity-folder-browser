(function () {
  const config = window.ModularityFolderBrowserAdmin || {};
  const i18n = config.i18n || {};

  const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);

  const fieldRow = (input) => input.closest('.acf-row') || input.closest('.acf-fields') || document;
  const sourceSelect = (input) => fieldRow(input).querySelector('[data-name="source"] select');

  const requestFolders = async (source, path) => {
    const url = new URL(config.restUrl, window.location.origin);
    url.searchParams.set('source', source);
    url.searchParams.set('path', path || '');

    const response = await fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce || '',
      },
    });

    if (!response.ok) {
      throw new Error('Folder picker request failed');
    }

    return response.json();
  };

  const renderPicker = async (input, panel, path) => {
    const select = sourceSelect(input);
    const source = select ? select.value : '';

    if (!source) {
      panel.innerHTML = `<p class="mod-file-browser-admin-picker__message">${escapeHtml(i18n.error || 'Folders could not be loaded.')}</p>`;
      return;
    }

    panel.innerHTML = `<p class="mod-file-browser-admin-picker__message">${escapeHtml(i18n.loading || 'Loading folders.')}</p>`;

    try {
      const data = await requestFolders(source, path);
      const folders = Array.isArray(data.folders) ? data.folders : [];
      const currentPath = data.path || '';

      panel.innerHTML = `
        <div class="mod-file-browser-admin-picker__toolbar">
          <button type="button" class="button" data-picker-choose>${escapeHtml(i18n.choose || 'Choose this folder')}</button>
          ${currentPath ? `<button type="button" class="button-link" data-picker-path="${escapeHtml(data.parent || '')}">${escapeHtml('..')}</button>` : ''}
          <span class="mod-file-browser-admin-picker__path">${escapeHtml(currentPath || i18n.root || 'Source root')}</span>
        </div>
        ${folders.length ? `
          <ul class="mod-file-browser-admin-picker__list">
            ${folders.map((folder) => `
              <li>
                <button type="button" class="button-link" data-picker-path="${escapeHtml(folder.path)}">
                  ${escapeHtml(folder.name)}
                </button>
              </li>
            `).join('')}
          </ul>
        ` : `<p class="mod-file-browser-admin-picker__message">${escapeHtml(i18n.empty || 'No folders found.')}</p>`}
      `;

      panel.querySelectorAll('[data-picker-path]').forEach((button) => {
        button.addEventListener('click', () => renderPicker(input, panel, button.dataset.pickerPath || ''));
      });

      const choose = panel.querySelector('[data-picker-choose]');
      if (choose) {
        choose.addEventListener('click', () => {
          input.value = currentPath;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
    } catch (error) {
      panel.innerHTML = `<p class="mod-file-browser-admin-picker__message">${escapeHtml(i18n.error || 'Folders could not be loaded.')}</p>`;
    }
  };

  const initField = (field) => {
    const input = field.querySelector('input[type="text"]');

    if (!input || input.dataset.folderBrowserPickerReady) {
      return;
    }

    input.dataset.folderBrowserPickerReady = 'true';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button mod-file-browser-admin-picker__toggle';
    button.textContent = i18n.browse || 'Browse folders';

    const panel = document.createElement('div');
    panel.className = 'mod-file-browser-admin-picker';
    panel.hidden = true;

    input.insertAdjacentElement('afterend', button);
    button.insertAdjacentElement('afterend', panel);

    button.addEventListener('click', () => {
      panel.hidden = !panel.hidden;

      if (!panel.hidden) {
        renderPicker(input, panel, input.value || '');
      }
    });

    const select = sourceSelect(input);
    if (select) {
      select.addEventListener('change', () => {
        input.value = '';
        panel.innerHTML = '';
      });
    }
  };

  const getRootElement = (root) => {
    if (!root) {
      return document;
    }

    if (root.jquery) {
      return root[0] || document;
    }

    return root.nodeType === 1 || root.nodeType === 9 ? root : document;
  };

  const init = (root) => {
    getRootElement(root).querySelectorAll('.mod-file-browser-folder-field').forEach(initField);
  };

  if (window.acf) {
    window.acf.addAction('ready', init);
    window.acf.addAction('append', init);
  } else {
    document.addEventListener('DOMContentLoaded', () => init(document));
  }
})();
