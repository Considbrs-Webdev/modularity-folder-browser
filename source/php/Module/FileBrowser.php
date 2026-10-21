<?php

namespace ModularityFolderBrowser\Module;

use ModularityFolderBrowser\Service\DirectoryScanner;
use ModularityFolderBrowser\Service\FileMetadataFormatter;
use ModularityFolderBrowser\Service\InstanceTokenStore;
use ModularityFolderBrowser\Service\PathResolver;
use ModularityFolderBrowser\Service\SingleFileSelection;
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
    private InstanceTokenStore $instanceTokens;
    private SingleFileSelection $singleFile;

    public function init(): void
    {
        $this->nameSingular = __('Folder Browser', 'modularity-folder-browser');
        $this->namePlural = __('Folder Browsers', 'modularity-folder-browser');
        $this->description = __('Expose selected server folders as an accessible downloadable document browser.', 'modularity-folder-browser');

        $this->sources = new SourceRepository();
        $this->paths = new PathResolver($this->sources);
        $this->scanner = new DirectoryScanner($this->paths, new FileMetadataFormatter());
        $this->instanceTokens = new InstanceTokenStore();
        $this->singleFile = new SingleFileSelection($this->paths);
    }

    public function data(): array
    {
        $fields = $this->getFields();

        if ($this->singleFile->isFileMode($fields)) {
            return $this->singleFileData($fields);
        }

        $startFolders = (array) ($fields['start_folders'] ?? []);
        $moduleId = $this->getPersistedModuleId((int) $this->ID);
        $sortOrder = $this->normalizeSortOrder((string) ($fields['sort_order'] ?? 'name_asc'));
        $override = isset($fields['allowed_file_types_override']) && is_array($fields['allowed_file_types_override'])
            ? $fields['allowed_file_types_override']
            : [];
        $allowedExtensions = $this->scanner->getAllowedExtensions($moduleId, $override);
        $topFolderName = sanitize_text_field((string) ($fields['top_folder_name'] ?? ''));
        $normalizedRoots = $this->normalizeRoots($startFolders);
        $instanceToken = $this->createInstanceToken($moduleId, $normalizedRoots, $sortOrder, $allowedExtensions);
        $roots = $this->prepareRoots($normalizedRoots, $moduleId, $sortOrder, $allowedExtensions, $instanceToken);

        if ($topFolderName !== '' && count($roots) === 1 && $this->hasExactlyOneConfiguredSourceFolder($startFolders)) {
            $roots[0]['attachListingToTopFolder'] = true;
            $roots[0]['expanded'] = true;
        }

        return [
            'id' => 'mod-file-browser-' . $moduleId . '-' . wp_unique_id(),
            'moduleId' => $moduleId,
            'instanceToken' => $instanceToken,
            'roots' => $roots,
            'topFolderName' => $topFolderName,
            'topFolderExpanded' => $topFolderName !== '',
            'enableSearch' => !empty($fields['enable_search']),
            'searchDebounce' => $this->getSearchDebounce($moduleId),
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
        return $this->singleFile->isFileMode($this->getFields())
            ? 'file-single.blade.php'
            : 'file-browser.blade.php';
    }

    private function singleFileData(array $fields): array
    {
        $moduleId = $this->getPersistedModuleId((int) $this->ID);
        $override = isset($fields['allowed_file_types_override']) && is_array($fields['allowed_file_types_override'])
            ? $fields['allowed_file_types_override']
            : [];
        $allowedExtensions = $this->scanner->getAllowedExtensions($moduleId, $override);
        $selection = $this->singleFile->fromFields($fields);
        $file = null;
        $instanceToken = '';

        if ($selection !== null) {
            $base = $this->paths->resolveSelectedBase($selection['root']['source'], $selection['root']['path']);

            if (!is_wp_error($base)) {
                $instanceToken = $moduleId > 0
                    ? ''
                    : $this->instanceTokens->create([
                        'module_id' => 0,
                        'roots' => [$selection['root']],
                        'single_file' => $selection['file'],
                        'allowed_extensions' => $allowedExtensions,
                    ], 0);

                $described = $this->scanner->describeFile($base, $selection['file'], $moduleId, 0, $allowedExtensions, $instanceToken);
                $file = is_wp_error($described) ? null : $described;
            }
        }

        return [
            'id' => 'mod-file-browser-' . $moduleId . '-' . wp_unique_id(),
            'file' => $file,
            'showFileSize' => !empty($fields['show_file_size']),
            'showModifiedDate' => !empty($fields['show_modified_date']),
            'showFileType' => !empty($fields['show_file_type']),
            'showFileDescription' => !array_key_exists('show_file_description', $fields) || !empty($fields['show_file_description']),
            'downloadDisplay' => $this->normalizeDownloadDisplay((string) ($fields['download_display'] ?? 'text')),
            'downloadIconUrl' => \ModularityFolderBrowser\Helper\IconResolver::getDownloadUrl(),
        ];
    }

    private function prepareRoots(array $roots, int $moduleId, string $sortOrder, array $allowedExtensions, string $instanceToken): array
    {
        $prepared = [];

        foreach ($roots as $rootIndex => $root) {
            if (!is_array($root)) {
                continue;
            }

            $source = sanitize_key((string) ($root['source'] ?? ''));

            if ($source === '') {
                continue;
            }

            $path = $this->paths->normalizeRelativePath((string) ($root['path'] ?? ''));

            if ($path === null) {
                continue;
            }

            $base = $this->paths->resolveSelectedBase($source, $path);

            if (is_wp_error($base)) {
                continue;
            }

            $displayName = $root['label'] !== ''
                ? $root['label']
                : ($path !== ''
                    ? basename($path)
                    : ($this->sources->getSource($source)['label'] ?? __('Documents', 'modularity-folder-browser')));

            $listing = $this->scanner->listDirectory($base, '', $moduleId, $rootIndex, $sortOrder, $allowedExtensions, $instanceToken);

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

        if (count($prepared) === 1) {
            $prepared[0]['expanded'] = true;
        }

        return $prepared;
    }

    private function normalizeRoots(array $roots): array
    {
        $normalized = [];

        foreach ($roots as $root) {
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

                $normalized[] = [
                    'source' => $source,
                    'path' => $path,
                    'label' => $path !== ''
                        ? basename($path)
                        : ($this->sources->getSource($source)['label'] ?? __('Documents', 'modularity-folder-browser')),
                    'initially_expanded' => !empty($root['initially_expanded']),
                ];
            }
        }

        return $normalized;
    }

    private function createInstanceToken(int $moduleId, array $roots, string $sortOrder, array $allowedExtensions): string
    {
        if ($moduleId > 0 || $roots === []) {
            return '';
        }

        return $this->instanceTokens->create([
            'module_id' => 0,
            'roots' => $roots,
            'sort_order' => $sortOrder,
            'allowed_extensions' => $allowedExtensions,
        ], 0);
    }

    private function getPersistedModuleId(int $moduleId): int
    {
        if ($moduleId <= 0 || get_post_type($moduleId) !== 'mod-folder-browser') {
            return 0;
        }

        return $moduleId;
    }

    private function getSearchDebounce(int $moduleId): int
    {
        $debounce = apply_filters('Modularity/Module/FolderBrowser/Frontend/FilterDebounce', 500, $moduleId);

        return max(0, (int) $debounce);
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
