# Modularity Folder Browser

An accessible Modularity module for exposing selected server folders as a public document browser. Editors choose one or more folders from configured safe filesystem sources, visitors can browse nested folders, and allowed files can be downloaded without exposing real filesystem paths.

## Features

- Select one or more start folders from configured filesystem sources
- Optional top folder name to group selected folders on the frontend
- Optional display name per selected folder
- Modal folder picker with lazy-loaded expandable folder trees and checkbox selection
- Accessible disclosure/list interface using real buttons and links
- Lazy-loaded subfolders through WordPress REST endpoints
- Public download endpoint with server-side path and extension validation
- Optional file type, file size, and modified date display
- Natural sorting by name, date, or file type
- Hidden files, traversal attempts, and unsafe file extensions are blocked
- Source paths stay in code and are never rendered in HTML or JSON

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
- Folder
- Initially expanded
- Show file type
- Show file size
- Show modified date
- Sort order
- Initial state
- Allowed file types override

The folder field stores only a relative path inside the selected source. The admin folder picker uses an authenticated REST endpoint and never shows absolute paths.

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

Restrict or extend the global file extension allowlist. Server-side executable extensions remain blocked.

```php
add_filter('modularity_file_browser_allowed_extensions', function (array $extensions): array {
    return ['pdf', 'docx', 'xlsx'];
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
