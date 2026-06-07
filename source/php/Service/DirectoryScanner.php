<?php

namespace ModularityFolderBrowser\Service;

use DirectoryIterator;
use WP_Error;

class DirectoryScanner
{
    public function __construct(
        private PathResolver $paths,
        private FileMetadataFormatter $metadata,
        private ?FileExtensionPolicy $extensions = null
    ) {
        $this->extensions = $this->extensions ?? new FileExtensionPolicy();
    }

    public function getAllowedExtensions(?int $moduleId = null, array $override = []): array
    {
        return $this->extensions->getAllowedExtensions($moduleId, $override);
    }

    /**
     * @return array{folders: array<int, array>, files: array<int, array>, counts: array{folders: int, files: int}}|WP_Error
     */
    public function listDirectory(
        string $basePath,
        string $relativePath,
        int $moduleId,
        int $rootIndex,
        string $sortOrder = 'name_asc',
        array $allowedExtensions = [],
        string $instanceToken = ''
    ) {
        $directory = $this->paths->resolveInside($basePath, $relativePath, true);

        if (is_wp_error($directory)) {
            return $directory;
        }

        $folders = [];
        $files = [];
        $allowedExtensions = $this->extensions->sanitizeExtensions($allowedExtensions ?: $this->getAllowedExtensions($moduleId));
        $cacheKey = $this->cacheKey($directory, $moduleId, $rootIndex, $sortOrder, $allowedExtensions, $instanceToken);
        $cached = get_transient($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            foreach (new DirectoryIterator($directory) as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $name = $item->getFilename();

                if ($this->isHidden($name)) {
                    continue;
                }

                $childRelativePath = trim($relativePath . '/' . $name, '/');

                if ($item->isDir()) {
                    $resolved = $this->paths->resolveInside($basePath, $childRelativePath, true);

                    if (is_wp_error($resolved)) {
                        continue;
                    }

                    $folders[] = [
                        'name' => $name,
                        'label' => $name,
                        'path' => $childRelativePath,
                        'has_children' => $this->hasVisibleChildren($resolved, $basePath, $childRelativePath, $moduleId, $allowedExtensions),
                        'modified' => $item->getMTime(),
                    ];
                    continue;
                }

                if (!$item->isFile()) {
                    continue;
                }

                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!$this->isAllowedExtension($extension, $allowedExtensions)) {
                    continue;
                }

                $resolved = $this->paths->resolveInside($basePath, $childRelativePath, false);

                if (is_wp_error($resolved)) {
                    continue;
                }

                $size = (int) $item->getSize();
                $modified = (int) $item->getMTime();

                $files[] = [
                    'name' => $name,
                    'label' => $this->metadata->fileLabel($name),
                    'extension' => $extension,
                    'mime_type' => wp_check_filetype($name)['type'] ?: 'application/octet-stream',
                    'type_label' => $this->metadata->fileTypeLabel($extension),
                    'size' => $size,
                    'size_human' => $this->metadata->formatBytes($size),
                    'modified' => wp_date(DATE_W3C, $modified),
                    'modified_human' => $this->metadata->formatDate($modified),
                    'download_url' => $this->downloadUrl($moduleId, $rootIndex, $childRelativePath, $instanceToken),
                ];
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Modularity Folder Browser scan failed: ' . $e->getMessage());
            }

            return new WP_Error('directory_unavailable', __('This folder could not be loaded.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $this->sortItems($folders, $files, $sortOrder);

        $result = [
            'folders' => $folders,
            'files' => $files,
            'counts' => [
                'folders' => count($folders),
                'files' => count($files),
            ],
        ];

        set_transient($cacheKey, $result, (int) apply_filters('Modularity/Module/FolderBrowser/CacheTTL', 10 * MINUTE_IN_SECONDS, $moduleId));

        return $result;
    }

    /**
     * @return array{folders: array<int, array>, files: array<int, array>, counts: array{folders: int, files: int}}|WP_Error
     */
    public function searchDirectory(
        string $basePath,
        int $moduleId,
        int $rootIndex,
        string $query,
        string $sortOrder = 'name_asc',
        array $allowedExtensions = [],
        string $instanceToken = ''
    ) {
        $directory = $this->paths->resolveInside($basePath, '', true);

        if (is_wp_error($directory)) {
            return $directory;
        }

        $query = $this->normalizeSearchString($query);

        if ($query === '') {
            return [
                'folders' => [],
                'files' => [],
                'counts' => ['folders' => 0, 'files' => 0],
            ];
        }

        $folders = [];
        $files = [];
        $allowedExtensions = $this->extensions->sanitizeExtensions($allowedExtensions ?: $this->getAllowedExtensions($moduleId));

        try {
            $this->searchDirectoryRecursive($directory, $basePath, '', $moduleId, $rootIndex, $query, $allowedExtensions, $folders, $files, $instanceToken);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Modularity Folder Browser search failed: ' . $e->getMessage());
            }

            return new WP_Error('directory_unavailable', __('This folder could not be searched.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $this->sortItems($folders, $files, $sortOrder);

        return [
            'folders' => $folders,
            'files' => $files,
            'counts' => [
                'folders' => count($folders),
                'files' => count($files),
            ],
        ];
    }

    public function listFoldersOnly(string $basePath, string $relativePath): array
    {
        $directory = $this->paths->resolveInside($basePath, $relativePath, true);

        if (is_wp_error($directory)) {
            return [];
        }

        $folders = [];

        try {
            foreach (new DirectoryIterator($directory) as $item) {
                if ($item->isDot() || !$item->isDir() || $this->isHidden($item->getFilename())) {
                    continue;
                }

                $name = $item->getFilename();
                $childRelativePath = trim($relativePath . '/' . $name, '/');
                $resolved = $this->paths->resolveInside($basePath, $childRelativePath, true);

                if (is_wp_error($resolved)) {
                    continue;
                }

                $folders[] = [
                    'name' => $name,
                    'path' => $childRelativePath,
                    'has_children' => $this->hasVisibleFolders($resolved),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        usort($folders, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $folders;
    }

    private function isAllowedExtension(string $extension, array $allowedExtensions): bool
    {
        return $this->extensions->isAllowedExtension($extension, $allowedExtensions);
    }

    private function isHidden(string $name): bool
    {
        return str_starts_with($name, '.');
    }

    private function hasVisibleFolders(string $directory): bool
    {
        try {
            foreach (new DirectoryIterator($directory) as $item) {
                if (!$item->isDot() && $item->isDir() && !$this->isHidden($item->getFilename())) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function hasVisibleChildren(string $directory, string $basePath, string $relativePath, int $moduleId, array $allowedExtensions): bool
    {
        try {
            foreach (new DirectoryIterator($directory) as $item) {
                if ($item->isDot() || $this->isHidden($item->getFilename())) {
                    continue;
                }

                if ($item->isDir()) {
                    return true;
                }

                if ($item->isFile() && $this->isAllowedExtension(strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION)), $allowedExtensions)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function searchDirectoryRecursive(
        string $directory,
        string $basePath,
        string $relativePath,
        int $moduleId,
        int $rootIndex,
        string $query,
        array $allowedExtensions,
        array &$folders,
        array &$files,
        string $instanceToken = ''
    ): void {
        foreach (new DirectoryIterator($directory) as $item) {
            if ($item->isDot()) {
                continue;
            }

            $name = $item->getFilename();

            if ($this->isHidden($name)) {
                continue;
            }

            $childRelativePath = trim($relativePath . '/' . $name, '/');

            if ($item->isDir()) {
                $resolved = $this->paths->resolveInside($basePath, $childRelativePath, true);

                if (is_wp_error($resolved)) {
                    continue;
                }

                if ($this->matchesSearch($query, $name)) {
                    $folders[] = [
                        'name' => $name,
                        'label' => $name,
                        'path' => $childRelativePath,
                        'root_index' => $rootIndex,
                        'has_children' => $this->hasVisibleChildren($resolved, $basePath, $childRelativePath, $moduleId, $allowedExtensions),
                        'modified' => $item->getMTime(),
                    ];
                }

                $this->searchDirectoryRecursive($resolved, $basePath, $childRelativePath, $moduleId, $rootIndex, $query, $allowedExtensions, $folders, $files, $instanceToken);
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!$this->isAllowedExtension($extension, $allowedExtensions)) {
                continue;
            }

            $resolved = $this->paths->resolveInside($basePath, $childRelativePath, false);

            if (is_wp_error($resolved)) {
                continue;
            }

            $label = $this->metadata->fileLabel($name);

            if (!$this->matchesSearch($query, $name, $label)) {
                continue;
            }

            $size = (int) $item->getSize();
            $modified = (int) $item->getMTime();

            $files[] = [
                'name' => $name,
                'label' => $label,
                'extension' => $extension,
                'mime_type' => wp_check_filetype($name)['type'] ?: 'application/octet-stream',
                'type_label' => $this->metadata->fileTypeLabel($extension),
                'size' => $size,
                'size_human' => $this->metadata->formatBytes($size),
                'modified' => wp_date(DATE_W3C, $modified),
                'modified_human' => $this->metadata->formatDate($modified),
                'download_url' => $this->downloadUrl($moduleId, $rootIndex, $childRelativePath, $instanceToken),
                'root_index' => $rootIndex,
            ];
        }
    }

    private function matchesSearch(string $query, string ...$values): bool
    {
        foreach ($values as $value) {
            if (str_contains($this->normalizeSearchString($value), $query)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSearchString(string $value): string
    {
        $value = trim($value);

        if (class_exists('\Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    private function sortItems(array &$folders, array &$files, string $sortOrder): void
    {
        $compareNameAsc = static fn($a, $b) => strnatcasecmp($a['label'] ?? $a['name'], $b['label'] ?? $b['name']);

        match ($sortOrder) {
            'name_desc' => [
                usort($folders, static fn($a, $b) => -$compareNameAsc($a, $b)),
                usort($files, static fn($a, $b) => -$compareNameAsc($a, $b)),
            ],
            'date_desc' => [
                usort($folders, static fn($a, $b) => ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0)),
                usort($files, static fn($a, $b) => strcmp($b['modified'] ?? '', $a['modified'] ?? '')),
            ],
            'date_asc' => [
                usort($folders, static fn($a, $b) => ($a['modified'] ?? 0) <=> ($b['modified'] ?? 0)),
                usort($files, static fn($a, $b) => strcmp($a['modified'] ?? '', $b['modified'] ?? '')),
            ],
            'type_asc' => [
                usort($folders, $compareNameAsc),
                usort($files, static fn($a, $b) => (($a['extension'] ?? '') <=> ($b['extension'] ?? '')) ?: strnatcasecmp($a['label'], $b['label'])),
            ],
            default => [
                usort($folders, $compareNameAsc),
                usort($files, $compareNameAsc),
            ],
        };
    }

    private function cacheKey(string $directory, int $moduleId, int $rootIndex, string $sortOrder, array $allowedExtensions, string $instanceToken): string
    {
        return 'mod_fb_' . md5(implode('|', [$directory, $moduleId, $rootIndex, $sortOrder, implode(',', $allowedExtensions), $instanceToken]));
    }

    private function downloadUrl(int $moduleId, int $rootIndex, string $path, string $instanceToken = ''): string
    {
        $args = [
            'module_id' => $moduleId,
            'root' => $rootIndex,
            'path' => $path,
        ];

        if ($instanceToken !== '') {
            $args['instance_token'] = $instanceToken;
        }

        return add_query_arg($args, rest_url('modularity-file-browser/v1/download'));
    }
}
