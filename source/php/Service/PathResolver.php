<?php

namespace ModularityFolderBrowser\Service;

use WP_Error;

class PathResolver
{
    public function __construct(private SourceRepository $sources)
    {
    }

    /**
     * @return string|WP_Error
     */
    public function resolveSelectedBase(string $sourceKey, string $selectedPath)
    {
        $source = $this->sources->getSource($sourceKey);

        if (!$source) {
            return new WP_Error('invalid_source', __('Selected source is not available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $selectedPath = $this->normalizeRelativePath($selectedPath);

        if ($selectedPath === null) {
            return new WP_Error('invalid_path', __('Invalid folder path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        return $this->resolveInside($source['path'], $selectedPath, true);
    }

    /**
     * @return string|WP_Error
     */
    public function resolveInside(string $basePath, string $relativePath, bool $directory = false)
    {
        $relativePath = $this->normalizeRelativePath($relativePath);

        if ($relativePath === null) {
            return new WP_Error('invalid_path', __('Invalid path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        $base = realpath($basePath);
        $targetCandidate = $relativePath === '' ? $basePath : $basePath . DIRECTORY_SEPARATOR . $relativePath;
        $target = realpath($targetCandidate);

        if (!$base || !$target) {
            return new WP_Error('not_found', __('The selected document is no longer available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);
        $insideBase = $target === $base || str_starts_with($target, $base . DIRECTORY_SEPARATOR);

        if (!$insideBase) {
            return new WP_Error('forbidden', __('Invalid path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        if ($directory && !is_dir($target)) {
            return new WP_Error('not_found', __('The selected folder is no longer available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        if (!$directory && !is_file($target)) {
            return new WP_Error('not_found', __('The selected document is no longer available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        if (!is_readable($target)) {
            return new WP_Error('forbidden', __('The selected item is not readable.', 'modularity-folder-browser'), ['status' => 403]);
        }

        return $target;
    }

    public function normalizeRelativePath(string $path): ?string
    {
        $path = wp_unslash($path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path);
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return null;
        }

        $parts = array_filter(array_map('trim', explode('/', $path)), static fn($part) => $part !== '');

        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || str_starts_with($part, '.') || str_contains($part, "\0")) {
                return null;
            }
        }

        return implode('/', $parts);
    }
}
