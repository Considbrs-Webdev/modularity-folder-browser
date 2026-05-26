<div
  id="{{ $id }}"
  class="mod-file-browser"
  data-file-browser
  data-module-id="{{ $moduleId }}"
  data-rest-base-url="{{ $restBaseUrl }}"
  data-show-file-size="{{ $showFileSize ? '1' : '0' }}"
  data-show-modified-date="{{ $showModifiedDate ? '1' : '0' }}"
  data-show-file-type="{{ $showFileType ? '1' : '0' }}">
  @if (empty($roots))
    <p class="mod-file-browser__empty">{{ __('No folders have been selected for this document browser.', 'modularity-folder-browser') }}</p>
  @else
    <ol class="mod-file-browser__list" data-file-browser-list>
      @if (!empty($topFolderName))
        @php
          $topPanelId = $id . '-top-folder';
          $topFileCount = array_sum(array_map(static fn($root) => $root['listing']['counts']['files'] ?? 0, $roots));
        @endphp
        <li class="mod-file-browser__item mod-file-browser__item--folder">
          <button
            class="mod-file-browser__folder-button"
            type="button"
            aria-expanded="{{ $topFolderExpanded ? 'true' : 'false' }}"
            aria-controls="{{ $topPanelId }}"
            data-file-browser-folder
            data-loaded="true">
            <span class="mod-file-browser__row-main">
              <span class="mod-file-browser__chevron" aria-hidden="true"></span>
              <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
              <span class="mod-file-browser__label">{{ $topFolderName }}</span>
            </span>
            <span class="mod-file-browser__meta">
              {{ sprintf(_n('%d folder', '%d folders', count($roots), 'modularity-folder-browser'), count($roots)) }},
              {{ sprintf(_n('%d file', '%d files', $topFileCount, 'modularity-folder-browser'), $topFileCount) }}
            </span>
          </button>

          <ol id="{{ $topPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested" {{ $topFolderExpanded ? '' : 'hidden' }}>
      @endif

      @foreach ($roots as $root)
        @php
          $rootPanelId = $id . '-root-' . $root['index'];
          $rootCounts = $root['listing']['counts'] ?? ['folders' => 0, 'files' => 0];
        @endphp
        <li class="mod-file-browser__item mod-file-browser__item--folder">
          <button
            class="mod-file-browser__folder-button"
            type="button"
            aria-expanded="{{ $root['expanded'] ? 'true' : 'false' }}"
            aria-controls="{{ $rootPanelId }}"
            data-file-browser-folder
            data-root-index="{{ $root['index'] }}"
            data-path=""
            data-loaded="true">
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

          <ol id="{{ $rootPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested" {{ $root['expanded'] ? '' : 'hidden' }}>
            @foreach (($root['listing']['folders'] ?? []) as $folder)
              @php $folderPanelId = $id . '-folder-' . $root['index'] . '-' . md5($folder['path']); @endphp
              <li class="mod-file-browser__item mod-file-browser__item--folder">
                <button
                  class="mod-file-browser__folder-button"
                  type="button"
                  aria-expanded="false"
                  aria-controls="{{ $folderPanelId }}"
                  data-file-browser-folder
                  data-root-index="{{ $root['index'] }}"
                  data-path="{{ esc_attr($folder['path']) }}"
                  data-loaded="false">
                  <span class="mod-file-browser__row-main">
                    <span class="mod-file-browser__chevron" aria-hidden="true"></span>
                    <span class="mod-file-browser__folder-icon" aria-hidden="true"></span>
                    <span class="mod-file-browser__label">{{ $folder['label'] }}</span>
                  </span>
                  @if (!empty($folder['has_children']))
                    <span class="mod-file-browser__meta">{{ __('Contains documents', 'modularity-folder-browser') }}</span>
                  @endif
                </button>
                <ol id="{{ $folderPanelId }}" class="mod-file-browser__list mod-file-browser__list--nested" hidden></ol>
              </li>
            @endforeach

            @foreach (($root['listing']['files'] ?? []) as $file)
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
              @endphp
              <li class="mod-file-browser__item mod-file-browser__item--file">
                <a class="mod-file-browser__file-link" href="{{ esc_url($file['download_url']) }}">
                  <span class="mod-file-browser__file-icon mod-file-browser__file-icon--{{ esc_attr($file['extension']) }}" aria-hidden="true"></span>
                  <span class="mod-file-browser__file-main">
                    <span class="mod-file-browser__file-name">{{ $file['label'] }}</span>
                    @if (!empty($file['type_label']))
                      <span class="mod-file-browser__file-description">{{ $file['type_label'] }}</span>
                    @endif
                  </span>
                  @if (!empty($meta))
                    <span class="mod-file-browser__file-meta">{{ implode(' · ', $meta) }}</span>
                  @endif
                  <span class="mod-file-browser__download">{{ __('Download', 'modularity-folder-browser') }}</span>
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
