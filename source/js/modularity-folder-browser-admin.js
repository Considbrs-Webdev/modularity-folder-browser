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

  const getRootElement = (root) => {
    if (!root) {
      return document;
    }

    if (root.jquery) {
      return root[0] || document;
    }

    return root.nodeType === 1 || root.nodeType === 9 ? root : document;
  };

  const rowFor = (element) => element.closest('tr.acf-row');
  const sourceSelectFor = (row) => row?.querySelector('[data-name="source"] select');
  const folderInputFor = (row) => row?.querySelector('[data-name="folder"] textarea, [data-name="folder"] input[type="text"]');

  const parseFolderValue = (value) => {
    const trimmed = String(value || '').trim();

    if (!trimmed) {
      return [''];
    }

    try {
      const decoded = JSON.parse(trimmed);

      if (Array.isArray(decoded)) {
        return decoded.map((path) => String(path));
      }
    } catch (error) {
      return [trimmed];
    }

    return [trimmed];
  };

  const writeFolderValue = (input, paths) => {
    input.value = JSON.stringify(Array.from(new Set(paths)));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    if (window.jQuery) {
      window.jQuery(input).trigger('change');
    }
  };

  const sourceLabel = (row) => sourceSelectFor(row)?.selectedOptions?.[0]?.textContent?.trim() || '';

  const labelForPath = (path, row) => {
    if (!path) {
      return (row && sourceLabel(row)) || i18n.root || 'Source root';
    }

    return path.split('/').filter(Boolean).pop() || path;
  };

  const requestFolders = async (source, path, includeFiles = false) => {
    const url = new URL(config.restUrl, window.location.origin);
    url.searchParams.set('source', source);
    url.searchParams.set('path', path || '');

    if (includeFiles) {
      url.searchParams.set('include_files', '1');
    }

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

  const updateSummary = (field) => {
    const row = rowFor(field);
    const input = folderInputFor(row);
    const paths = parseFolderValue(input?.value || '');
    const summary = field.querySelector('[data-folder-browser-summary]');

    if (!summary) {
      return;
    }

    if (!paths.length) {
      summary.innerHTML = `<span class="mod-file-browser-selected-folder__empty">${escapeHtml(i18n.emptySelection || 'No folders selected')}</span>`;
      return;
    }

    summary.innerHTML = `
      <ul class="mod-file-browser-selected-folder__list">
        ${paths.map((path) => {
          const label = labelForPath(path, row);
          const showPath = path && path !== label;
          return `<li>${escapeHtml(label)}${showPath ? `<small>${escapeHtml(path)}</small>` : ''}</li>`;
        }).join('')}
      </ul>
    `;
  };

  const selectedSetFor = (row) => new Set(parseFolderValue(folderInputFor(row)?.value || ''));

  const renderTree = async (dialog, source, selected, path, container, level) => {
    container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.loading || 'Loading folders.')}</li>`;

    try {
      const data = await requestFolders(source, path);
      const folders = Array.isArray(data.folders) ? data.folders : [];

      if (!folders.length) {
        container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.empty || 'No folders found.')}</li>`;
        return;
      }

      container.innerHTML = folders.map((folder) => {
        const checked = selected.has(folder.path) ? 'checked' : '';
        const spacer = Math.max(0, level) * 18;

        return `
          <li class="mod-file-browser-tree__item" data-tree-item data-path="${escapeHtml(folder.path)}" data-label="${escapeHtml(folder.name)}">
            <div class="mod-file-browser-tree__row" style="--tree-indent: ${spacer}px">
              <button class="mod-file-browser-tree__toggle" type="button" ${folder.has_children ? '' : 'disabled'} aria-expanded="false">
                <span class="screen-reader-text">${escapeHtml(i18n.expand || 'Expand folder')}</span>
              </button>
              <label class="mod-file-browser-tree__check">
                <input type="checkbox" value="${escapeHtml(folder.path)}" ${checked}>
                <span>${escapeHtml(folder.name)}</span>
              </label>
            </div>
            <ol class="mod-file-browser-tree__children" hidden></ol>
          </li>
        `;
      }).join('');

      container.querySelectorAll('[data-tree-item]').forEach((item) => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const toggle = item.querySelector('.mod-file-browser-tree__toggle');
        const childList = item.querySelector('.mod-file-browser-tree__children');
        const folderPath = item.dataset.path || '';

        checkbox.addEventListener('change', () => {
          if (checkbox.checked) {
            selected.add(folderPath);
          } else {
            selected.delete(folderPath);
          }

          updateSelectedCount(dialog, selected);
        });

        toggle.addEventListener('click', async () => {
          const expanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', String(!expanded));
          childList.hidden = expanded;

          if (!expanded && childList.dataset.loaded !== 'true') {
            await renderTree(dialog, source, selected, folderPath, childList, level + 1);
            childList.dataset.loaded = 'true';
          }
        });
      });
    } catch (error) {
      container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.error || 'Folders could not be loaded.')}</li>`;
    }
  };

  const updateSelectedCount = (dialog, selected) => {
    const count = dialog.querySelector('[data-folder-browser-selected-count]');

    if (count) {
      count.textContent = `${selected.size} ${i18n.selectedCount || 'selected'}`;
    }
  };

  const renderRootSelection = (dialog, row, selected) => {
    const rootCheckbox = dialog.querySelector('[data-folder-browser-root-checkbox]');

    if (!rootCheckbox) {
      return;
    }

    rootCheckbox.checked = selected.has('');
    rootCheckbox.nextElementSibling.textContent = sourceLabel(row) || i18n.root || 'Source root';
    rootCheckbox.onchange = () => {
      if (rootCheckbox.checked) {
        selected.add('');
      } else {
        selected.delete('');
      }

      updateSelectedCount(dialog, selected);
    };
  };

  const openModal = async (field, dialog) => {
    const row = rowFor(field);
    const source = sourceSelectFor(row)?.value || '';
    const input = folderInputFor(row);
    const selected = selectedSetFor(row);
    const sourceLabelText = sourceLabel(row);
    const sourceName = dialog.querySelector('[data-folder-browser-source-name]');
    const tree = dialog.querySelector('[data-folder-browser-tree]');
    const applyButton = dialog.querySelector('[data-folder-browser-apply]');
    const closeButtons = dialog.querySelectorAll('[data-folder-browser-close]');

    if (!source || !input) {
      return;
    }

    sourceName.textContent = sourceLabelText || source;
    renderRootSelection(dialog, row, selected);
    updateSelectedCount(dialog, selected);

    applyButton.onclick = () => {
      writeFolderValue(input, Array.from(selected));
      updateSummary(field);
      dialog.hidden = true;
    };

    closeButtons.forEach((button) => {
      button.onclick = () => {
        dialog.hidden = true;
      };
    });

    dialog.hidden = false;
    await renderTree(dialog, source, selected, '', tree, 0);
  };

  const fieldsFor = (field) => field.closest('.acf-fields');
  const fileSourceSelectFor = (fields) => fields?.querySelector('[data-name="file_source"] select');
  const fileInputFor = (field) => field.querySelector('input[type="text"]');

  const writeFileValue = (input, path) => {
    input.value = path;
    input.dispatchEvent(new Event('change', { bubbles: true }));

    if (window.jQuery) {
      window.jQuery(input).trigger('change');
    }
  };

  const updateFileSummary = (field) => {
    const summary = field.querySelector('[data-folder-browser-summary]');
    const value = fileInputFor(field)?.value || '';

    if (!summary) {
      return;
    }

    if (!value) {
      summary.innerHTML = `<span class="mod-file-browser-selected-folder__empty">${escapeHtml(i18n.emptyFileSelection || 'No file selected')}</span>`;
      return;
    }

    const label = labelForPath(value);
    const showPath = value !== label;
    summary.innerHTML = `<ul class="mod-file-browser-selected-folder__list"><li>${escapeHtml(label)}${showPath ? `<small>${escapeHtml(value)}</small>` : ''}</li></ul>`;
  };

  const renderFileTree = async (state, source, path, container, level) => {
    container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.loadingFiles || 'Loading files.')}</li>`;

    try {
      const data = await requestFolders(source, path, true);
      const folders = Array.isArray(data.folders) ? data.folders : [];
      const files = Array.isArray(data.files) ? data.files : [];

      if (!folders.length && !files.length) {
        container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.noFiles || 'No files found.')}</li>`;
        return;
      }

      const indent = Math.max(0, level) * 18;

      container.innerHTML = folders.map((folder) => `
        <li class="mod-file-browser-tree__item" data-tree-folder data-path="${escapeHtml(folder.path)}">
          <div class="mod-file-browser-tree__row" style="--tree-indent: ${indent}px">
            <button class="mod-file-browser-tree__toggle" type="button" aria-expanded="false">
              <span class="screen-reader-text">${escapeHtml(i18n.expand || 'Expand folder')}</span>
            </button>
            <span class="mod-file-browser-tree__folder-name">${escapeHtml(folder.name)}</span>
          </div>
          <ol class="mod-file-browser-tree__children" hidden></ol>
        </li>
      `).join('') + files.map((file) => `
        <li class="mod-file-browser-tree__item" data-tree-file>
          <div class="mod-file-browser-tree__row" style="--tree-indent: ${indent}px">
            <span class="mod-file-browser-tree__toggle" aria-hidden="true"></span>
            <label class="mod-file-browser-tree__check">
              <input type="radio" name="mod-file-browser-file" value="${escapeHtml(file.path)}" ${state.selected === file.path ? 'checked' : ''}>
              <span>${escapeHtml(file.name)}</span>
            </label>
          </div>
        </li>
      `).join('');

      container.querySelectorAll(':scope > [data-tree-folder]').forEach((item) => {
        const toggle = item.querySelector('.mod-file-browser-tree__toggle');
        const childList = item.querySelector('.mod-file-browser-tree__children');

        toggle.addEventListener('click', async () => {
          const expanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', String(!expanded));
          childList.hidden = expanded;

          if (!expanded && childList.dataset.loaded !== 'true') {
            await renderFileTree(state, source, item.dataset.path || '', childList, level + 1);
            childList.dataset.loaded = 'true';
          }
        });
      });

      container.querySelectorAll(':scope > [data-tree-file] input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
          state.selected = radio.value;
          const count = state.dialog.querySelector('[data-folder-browser-selected-count]');

          if (count) {
            count.textContent = labelForPath(state.selected);
          }
        });
      });
    } catch (error) {
      container.innerHTML = `<li class="mod-file-browser-tree__status">${escapeHtml(i18n.errorFiles || 'Files could not be loaded.')}</li>`;
    }
  };

  const openFileModal = async (field, dialog) => {
    const fields = fieldsFor(field);
    const sourceSelect = fileSourceSelectFor(fields);
    const source = sourceSelect?.value || '';
    const input = fileInputFor(field);

    if (!source || !input) {
      return;
    }

    const state = { selected: input.value || '', dialog };
    const tree = dialog.querySelector('[data-folder-browser-tree]');
    const count = dialog.querySelector('[data-folder-browser-selected-count]');

    dialog.querySelector('[data-folder-browser-source-name]').textContent = sourceSelect.selectedOptions?.[0]?.textContent?.trim() || source;
    count.textContent = state.selected ? labelForPath(state.selected) : '';

    dialog.querySelector('[data-folder-browser-apply]').onclick = () => {
      writeFileValue(input, state.selected);
      updateFileSummary(field);
      dialog.hidden = true;
    };

    dialog.querySelectorAll('[data-folder-browser-close]').forEach((button) => {
      button.onclick = () => {
        dialog.hidden = true;
      };
    });

    dialog.hidden = false;
    await renderFileTree(state, source, '', tree, 0);
  };

  const initSelectedFileField = (field) => {
    if (field.dataset.folderBrowserModalReady) {
      updateFileSummary(field);
      return;
    }

    field.dataset.folderBrowserModalReady = 'true';

    const container = field.querySelector('.acf-input');
    const button = document.createElement('button');
    const summary = document.createElement('div');
    const dialog = createDialog('file');
    const sourceSelect = fileSourceSelectFor(fieldsFor(field));
    const input = fileInputFor(field);

    button.type = 'button';
    button.className = 'button button-secondary mod-file-browser-select-folders';
    button.textContent = i18n.selectFile || 'Select file';
    button.addEventListener('click', () => openFileModal(field, dialog));

    summary.className = 'mod-file-browser-selected-folder';
    summary.setAttribute('data-folder-browser-summary', '');

    container?.insertAdjacentElement('afterbegin', summary);
    summary.insertAdjacentElement('beforebegin', button);

    sourceSelect?.addEventListener('change', () => {
      if (input) {
        writeFileValue(input, '');
      }
      updateFileSummary(field);
    });
    input?.addEventListener('change', () => updateFileSummary(field));

    updateFileSummary(field);
  };

  const createDialog = (mode = 'folders') => {
    const dialog = document.createElement('div');
    dialog.className = 'mod-file-browser-modal';
    dialog.hidden = true;
    const isFile = mode === 'file';
    dialog.innerHTML = `
      <div class="mod-file-browser-modal__backdrop" data-folder-browser-close></div>
      <div class="mod-file-browser-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mod-file-browser-modal-title">
        <div class="mod-file-browser-modal__header">
          <h2 id="mod-file-browser-modal-title">${escapeHtml(isFile ? (i18n.fileDialogTitle || 'Select a file to show') : (i18n.dialogTitle || 'Select folders to expose'))}</h2>
          <button type="button" class="button-link mod-file-browser-modal__close" data-folder-browser-close aria-label="${escapeHtml(i18n.cancel || 'Cancel')}">&times;</button>
        </div>
        <div class="mod-file-browser-modal__controls">
          <p><strong>${escapeHtml(i18n.source || 'Source')}</strong> <span data-folder-browser-source-name></span></p>
          <span class="mod-file-browser-modal__count" data-folder-browser-selected-count></span>
        </div>
        <div class="mod-file-browser-tree">
          ${isFile ? '' : `<label class="mod-file-browser-tree__root">
            <input type="checkbox" data-folder-browser-root-checkbox>
            <span>${escapeHtml(i18n.root || 'Source root')}</span>
          </label>`}
          <ol class="mod-file-browser-tree__list" data-folder-browser-tree></ol>
        </div>
        <div class="mod-file-browser-modal__footer">
          <button type="button" class="button" data-folder-browser-close>${escapeHtml(i18n.cancel || 'Cancel')}</button>
          <button type="button" class="button button-primary" data-folder-browser-apply>${escapeHtml(i18n.apply || 'OK')}</button>
        </div>
      </div>
    `;
    document.body.appendChild(dialog);

    return dialog;
  };

  const initSelectedFoldersField = (field) => {
    if (field.dataset.folderBrowserModalReady) {
      updateSummary(field);
      return;
    }

    field.dataset.folderBrowserModalReady = 'true';

    const input = field.querySelector('.acf-input');
    const button = document.createElement('button');
    const summary = document.createElement('div');
    const dialog = createDialog();
    const row = rowFor(field);
    const source = sourceSelectFor(row);
    const folderInput = folderInputFor(row);

    button.type = 'button';
    button.className = 'button button-secondary mod-file-browser-select-folders';
    button.textContent = i18n.selectFolders || 'Select folders';
    button.addEventListener('click', () => openModal(field, dialog));

    summary.className = 'mod-file-browser-selected-folder';
    summary.setAttribute('data-folder-browser-summary', '');

    input?.insertAdjacentElement('afterbegin', summary);
    summary.insertAdjacentElement('beforebegin', button);

    source?.addEventListener('change', () => {
      if (folderInput) {
        writeFolderValue(folderInput, ['']);
      }
      updateSummary(field);
    });
    folderInput?.addEventListener('change', () => updateSummary(field));

    updateSummary(field);
  };

  const init = (root) => {
    const scope = getRootElement(root);

    scope.querySelectorAll('.mod-file-browser-selected-folders-field').forEach(initSelectedFoldersField);
    scope.querySelectorAll('.mod-file-browser-selected-file-field').forEach(initSelectedFileField);
  };

  if (window.acf) {
    window.acf.addAction('ready', init);
    window.acf.addAction('append', init);
  } else {
    document.addEventListener('DOMContentLoaded', () => init(document));
  }
})();
