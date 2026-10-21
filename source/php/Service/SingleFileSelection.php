<?php

namespace ModularityFolderBrowser\Service;

/**
 * Resolves the "single file" display mode of a module into a root + file name pair.
 *
 * The selected file is expressed as a root (source + parent directory) plus the file name inside
 * that root, so the existing PathResolver/download flow can be reused unchanged.
 */
class SingleFileSelection
{
    public const MODE_FOLDERS = 'folders';
    public const MODE_FILE = 'file';

    public function __construct(private PathResolver $paths)
    {
    }

    public function isFileMode(array $fields): bool
    {
        return ($fields['display_mode'] ?? self::MODE_FOLDERS) === self::MODE_FILE;
    }

    /**
     * @return array{root: array{source: string, path: string, label: string, initially_expanded: bool}, file: string}|null
     */
    public function fromFields(array $fields): ?array
    {
        if (!$this->isFileMode($fields)) {
            return null;
        }

        $source = sanitize_key((string) ($fields['file_source'] ?? ''));
        $relativePath = $this->paths->normalizeRelativePath((string) ($fields['selected_file'] ?? ''));

        if ($source === '' || $relativePath === null || $relativePath === '') {
            return null;
        }

        $directory = dirname($relativePath);
        $directory = $directory === '.' ? '' : $directory;

        return [
            'root' => [
                'source' => $source,
                'path' => $directory,
                'label' => $directory !== '' ? basename($directory) : '',
                'initially_expanded' => false,
            ],
            'file' => basename($relativePath),
        ];
    }
}
