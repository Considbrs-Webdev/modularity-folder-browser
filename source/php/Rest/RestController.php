<?php

namespace ModularityFolderBrowser\Rest;

use ModularityFolderBrowser\Service\DirectoryScanner;
use ModularityFolderBrowser\Service\FileMetadataFormatter;
use ModularityFolderBrowser\Service\PathResolver;
use ModularityFolderBrowser\Service\SourceRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestController
{
    private SourceRepository $sources;
    private PathResolver $paths;
    private DirectoryScanner $scanner;

    public function __construct()
    {
        $this->sources = new SourceRepository();
        $this->paths = new PathResolver($this->sources);
        $this->scanner = new DirectoryScanner($this->paths, new FileMetadataFormatter());

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('modularity-file-browser/v1', '/admin/folders', [
            'methods' => 'GET',
            'callback' => [$this, 'adminFolders'],
            'permission_callback' => static fn() => current_user_can('edit_posts'),
            'args' => [
                'source' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'path' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('modularity-file-browser/v1', '/modules/(?P<module_id>\d+)/roots/(?P<root_index>\d+)/tree', [
            'methods' => 'GET',
            'callback' => [$this, 'tree'],
            'permission_callback' => '__return_true',
            'args' => [
                'module_id' => [
                    'sanitize_callback' => 'absint',
                ],
                'root_index' => [
                    'sanitize_callback' => 'absint',
                ],
                'path' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('modularity-file-browser/v1', '/modules/(?P<module_id>\d+)/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args' => [
                'module_id' => [
                    'sanitize_callback' => 'absint',
                ],
                'query' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('modularity-file-browser/v1', '/download', [
            'methods' => 'GET',
            'callback' => [$this, 'download'],
            'permission_callback' => '__return_true',
            'args' => [
                'module_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'root' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'path' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function adminFolders(WP_REST_Request $request)
    {
        $sourceKey = (string) $request->get_param('source');
        $path = (string) ($request->get_param('path') ?? '');
        $source = $this->sources->getSource($sourceKey);

        if (!$source) {
            return new WP_Error('invalid_source', __('Selected source is not available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $path = $this->paths->normalizeRelativePath($path);

        if ($path === null) {
            return new WP_Error('invalid_path', __('Invalid folder path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        $folders = $this->scanner->listFoldersOnly($source['path'], $path);
        $parent = '';

        if ($path !== '') {
            $parts = explode('/', $path);
            array_pop($parts);
            $parent = implode('/', $parts);
        }

        return new WP_REST_Response([
            'source' => $sourceKey,
            'path' => $path,
            'parent' => $parent,
            'folders' => $folders,
        ]);
    }

    public function tree(WP_REST_Request $request)
    {
        $moduleId = absint($request->get_param('module_id'));
        $rootIndex = absint($request->get_param('root_index'));
        $path = (string) ($request->get_param('path') ?? '');
        $config = $this->getModuleConfig($moduleId);

        if (is_wp_error($config)) {
            return $config;
        }

        if (!isset($config['roots'][$rootIndex])) {
            return new WP_Error('invalid_root', __('Selected folder is not available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $root = $config['roots'][$rootIndex];
        $base = $this->paths->resolveSelectedBase($root['source'], $root['path']);

        if (is_wp_error($base)) {
            return $base;
        }

        $path = $this->paths->normalizeRelativePath($path);

        if ($path === null) {
            return new WP_Error('invalid_path', __('Invalid path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        $listing = $this->scanner->listDirectory($base, $path, $moduleId, $rootIndex, $config['sort_order'], $config['allowed_extensions']);

        if (is_wp_error($listing)) {
            return $listing;
        }

        return new WP_REST_Response([
            'module_id' => $moduleId,
            'root_index' => $rootIndex,
            'root_label' => $root['label'],
            'path' => $path,
            'folders' => $listing['folders'],
            'files' => $listing['files'],
            'counts' => $listing['counts'],
        ]);
    }

    public function search(WP_REST_Request $request)
    {
        $moduleId = absint($request->get_param('module_id'));
        $query = trim((string) ($request->get_param('query') ?? ''));
        $config = $this->getModuleConfig($moduleId);

        if (is_wp_error($config)) {
            return $config;
        }

        if ($query === '') {
            return new WP_REST_Response([
                'module_id' => $moduleId,
                'query' => '',
                'folders' => [],
                'files' => [],
                'counts' => ['folders' => 0, 'files' => 0],
            ]);
        }

        $folders = [];
        $files = [];

        foreach ($config['roots'] as $rootIndex => $root) {
            $base = $this->paths->resolveSelectedBase($root['source'], $root['path']);

            if (is_wp_error($base)) {
                continue;
            }

            $listing = $this->scanner->searchDirectory($base, $moduleId, (int) $rootIndex, $query, $config['sort_order'], $config['allowed_extensions']);

            if (is_wp_error($listing)) {
                continue;
            }

            $folders = array_merge($folders, $listing['folders']);
            $files = array_merge($files, $listing['files']);
        }

        return new WP_REST_Response([
            'module_id' => $moduleId,
            'query' => $query,
            'folders' => $folders,
            'files' => $files,
            'counts' => [
                'folders' => count($folders),
                'files' => count($files),
            ],
        ]);
    }

    public function download(WP_REST_Request $request)
    {
        $moduleId = absint($request->get_param('module_id'));
        $rootIndex = absint($request->get_param('root'));
        $requestedPath = (string) $request->get_param('path');
        $config = $this->getModuleConfig($moduleId);

        if (is_wp_error($config)) {
            return $config;
        }

        if (!isset($config['roots'][$rootIndex])) {
            return new WP_Error('invalid_root', __('Selected folder is not available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $root = $config['roots'][$rootIndex];
        $base = $this->paths->resolveSelectedBase($root['source'], $root['path']);

        if (is_wp_error($base)) {
            return $base;
        }

        $requestedPath = $this->paths->normalizeRelativePath(rawurldecode($requestedPath));

        if ($requestedPath === null) {
            return new WP_Error('invalid_path', __('Invalid path.', 'modularity-folder-browser'), ['status' => 403]);
        }

        $file = $this->paths->resolveInside($base, $requestedPath, false);

        if (is_wp_error($file)) {
            return $file;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($extension, $config['allowed_extensions'], true)) {
            return new WP_Error('forbidden_file_type', __('This file type is not available for download.', 'modularity-folder-browser'), ['status' => 403]);
        }

        $filename = sanitize_file_name(basename($file));
        $mime = wp_check_filetype($filename)['type'] ?: 'application/octet-stream';

        if (ob_get_level()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /**
     * @return array|WP_Error
     */
    private function getModuleConfig(int $moduleId)
    {
        if ($moduleId <= 0 || get_post_status($moduleId) === false) {
            return new WP_Error('invalid_module', __('Selected document browser is not available.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $fields = function_exists('get_fields') ? (array) get_fields($moduleId) : [];
        $roots = $this->normalizeRoots($fields['start_folders'] ?? []);

        if ($roots === []) {
            return new WP_Error('empty_roots', __('No folders have been selected for this document browser.', 'modularity-folder-browser'), ['status' => 404]);
        }

        $sortOrder = $this->normalizeSortOrder((string) ($fields['sort_order'] ?? 'name_asc'));
        $override = isset($fields['allowed_file_types_override']) && is_array($fields['allowed_file_types_override'])
            ? $fields['allowed_file_types_override']
            : [];

        return [
            'roots' => $roots,
            'sort_order' => $sortOrder,
            'allowed_extensions' => $this->scanner->getAllowedExtensions($moduleId, $override),
        ];
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

                $displayName = $path !== ''
                    ? basename($path)
                    : ($this->sources->getSource($source)['label'] ?? __('Documents', 'modularity-folder-browser'));

                $normalized[] = [
                    'source' => $source,
                    'path' => $path,
                    'label' => $displayName,
                    'initially_expanded' => !empty($root['initially_expanded']),
                ];
            }
        }

        return $normalized;
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
}
