<div id="{{ $id }}" class="mod-file-browser"
  style="--file-browser-folder-icon-url: url('{{ esc_url($folderIconUrl) }}'); --file-browser-download-icon-url: url('{{ esc_url($downloadIconUrl) }}')"
  data-file-browser data-module-id="{{ $moduleId }}" data-rest-base-url="{{ $restBaseUrl }}"
  data-show-file-size="{{ $showFileSize ? '1' : '0' }}" data-show-modified-date="{{ $showModifiedDate ? '1' : '0' }}"
  data-show-file-type="{{ $showFileType ? '1' : '0' }}"
  data-show-file-description="{{ $showFileDescription ? '1' : '0' }}"
  data-download-display="{{ esc_attr($downloadDisplay) }}">
  @if (empty($roots))
    <p class="mod-file-browser__empty">
      {{ __('No folders have been selected for this document browser.', 'modularity-folder-browser') }}</p>
  @else
    @php
      $attachSingleRootToTopFolder = !empty($topFolderName) && count($roots) === 1 && !empty($roots[0]['attachListingToTopFolder']);
      $rootsToRender = $attachSingleRootToTopFolder ? [] : $roots;
    @endphp
    <ol class="mod-file-browser__list" data-file-browser-list>
      @if (!empty($topFolderName))
        @php
          $topPanelId = $id . '-top-folder';
          $topRoot = $attachSingleRootToTopFolder ? $roots[0] : null;
          $topCounts = $topRoot
              ? ($topRoot['listing']['counts'] ?? ['folders' => 0, 'files' => 0])
              : [
                  'folders' => count($roots),
                  'files' => array_sum(array_map(static fn($root) => $root['listing']['counts']['files'] ?? 0, $roots)),
              ];
        @endphp
        <li class="mod-file-browser__item mod-file-browser__item--folder">
          <button class="mod-file-browser__folder-button" type="button"
            aria-expanded="{{ $topFolderExpanded ? 'true' : 'false' }}" aria-controls="{{ $topPanelId }}"
            data-file-browser-folder data-loaded="true">
            <span class="mod-file-browser__row-main">
              <span class="mod-file-browser__chevron" aria-hidden="true"></span>
              <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
              <span class="mod-file-browser__label">{{ $topFolderName }}</span>
            </span>
            <span class="mod-file-browser__meta">
              {{ sprintf(_n('%d folder', '%d folders', $topCounts['folders'], 'modularity-folder-browser'), $topCounts['folders']) }},
              {{ sprintf(_n('%d file', '%d files', $topCounts['files'], 'modularity-folder-browser'), $topCounts['files']) }}
            </span>
          </button>

          <ol id="{{ $topPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested" aria-live="polite"
            {{ $topFolderExpanded ? '' : 'hidden' }}>
            @if ($topRoot)
              @foreach ($topRoot['listing']['folders'] ?? [] as $folder)
                @php $folderPanelId = $id . '-folder-' . $topRoot['index'] . '-' . md5($folder['path']); @endphp
                <li class="mod-file-browser__item mod-file-browser__item--folder">
                  <button class="mod-file-browser__folder-button" type="button" aria-expanded="false"
                    aria-controls="{{ $folderPanelId }}" data-file-browser-folder data-root-index="{{ $topRoot['index'] }}"
                    data-path="{{ esc_attr($folder['path']) }}" data-loaded="false">
                    <span class="mod-file-browser__row-main">
                      <span class="mod-file-browser__chevron" aria-hidden="true"></span>
                      <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
                      <span class="mod-file-browser__label">{{ $folder['label'] }}</span>
                    </span>
                    @if (!empty($folder['has_children']))
                      <span
                        class="mod-file-browser__meta">{{ __('Contains documents', 'modularity-folder-browser') }}</span>
                    @endif
                  </button>
                  <ol id="{{ $folderPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested"
                    aria-live="polite" hidden>
                  </ol>
                </li>
              @endforeach

              @foreach ($topRoot['listing']['files'] ?? [] as $file)
                @php
                  $meta = [];
                  if ($showFileType && !empty($file['extension'])) {
                      $meta[] = strtoupper($file['extension']);
                  }
                  if ($showFileSize && !empty($file['size_human'])) {
                      $meta[] = $file['size_human'];
                  }
                  if ($showModifiedDate && !empty($file['modified_human'])) {
                      $meta[] = sprintf(__('Updated %s', 'modularity-folder-browser'), $file['modified_human']);
                  }
                  $downloadAriaLabel = sprintf(
                      /* translators: %s: file name. */
                      __('Download %s', 'modularity-folder-browser'),
                      $file['label'],
                  );
                @endphp
                <li class="mod-file-browser__item mod-file-browser__item--file">
                  <a class="mod-file-browser__file-link" href="{!! esc_url($file['download_url']) !!}"
                    @if ($downloadDisplay === 'icon') aria-label="{{ esc_attr($downloadAriaLabel) }}" @endif>
                    <span class="mod-file-browser__file-icon"
                      data-icon="{{ \ModularityFolderBrowser\Helper\IconResolver::getCategory($file['extension'] ?? '') }}"
                      style="--_icon-url: url('{{ esc_url(\ModularityFolderBrowser\Helper\IconResolver::getUrl($file['extension'] ?? '')) }}')"
                      aria-hidden="true"></span>
                    <span class="mod-file-browser__file-main">
                      <span class="mod-file-browser__file-name">{{ $file['label'] }}</span>
                      @if ($showFileDescription && !empty($file['type_label']))
                        <span class="mod-file-browser__file-description">{{ $file['type_label'] }}</span>
                      @endif
                    </span>
                    @if (!empty($meta))
                      <span class="mod-file-browser__file-meta" aria-hidden="true">{{ implode(' · ', $meta) }}</span>
                      <span class="screen-reader-text">{{ implode(', ', $meta) }}</span>
                    @endif
                    @if ($downloadDisplay === 'icon')
                      <span class="mod-file-browser__download mod-file-browser__download--icon" aria-hidden="true">
                        <span class="mod-file-browser__download-icon" aria-hidden="true"></span>
                      </span>
                    @else
                      <span class="mod-file-browser__download">{{ __('Download', 'modularity-folder-browser') }}</span>
                    @endif
                  </a>
                </li>
              @endforeach
            @endif
      @endif

      @foreach ($rootsToRender as $root)
        @php
          $rootPanelId = $id . '-root-' . $root['index'];
          $rootCounts = $root['listing']['counts'] ?? ['folders' => 0, 'files' => 0];
        @endphp
        <li class="mod-file-browser__item mod-file-browser__item--folder">
          <button class="mod-file-browser__folder-button" type="button"
            aria-expanded="{{ $root['expanded'] ? 'true' : 'false' }}" aria-controls="{{ $rootPanelId }}"
            data-file-browser-folder data-root-index="{{ $root['index'] }}" data-path="" data-loaded="true">
            <span class="mod-file-browser__row-main">
              <span class="mod-file-browser__chevron" aria-hidden="true"></span>
              <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
              <span class="mod-file-browser__label">{{ $root['label'] }}</span>
            </span>
            <span class="mod-file-browser__meta">
              {{ sprintf(_n('%d folder', '%d folders', $rootCounts['folders'], 'modularity-folder-browser'), $rootCounts['folders']) }},
              {{ sprintf(_n('%d file', '%d files', $rootCounts['files'], 'modularity-folder-browser'), $rootCounts['files']) }}
            </span>
          </button>

          <ol id="{{ $rootPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested" aria-live="polite"
            {{ $root['expanded'] ? '' : 'hidden' }}>
            @foreach ($root['listing']['folders'] ?? [] as $folder)
              @php $folderPanelId = $id . '-folder-' . $root['index'] . '-' . md5($folder['path']); @endphp
              <li class="mod-file-browser__item mod-file-browser__item--folder">
                <button class="mod-file-browser__folder-button" type="button" aria-expanded="false"
                  aria-controls="{{ $folderPanelId }}" data-file-browser-folder data-root-index="{{ $root['index'] }}"
                  data-path="{{ esc_attr($folder['path']) }}" data-loaded="false">
                  <span class="mod-file-browser__row-main">
                    <span class="mod-file-browser__chevron" aria-hidden="true"></span>
                    <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
                    <span class="mod-file-browser__label">{{ $folder['label'] }}</span>
                  </span>
                  @if (!empty($folder['has_children']))
                    <span
                      class="mod-file-browser__meta">{{ __('Contains documents', 'modularity-folder-browser') }}</span>
                  @endif
                </button>
                <ol id="{{ $folderPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested"
                  aria-live="polite" hidden>
                </ol>
              </li>
            @endforeach

            @foreach ($root['listing']['files'] ?? [] as $file)
              @php
                $meta = [];
                if ($showFileType && !empty($file['extension'])) {
                    $meta[] = strtoupper($file['extension']);
                }
                if ($showFileSize && !empty($file['size_human'])) {
                    $meta[] = $file['size_human'];
                }
                if ($showModifiedDate && !empty($file['modified_human'])) {
                    $meta[] = sprintf(__('Updated %s', 'modularity-folder-browser'), $file['modified_human']);
                }
                $downloadAriaLabel = sprintf(
                    /* translators: %s: file name. */
                    __('Download %s', 'modularity-folder-browser'),
                    $file['label'],
                );
              @endphp
              <li class="mod-file-browser__item mod-file-browser__item--file">
                <a class="mod-file-browser__file-link" href="{!! esc_url($file['download_url']) !!}"
                  @if ($downloadDisplay === 'icon') aria-label="{{ esc_attr($downloadAriaLabel) }}" @endif>
                  <span class="mod-file-browser__file-icon"
                    data-icon="{{ \ModularityFolderBrowser\Helper\IconResolver::getCategory($file['extension'] ?? '') }}"
                    style="--_icon-url: url('{{ esc_url(\ModularityFolderBrowser\Helper\IconResolver::getUrl($file['extension'] ?? '')) }}')"
                    aria-hidden="true"></span>
                  <span class="mod-file-browser__file-main">
                    <span class="mod-file-browser__file-name">{{ $file['label'] }}</span>
                    @if ($showFileDescription && !empty($file['type_label']))
                      <span class="mod-file-browser__file-description">{{ $file['type_label'] }}</span>
                    @endif
                  </span>
                  @if (!empty($meta))
                    <span class="mod-file-browser__file-meta" aria-hidden="true">{{ implode(' · ', $meta) }}</span>
                    <span class="screen-reader-text">{{ implode(', ', $meta) }}</span>
                  @endif
                  @if ($downloadDisplay === 'icon')
                    <span class="mod-file-browser__download mod-file-browser__download--icon" aria-hidden="true">
                      <span class="mod-file-browser__download-icon" aria-hidden="true"></span>
                    </span>
                  @else
                    <span class="mod-file-browser__download">{{ __('Download', 'modularity-folder-browser') }}</span>
                  @endif
                </a>
              </li>
            @endforeach
          </ol>
        </li>
      @endforeach

      @if (!empty($topFolderName))
    </ol>
    </li>
  @endif
  </ol>
  @endif
</div>
