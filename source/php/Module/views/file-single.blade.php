<div id="{{ $id }}" class="mod-file-browser mod-file-browser--single"
  style="--file-browser-download-icon-url: url('{{ esc_url($downloadIconUrl) }}')">
  @if (empty($file))
    @if (current_user_can('edit_posts'))
      <p class="mod-file-browser__empty">
        {{ __('The selected file is no longer available.', 'modularity-folder-browser') }}</p>
    @endif
  @else
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
    <ol class="mod-file-browser__list">
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
    </ol>
  @endif
</div>
