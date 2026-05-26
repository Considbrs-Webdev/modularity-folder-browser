<?php

namespace ModularityFolderBrowser\Service;

use DirectoryIterator;
use WP_Error;

class DirectoryScanner
{
    private const DEFAULT_ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'csv',
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php',
        'phtml',
        'phar',
        'cgi',
        'pl',
        'py',
        'rb',
        'sh',
        'bash',
        'zsh',
        'exe',
        'dll',
        'so',
        'dylib',
        'htaccess',
    ];

    public function __construct(
        private PathResolver $paths,
        private FileMetadataFormatter $metadata
    ) {
    }

    public function getAllowedExtensions(?int $moduleId = null, array $override = []): array
    {
        $global = apply_filters('modularity_file_browser_allowed_extensions', self::DEFAULT_ALLOWED_EXTENSIONS, $moduleId);
        $global = $this->sanitizeExtensions(is_array($global) ? $global : self::DEFAULT_ALLOWED_EXTENSIONS);

        if ($override === []) {
            return $global;
        }

        $override = $this->sanitizeExtensions($override);

        return array_values(array_intersect($global, $override));
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
        array $allowedExtensions = []
    ) {
        $directory = $this->paths->resolveInside($basePath, $relativePath, true);

        if (is_wp_error($directory)) {
            return $directory;
        }

        $folders = [];
        $files = [];
        $allowedExtensions = $allowedExtensions ?: $this->getAllowedExtensions($moduleId);
        $cacheKey = $this->cacheKey($directory, $moduleId, $rootIndex, $sortOrder, $allowedExtensions);
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
                    'download_url' => $this->downloadUrl($moduleId, $rootIndex, $childRelativePath),
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

        set_transient($cacheKey, $result, (int) apply_filters('modularity_file_browser_cache_ttl', 10 * MINUTE_IN_SECONDS, $moduleId));

        return $result;
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

    private function sanitizeExtensions(array $extensions): array
    {
        $extensions = array_map(static fn($extension) => strtolower(ltrim((string) $extension, '.')), $extensions);
        $extensions = array_filter($extensions, static fn($extension) => $extension !== '' && !in_array($extension, self::BLOCKED_EXTENSIONS, true));

        return array_values(array_unique($extensions));
    }

    private function isAllowedExtension(string $extension, array $allowedExtensions): bool
    {
        return $extension !== ''
            && !in_array($extension, self::BLOCKED_EXTENSIONS, true)
            && in_array($extension, $allowedExtensions, true);
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

    private function cacheKey(string $directory, int $moduleId, int $rootIndex, string $sortOrder, array $allowedExtensions): string
    {
        return 'mod_fb_' . md5(implode('|', [$directory, $moduleId, $rootIndex, $sortOrder, implode(',', $allowedExtensions)]));
    }

    private function downloadUrl(int $moduleId, int $rootIndex, string $path): string
    {
        return add_query_arg(
            [
                'module_id' => $moduleId,
                'root' => $rootIndex,
                'path' => $path,
            ],
            rest_url('modularity-file-browser/v1/download')
        );
    }
}
