<?php

namespace ModularityFolderBrowser\Helper;

/**
 * Maps file extensions to icon URLs and exposes a WordPress filter
 * so themes and other plugins can swap any icon.
 *
 * Filter: modularity_file_browser_file_icon
 *   @param string $url       Absolute URL to the default SVG icon.
 *   @param string $extension Lowercase file extension (e.g. 'pdf').
 *   @param string $category  Internal icon category (e.g. 'pdf', 'spreadsheet').
 *   @return string           URL to the icon to use.
 *
 * Example:
 *   add_filter('modularity_file_browser_file_icon', function($url, $ext, $category) {
 *       if ($category === 'pdf') {
 *           return get_template_directory_uri() . '/icons/my-pdf.svg';
 *       }
 *       return $url;
 *   }, 10, 3);
 */
class IconResolver
{
    /**
     * Extension → icon filename (without .svg) mapping.
     * The icon files live in source/icons/.
     */
    private const EXTENSION_MAP = [
        // PDF
        'pdf'  => 'pdf',
        // Word / text documents
        'doc'  => 'text',
        'docx' => 'text',
        'odt'  => 'text',
        'rtf'  => 'text',
        // Plain text / markdown
        'txt'  => 'text',
        'md'   => 'text',
        // Spreadsheets
        'xls'  => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv'  => 'spreadsheet',
        'ods'  => 'spreadsheet',
        // Presentations
        'ppt'  => 'presentation',
        'pptx' => 'presentation',
        'odp'  => 'presentation',
        // Archives
        'zip'  => 'archive',
        'rar'  => 'archive',
        'gz'   => 'archive',
        '7z'   => 'archive',
        'tar'  => 'archive',
        // Images
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'svg'  => 'image',
        'webp' => 'image',
        // Video
        'mp4'  => 'video',
        'm4v'  => 'video',
        'mov'  => 'video',
        'avi'  => 'video',
        'webm' => 'video',
        'mkv'  => 'video',
        'wmv'  => 'video',
        'mpeg' => 'video',
        'mpg'  => 'video',
        '3gp'  => 'video',
        'ogv'  => 'video',
        // Audio
        'mp3'  => 'audio',
        'wav'  => 'audio',
        'ogg'  => 'audio',
        'oga'  => 'audio',
        'm4a'  => 'audio',
        'aac'  => 'audio',
        'flac' => 'audio',
        'wma'  => 'audio',
        'aiff' => 'audio',
        'aif'  => 'audio',
        'opus' => 'audio',
    ];

    /**
     * Returns the absolute URL for the folder icon.
     *
     * Override via the `modularity_file_browser_folder_icon` filter:
     *
     *   add_filter('modularity_file_browser_folder_icon', function($url) {
     *       return get_template_directory_uri() . '/icons/folder.svg';
     *   });
     *
     * @return string  Absolute URL to an SVG icon file.
     */
    public static function getFolderUrl(): string
    {
        $url = MODULARITY_FOLDER_BROWSER_URL . '/source/icons/folder.svg';

        return (string) apply_filters('modularity_file_browser_folder_icon', $url);
    }

    /**
     * Returns the absolute URL for the download icon.
     *
     * @return string Absolute URL to an SVG icon file.
     */
    public static function getDownloadUrl(): string
    {
        $url = MODULARITY_FOLDER_BROWSER_URL . '/source/icons/download.svg';

        return (string) apply_filters('modularity_file_browser_download_icon', $url);
    }

    /**
     * Returns the icon category for $extension (the icon filename without .svg).
     *
     * @param string $extension  File extension without leading dot (e.g. 'pdf').
     * @return string            Icon category name (e.g. 'pdf', 'spreadsheet', 'file').
     */
    public static function getCategory(string $extension): string
    {
        return self::EXTENSION_MAP[strtolower(trim($extension))] ?? 'file';
    }

    /**
     * Returns the absolute URL for the icon that best represents $extension.
     *
     * @param string $extension  File extension without leading dot (e.g. 'pdf').
     * @return string            Absolute URL to an SVG icon file.
     */
    public static function getUrl(string $extension): string
    {
        $extension = strtolower(trim($extension));
        $category  = self::EXTENSION_MAP[$extension] ?? 'file';
        $url       = MODULARITY_FOLDER_BROWSER_URL . '/source/icons/' . $category . '.svg';

        return (string) apply_filters(
            'modularity_file_browser_file_icon',
            $url,
            $extension,
            $category
        );
    }

    /**
     * Returns the full extension → URL map for passing to JavaScript.
     * Also includes a '' (empty string) key as the fallback for unknown extensions.
     *
     * @return array<string, string>
     */
    public static function getIconMap(): array
    {
        $map = [];

        foreach (array_keys(self::EXTENSION_MAP) as $ext) {
            $map[$ext] = self::getUrl($ext);
        }

        // Fallback for unknown / missing extensions.
        $fallback   = MODULARITY_FOLDER_BROWSER_URL . '/source/icons/file.svg';
        $map['']    = (string) apply_filters('modularity_file_browser_file_icon', $fallback, '', 'file');

        return $map;
    }

    /**
     * Returns the full extension → category map for passing to JavaScript.
     * Includes '' → 'file' as the fallback.
     *
     * @return array<string, string>
     */
    public static function getCategoryMap(): array
    {
        $map = array_map(fn($category) => $category, self::EXTENSION_MAP);
        $map[''] = 'file';

        return $map;
    }
}
