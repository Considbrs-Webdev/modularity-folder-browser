<?php

namespace ModularityFolderBrowser\Service;

class SourceRepository
{
    /**
     * @return array<string, array{label: string, path: string}>
     */
    public function getSources(): array
    {
        $sources = modularity_file_browser_get_sources();
        $validSources = [];

        foreach ($sources as $key => $source) {
            $key = is_string($key) ? sanitize_key($key) : '';
            $label = is_array($source) && isset($source['label']) ? (string) $source['label'] : '';
            $path = is_array($source) && isset($source['path']) ? (string) $source['path'] : '';
            $realPath = $path !== '' ? realpath($path) : false;

            if ($key === '' || $label === '' || !$realPath || !is_dir($realPath) || !is_readable($realPath) || !$this->isAbsolutePath($path)) {
                $this->logInvalidSource($key ?: '(missing key)');
                continue;
            }

            $validSources[$key] = [
                'label' => $label,
                'path'  => rtrim($realPath, DIRECTORY_SEPARATOR),
            ];
        }

        return $validSources;
    }

    public function getSource(string $sourceKey): ?array
    {
        $sources = $this->getSources();
        $sourceKey = sanitize_key($sourceKey);

        return $sources[$sourceKey] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getSourceChoices(): array
    {
        $choices = [];

        foreach ($this->getSources() as $key => $source) {
            $choices[$key] = $source['label'];
        }

        return $choices;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function logInvalidSource(string $key): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Modularity Folder Browser ignored invalid source "%s".', $key));
        }
    }
}
