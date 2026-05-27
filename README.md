# Modularity Folder Browser

An accessible Modularity module for exposing selected server folders as a public document browser. Editors choose one or more folders from configured safe filesystem sources, visitors can browse nested folders, and allowed files can be downloaded without exposing real filesystem paths.

## Features

- Select one or more start folders from configured filesystem sources
- Optional top folder name to group selected folders on the frontend
- Modal folder picker with lazy-loaded expandable folder trees and checkbox selection
- Accessible disclosure/list interface using real buttons and links
- Lazy-loaded subfolders through WordPress REST endpoints
- Public download endpoint with server-side path and extension validation
- Optional file type, file size, and modified date display
- Natural sorting by name, date, or file type
- Hidden files, traversal attempts, and unsafe file extensions are blocked
- Source paths stay in code and are never rendered in HTML or JSON
- Default file support includes common office documents, text files, images, archives, video, and audio

## Configuration

Sources must be configured by code. Use either the `MODULARITY_FILE_BROWSER_SOURCES` constant or the `modularity_file_browser_sources` filter.

```php
define('MODULARITY_FILE_BROWSER_SOURCES', [
    'public-documents' => [
        'label' => 'Public documents',
        'path'  => '/srv/shared/public-documents',
    ],
]);
```

Each source path must be absolute, readable by the web server user, and resolvable with `realpath()`. Invalid sources are ignored.

## Module Fields

- Top folder name
- Start folders
- Source
- Selected folders
- Initially expanded
- Show file type
- Show file size
- Show modified date
- Sort order
- Initial state
- Allowed file types override

Selected folders are stored only as relative paths inside the selected source. The admin folder picker uses an authenticated REST endpoint and never shows absolute paths.

The allowed file types override field can only narrow the globally allowed extensions. Use the `modularity_file_browser_allowed_extensions` filter to change the global list.

## REST Endpoints

- `GET /wp-json/modularity-file-browser/v1/admin/folders?source=public-documents&path=policies`
- `GET /wp-json/modularity-file-browser/v1/modules/{module_id}/roots/{root_index}/tree?path=optional/subfolder`
- `GET /wp-json/modularity-file-browser/v1/download?module_id=123&root=0&path=file.pdf`

The admin endpoint requires `edit_posts`. Public tree and download endpoints revalidate the stored module configuration, selected root, requested relative path, and file extension on every request.

## Filters

### `modularity_file_browser_sources`

Add or change available filesystem sources.

```php
add_filter('modularity_file_browser_sources', function (array $sources): array {
    $sources['meeting-documents'] = [
        'label' => 'Meeting documents',
        'path'  => '/srv/shared/meeting-documents',
    ];

    return $sources;
});
```

### `modularity_file_browser_allowed_extensions`

Restrict or extend the global file extension allowlist. The filter receives the plugin defaults and the current module ID, so it can be used to add file types, remove file types, or replace the whole list. Server-side executable extensions remain blocked even if they are added here.

```php
add_filter('modularity_file_browser_allowed_extensions', function (array $extensions): array {
    $extensions[] = 'heic';

    return array_values(array_unique($extensions));
});
```

```php
add_filter('modularity_file_browser_allowed_extensions', function (array $extensions): array {
    return array_values(array_diff($extensions, ['zip', 'rar', '7z', 'tar', 'gz']));
});
```

```php
add_filter('modularity_file_browser_allowed_extensions', function (array $extensions, ?int $moduleId): array {
    if ($moduleId === 123) {
        return ['pdf', 'docx', 'xlsx'];
    }

    return $extensions;
}, 10, 2);
```

Default allowed extensions:

```txt
pdf, doc, docx, odt, rtf, xls, xlsx, ods, ppt, pptx, odp, txt, md, csv,
jpg, jpeg, png, gif, webp,
zip, rar, gz, 7z, tar,
mp4, m4v, mov, avi, webm, mkv, wmv, mpeg, mpg, 3gp, ogv,
mp3, wav, ogg, oga, m4a, aac, flac, wma, aiff, aif, opus
```

### `modularity_file_browser_file_icon`

Override the icon URL for a file extension or icon category.

```php
add_filter('modularity_file_browser_file_icon', function (string $url, string $extension, string $category): string {
    if ($category === 'archive') {
        return get_stylesheet_directory_uri() . '/assets/icons/archive.svg';
    }

    return $url;
}, 10, 3);
```

### `modularity_file_browser_folder_icon`

Override the folder icon URL.

```php
add_filter('modularity_file_browser_folder_icon', function (string $url): string {
    return get_stylesheet_directory_uri() . '/assets/icons/folder.svg';
});
```

### `modularity_file_browser_cache_ttl`

Change directory listing cache duration.

```php
add_filter('modularity_file_browser_cache_ttl', function (): int {
    return 5 * MINUTE_IN_SECONDS;
});
```

## Development

```bash
composer install
npm install
npm run build
```

## Requirements

- WordPress
- Modularity
- Advanced Custom Fields Pro
- Municipio/component library for Blade rendering

## License

MIT
