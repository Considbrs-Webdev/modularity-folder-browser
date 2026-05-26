<?php

namespace ModularityFolderBrowser\Module;

use ModularityFolderBrowser\Service\DirectoryScanner;
use ModularityFolderBrowser\Service\FileMetadataFormatter;
use ModularityFolderBrowser\Service\PathResolver;
use ModularityFolderBrowser\Service\SourceRepository;

class FileBrowser extends \Modularity\Module
{
    public $slug = 'folder-browser';
    public $icon = 'dashicons-category';
    public $supports = [];
    public $isBlockCompatible = true;

    private SourceRepository $sources;
    private PathResolver $paths;
    private DirectoryScanner $scanner;

    public function init(): void
    {
        $this->nameSingular = __('Folder Browser', 'modularity-folder-browser');
        $this->namePlural = __('Folder Browsers', 'modularity-folder-browser');
        $this->description = __('Expose selected server folders as an accessible downloadable document browser.', 'modularity-folder-browser');

        $this->sources = new SourceRepository();
        $this->paths = new PathResolver($this->sources);
        $this->scanner = new DirectoryScanner($this->paths, new FileMetadataFormatter());
    }

    public function data(): array
    {
        $fields = $this->getFields();
        $moduleId = (int) $this->ID;
        $sortOrder = $this->normalizeSortOrder((string) ($fields['sort_order'] ?? 'name_asc'));
        $override = isset($fields['allowed_file_types_override']) && is_array($fields['allowed_file_types_override'])
            ? $fields['allowed_file_types_override']
            : [];
        $allowedExtensions = $this->scanner->getAllowedExtensions($moduleId, $override);
        $initialState = (string) ($fields['initial_state'] ?? 'collapsed');

        return [
            'id' => 'mod-file-browser-' . $moduleId . '-' . wp_unique_id(),
            'moduleId' => $moduleId,
            'roots' => $this->prepareRoots((array) ($fields['start_folders'] ?? []), $moduleId, $sortOrder, $allowedExtensions, $initialState),
            'showFileSize' => !empty($fields['show_file_size']),
            'showModifiedDate' => !empty($fields['show_modified_date']),
            'showFileType' => !empty($fields['show_file_type']),
            'sortOrder' => $sortOrder,
            'initialState' => $initialState,
            'restBaseUrl' => esc_url_raw(rest_url('modularity-file-browser/v1/')),
        ];
    }

    public function template(): string
    {
        return 'file-browser.blade.php';
    }

    private function prepareRoots(array $roots, int $moduleId, string $sortOrder, array $allowedExtensions, string $initialState): array
    {
        $prepared = [];

        foreach ($roots as $index => $root) {
            if (!is_array($root)) {
                continue;
            }

            $source = sanitize_key((string) ($root['source'] ?? ''));
            $path = $this->paths->normalizeRelativePath((string) ($root['folder'] ?? ''));

            if ($source === '' || $path === null) {
                continue;
            }

            $base = $this->paths->resolveSelectedBase($source, $path);

            if (is_wp_error($base)) {
                continue;
            }

            $displayName = sanitize_text_field((string) ($root['display_name'] ?? ''));

            if ($displayName === '') {
                $displayName = $path !== '' ? basename($path) : ($this->sources->getSource($source)['label'] ?? __('Documents', 'modularity-folder-browser'));
            }

            $listing = $this->scanner->listDirectory($base, '', $moduleId, (int) $index, $sortOrder, $allowedExtensions);
            $isExpanded = !empty($root['initially_expanded']) || $initialState === 'expanded_first_level';

            $prepared[] = [
                'index' => (int) $index,
                'source' => $source,
                'path' => $path,
                'label' => $displayName,
                'expanded' => $isExpanded,
                'listing' => is_wp_error($listing) ? [
                    'folders' => [],
                    'files' => [],
                    'counts' => ['folders' => 0, 'files' => 0],
                ] : $listing,
                'error' => is_wp_error($listing) ? $listing->get_error_message() : '',
            ];
        }

        return $prepared;
    }

    private function normalizeSortOrder(string $sortOrder): string
    {
        $allowed = ['name_asc', 'name_desc', 'date_desc', 'date_asc', 'type_asc'];

        return in_array($sortOrder, $allowed, true) ? $sortOrder : 'name_asc';
    }
}
