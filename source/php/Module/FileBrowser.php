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
        $startFolders = (array) ($fields['start_folders'] ?? []);
        $moduleId = (int) $this->ID;
        $sortOrder = $this->normalizeSortOrder((string) ($fields['sort_order'] ?? 'name_asc'));
        $override = isset($fields['allowed_file_types_override']) && is_array($fields['allowed_file_types_override'])
            ? $fields['allowed_file_types_override']
            : [];
        $allowedExtensions = $this->scanner->getAllowedExtensions($moduleId, $override);
        $topFolderName = sanitize_text_field((string) ($fields['top_folder_name'] ?? ''));
        $roots = $this->prepareRoots($startFolders, $moduleId, $sortOrder, $allowedExtensions);

        if ($topFolderName !== '' && count($roots) === 1 && $this->hasExactlyOneConfiguredSourceFolder($startFolders)) {
            $roots[0]['attachListingToTopFolder'] = true;
            $roots[0]['expanded'] = true;
        }

        return [
            'id' => 'mod-file-browser-' . $moduleId . '-' . wp_unique_id(),
            'moduleId' => $moduleId,
            'roots' => $roots,
            'topFolderName' => $topFolderName,
            'topFolderExpanded' => $topFolderName !== '',
            'showFileSize' => !empty($fields['show_file_size']),
            'showModifiedDate' => !empty($fields['show_modified_date']),
            'showFileType' => !empty($fields['show_file_type']),
            'showFileDescription' => !array_key_exists('show_file_description', $fields) || !empty($fields['show_file_description']),
            'downloadDisplay' => $this->normalizeDownloadDisplay((string) ($fields['download_display'] ?? 'text')),
            'sortOrder' => $sortOrder,
            'restBaseUrl' => esc_url_raw(rest_url('modularity-file-browser/v1/')),
            'folderIconUrl' => \ModularityFolderBrowser\Helper\IconResolver::getFolderUrl(),
            'downloadIconUrl' => \ModularityFolderBrowser\Helper\IconResolver::getDownloadUrl(),
        ];
    }

    public function template(): string
    {
        return 'file-browser.blade.php';
    }

    private function prepareRoots(array $roots, int $moduleId, string $sortOrder, array $allowedExtensions): array
    {
        $prepared = [];

        foreach ($roots as $index => $root) {
            if (!is_array($root)) {
                continue;
            }

            $source = sanitize_key((string) ($root['source'] ?? ''));

            if ($source === '') {
                continue;
            }

            foreach ($this->parseFolderPaths($root['folder'] ?? '') as $folderPath) {
                $path = $this->paths->normalizeRelativePath($folderPath);

                if ($path === null) {
                    continue;
                }

                $base = $this->paths->resolveSelectedBase($source, $path);

                if (is_wp_error($base)) {
                    continue;
                }

                $displayName = $path !== ''
                    ? basename($path)
                    : ($this->sources->getSource($source)['label'] ?? __('Documents', 'modularity-folder-browser'));

                $rootIndex = count($prepared);
                $listing = $this->scanner->listDirectory($base, '', $moduleId, $rootIndex, $sortOrder, $allowedExtensions);

                $prepared[] = [
                    'index' => $rootIndex,
                    'source' => $source,
                    'path' => $path,
                    'label' => $displayName,
                    'expanded' => !empty($root['initially_expanded']),
                    'listing' => is_wp_error($listing) ? [
                        'folders' => [],
                        'files' => [],
                        'counts' => ['folders' => 0, 'files' => 0],
                    ] : $listing,
                    'error' => is_wp_error($listing) ? $listing->get_error_message() : '',
                ];
            }
        }

        if (count($prepared) === 1) {
            $prepared[0]['expanded'] = true;
        }

        return $prepared;
    }

    private function hasExactlyOneConfiguredSourceFolder(array $roots): bool
    {
        $sourceCount = 0;
        $folderCount = 0;

        foreach ($roots as $root) {
            if (!is_array($root)) {
                continue;
            }

            $source = sanitize_key((string) ($root['source'] ?? ''));

            if ($source === '') {
                continue;
            }

            $sourceCount++;

            foreach ($this->parseFolderPaths($root['folder'] ?? '') as $folderPath) {
                if ($this->paths->normalizeRelativePath($folderPath) === null) {
                    continue;
                }

                $folderCount++;
            }
        }

        return $sourceCount === 1 && $folderCount === 1;
    }

    private function parseFolderPaths(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return [''];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        return [$value];
    }

    private function normalizeSortOrder(string $sortOrder): string
    {
        $allowed = ['name_asc', 'name_desc', 'date_desc', 'date_asc', 'type_asc'];

        return in_array($sortOrder, $allowed, true) ? $sortOrder : 'name_asc';
    }

    private function normalizeDownloadDisplay(string $downloadDisplay): string
    {
        return in_array($downloadDisplay, ['text', 'icon'], true) ? $downloadDisplay : 'text';
    }
}
