<?php

namespace ModularityFolderBrowser\Service;

class FileExtensionPolicy
{
    private const DEFAULT_ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'odt',
        'rtf',
        'xls',
        'xlsx',
        'ods',
        'ppt',
        'pptx',
        'odp',
        'txt',
        'md',
        'csv',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'zip',
        'rar',
        'gz',
        '7z',
        'tar',
        'mp4',
        'm4v',
        'mov',
        'avi',
        'webm',
        'mkv',
        'wmv',
        'mpeg',
        'mpg',
        '3gp',
        'ogv',
        'mp3',
        'wav',
        'ogg',
        'oga',
        'm4a',
        'aac',
        'flac',
        'wma',
        'aiff',
        'aif',
        'opus',
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

    public function getAllowedExtensions(?int $moduleId = null, array $override = []): array
    {
        $global = apply_filters('Modularity/Module/FolderBrowser/AllowedFileTypes', self::DEFAULT_ALLOWED_EXTENSIONS, $moduleId);
        $global = $this->sanitizeExtensions(is_array($global) ? $global : self::DEFAULT_ALLOWED_EXTENSIONS);

        if ($override === []) {
            return $global;
        }

        $override = $this->sanitizeExtensions($override);

        return array_values(array_intersect($global, $override));
    }

    public function sanitizeExtensions(array $extensions): array
    {
        $extensions = array_map(static fn($extension) => strtolower(ltrim((string) $extension, '.')), $extensions);
        $extensions = array_filter($extensions, static fn($extension) => $extension !== '' && !in_array($extension, self::BLOCKED_EXTENSIONS, true));

        return array_values(array_unique($extensions));
    }

    public function isAllowedExtension(string $extension, array $allowedExtensions): bool
    {
        $extension = strtolower(ltrim($extension, '.'));

        return $extension !== ''
            && !in_array($extension, self::BLOCKED_EXTENSIONS, true)
            && in_array($extension, $allowedExtensions, true);
    }
}
