(function () {
  const config = window.ModularityFolderBrowser || {};
  const i18n = config.i18n || {};

  const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);

  const createFolderMarkup = (browser, folder, rootIndex) => {
    rootIndex = folder.root_index ?? rootIndex;

    const id = `${browser.id}-folder-${rootIndex}-${Math.random().toString(36).slice(2)}`;
    const label = escapeHtml(folder.label || folder.name);
    const path = escapeHtml(folder.path || '');

    return `
      <li class="mod-file-browser__item mod-file-browser__item--folder">
        <button class="mod-file-browser__folder-button" type="button" aria-expanded="false" aria-controls="${id}" data-file-browser-folder data-root-index="${rootIndex}" data-path="${path}" data-loaded="false">
          <span class="mod-file-browser__row-main">
            <span class="mod-file-browser__chevron" aria-hidden="true"></span>
            <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
            <span class="mod-file-browser__label">${label}</span>
          </span>
          ${folder.has_children ? `<span class="mod-file-browser__meta">${escapeHtml(i18n.folder || 'Folder')}</span>` : ''}
        </button>
        <ol id="${id}" class="mod-file-browser__list mod-file-browser__list--nested" aria-live="polite" hidden></ol>
      </li>
    `;
  };

  const createFileMarkup = (browser, file) => {
    const showFileType = browser.dataset.showFileType === '1';
    const showFileSize = browser.dataset.showFileSize === '1';
    const showModifiedDate = browser.dataset.showModifiedDate === '1';
    const showFileDescription = browser.dataset.showFileDescription === '1';
    const useDownloadIcon = browser.dataset.downloadDisplay === 'icon';
    const meta = [];

    if (showFileType && file.extension) {
      meta.push(String(file.extension).toUpperCase());
    }

    if (showFileSize && file.size_human) {
      meta.push(file.size_human);
    }

    if (showModifiedDate && file.modified_human) {
      meta.push(file.modified_human);
    }

    const label = escapeHtml(file.label || file.name);
    const ext = (file.extension || '').toLowerCase();
    const icons = config.icons || {};
    const iconCategories = config.iconCategories || {};
    const iconUrl = icons[ext] || icons[''] || '';
    const iconCategory = iconCategories[ext] || 'file';
    const downloadLabel = i18n.download || 'Download';
    const downloadFileLabel = (i18n.downloadFile || 'Download %s').replace('%s', file.label || file.name || '');

    return `
      <li class="mod-file-browser__item mod-file-browser__item--file">
        <a class="mod-file-browser__file-link" href="${escapeHtml(file.download_url)}"${useDownloadIcon ? ` aria-label="${escapeHtml(downloadFileLabel)}"` : ''}>
          <span class="mod-file-browser__file-icon" data-icon="${iconCategory}" style="--_icon-url: url(${escapeHtml(iconUrl)})" aria-hidden="true"></span>
          <span class="mod-file-browser__file-main">
            <span class="mod-file-browser__file-name">${label}</span>
            ${showFileDescription && file.type_label ? `<span class="mod-file-browser__file-description">${escapeHtml(file.type_label)}</span>` : ''}
          </span>
          ${meta.length ? `<span class="mod-file-browser__file-meta" aria-hidden="true">${escapeHtml(meta.join(' · '))}</span><span class="screen-reader-text">${escapeHtml(meta.join(', '))}</span>` : ''}
          ${useDownloadIcon ? '<span class="mod-file-browser__download mod-file-browser__download--icon" aria-hidden="true"><span class="mod-file-browser__download-icon" aria-hidden="true"></span></span>' : `<span class="mod-file-browser__download">${escapeHtml(downloadLabel)}</span>`}
        </a>
      </li>
    `;
  };

  const renderListing = (browser, data, target, emptyMessage) => {
    const folders = Array.isArray(data.folders) ? data.folders : [];
    const files = Array.isArray(data.files) ? data.files : [];

    if (!folders.length && !files.length) {
      target.innerHTML = `<li class="mod-file-browser__empty">${escapeHtml(emptyMessage || i18n.empty || 'No documents found.')}</li>`;
      return;
    }

    target.innerHTML = [
      ...folders.map((folder) => createFolderMarkup(browser, folder, data.root_index)),
      ...files.map((file) => createFileMarkup(browser, file)),
    ].join('');
  };

  const loadFolder = async (browser, button, target) => {
    const moduleId = browser.dataset.moduleId;
    const rootIndex = button.dataset.rootIndex;
    const path = button.dataset.path || '';
    const instanceToken = browser.dataset.instanceToken || '';
    const restBaseUrl = browser.dataset.restBaseUrl || config.restUrl || '/wp-json/modularity-file-browser/v1/';
    const params = new URLSearchParams({ path });

    if (instanceToken) {
      params.set('instance_token', instanceToken);
    }

    const url = `${restBaseUrl.replace(/\/$/, '')}/modules/${moduleId}/roots/${rootIndex}/tree?${params.toString()}`;

    target.innerHTML = `<li class="mod-file-browser__loading">${escapeHtml(i18n.loading || 'Loading folder contents.')}</li>`;

    const response = await fetch(url, { credentials: 'same-origin' });

    if (!response.ok) {
      throw new Error('Folder request failed');
    }

    renderListing(browser, await response.json(), target);
    button.dataset.loaded = 'true';
  };

  const searchTimers = new WeakMap();
  const searchRequests = new WeakMap();
  const originalListings = new WeakMap();

  const getRestBaseUrl = (browser) => browser.dataset.restBaseUrl || config.restUrl || '/wp-json/modularity-file-browser/v1/';

  const setSearchStatus = (browser, message) => {
    const status = browser.querySelector('[data-file-browser-search-status]');

    if (status) {
      status.textContent = message ? ` - ${message}` : '';
    }
  };

  const setSearchBusy = (target, busy) => {
    if (busy) {
      target.setAttribute('aria-busy', 'true');
      return;
    }

    target.removeAttribute('aria-busy');
  };

  const restoreOriginalListing = (browser, target) => {
    const original = originalListings.get(browser);

    if (typeof original === 'string') {
      target.innerHTML = original;
    }

    setSearchBusy(target, false);
    setSearchStatus(browser, '');
  };

  const performSearch = async (input) => {
    const browser = input.closest('[data-file-browser]');
    const target = browser ? browser.querySelector('[data-file-browser-list]') : null;

    if (!browser || !target) {
      return;
    }

    if (!originalListings.has(browser)) {
      originalListings.set(browser, target.innerHTML);
    }

    const previousRequest = searchRequests.get(browser);

    if (previousRequest) {
      previousRequest.abort();
    }

    const query = input.value.trim();

    if (query === '') {
      restoreOriginalListing(browser, target);
      return;
    }

    const request = new AbortController();
    searchRequests.set(browser, request);
    setSearchBusy(target, true);
    setSearchStatus(browser, i18n.searching || 'Searching documents.');
    target.innerHTML = `<li class="mod-file-browser__loading">${escapeHtml(i18n.searching || 'Searching documents.')}</li>`;

    try {
      const moduleId = browser.dataset.moduleId;
      const restBaseUrl = getRestBaseUrl(browser);
      const params = new URLSearchParams({ query });
      const instanceToken = browser.dataset.instanceToken || '';

      if (instanceToken) {
        params.set('instance_token', instanceToken);
      }

      const url = `${restBaseUrl.replace(/\/$/, '')}/modules/${moduleId}/search?${params.toString()}`;
      const response = await fetch(url, {
        credentials: 'same-origin',
        signal: request.signal,
      });

      if (!response.ok) {
        throw new Error('Search request failed');
      }

      const data = await response.json();
      const resultCount = (data.counts?.folders || 0) + (data.counts?.files || 0);
      const resultMessage = (i18n.searchResults || '%d matching files or folders found.').replace('%d', resultCount);

      renderListing(browser, data, target, i18n.searchEmpty || 'No matching files or folders found.');
      setSearchStatus(browser, resultCount > 0 ? resultMessage : (i18n.searchEmpty || 'No matching files or folders found.'));
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      target.innerHTML = `<li class="mod-file-browser__error">${escapeHtml(i18n.searchError || 'The search could not be completed.')}</li>`;
      setSearchStatus(browser, i18n.searchError || 'The search could not be completed.');
    } finally {
      if (searchRequests.get(browser) === request) {
        searchRequests.delete(browser);
        setSearchBusy(target, false);
      }
    }
  };

  document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-file-browser-search]');

    if (!input) {
      return;
    }

    const browser = input.closest('[data-file-browser]');

    if (!browser) {
      return;
    }

    const previousTimer = searchTimers.get(browser);

    if (previousTimer) {
      window.clearTimeout(previousTimer);
    }

    if (input.value.trim() === '') {
      performSearch(input);
      return;
    }

    const debounce = Number.parseInt(browser.dataset.searchDebounce || '500', 10);

    searchTimers.set(browser, window.setTimeout(() => {
      performSearch(input);
    }, Number.isNaN(debounce) ? 500 : Math.max(0, debounce)));
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-file-browser-folder]');

    if (!button) {
      return;
    }

    const browser = button.closest('[data-file-browser]');
    const target = document.getElementById(button.getAttribute('aria-controls'));
    const expanded = button.getAttribute('aria-expanded') === 'true';

    if (!browser || !target) {
      return;
    }

    button.setAttribute('aria-expanded', String(!expanded));
    target.hidden = expanded;

    if (!expanded && button.dataset.loaded === 'false') {
      try {
        await loadFolder(browser, button, target);
      } catch (error) {
        target.innerHTML = `<li class="mod-file-browser__error">${escapeHtml(i18n.error || 'This folder could not be loaded.')}</li>`;
      }
    }
  });
})();
