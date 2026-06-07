<?php

namespace ModularityFolderBrowser\Service;

class InstanceTokenStore
{
    private const TRANSIENT_PREFIX = 'mod_fb_instance_';

    public function __construct(private ?FileExtensionPolicy $extensions = null)
    {
        $this->extensions = $this->extensions ?? new FileExtensionPolicy();
    }

    public function create(array $config, int $moduleId = 0): string
    {
        $token = $this->generateToken();
        $ttl = max(60, (int) apply_filters(
            'Modularity/Module/FolderBrowser/InstanceToken/CacheTTL',
            DAY_IN_SECONDS,
            $moduleId
        ));

        set_transient($this->transientKey($token), $this->sanitizeConfig($config), $ttl);

        return $token;
    }

    public function get(string $token): ?array
    {
        if (!$this->isValidToken($token)) {
            return null;
        }

        $config = get_transient($this->transientKey($token));

        if (!is_array($config)) {
            return null;
        }

        $config = $this->sanitizeConfig($config);

        return $config['roots'] === [] ? null : $config;
    }

    public function isValidToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return strtolower(wp_generate_password(32, false, false));
        }
    }

    private function transientKey(string $token): string
    {
        return self::TRANSIENT_PREFIX . $token;
    }

    private function sanitizeConfig(array $config): array
    {
        $roots = [];

        foreach ((array) ($config['roots'] ?? []) as $root) {
            if (!is_array($root)) {
                continue;
            }

            $source = sanitize_key((string) ($root['source'] ?? ''));
            $path = $this->normalizeRelativePath((string) ($root['path'] ?? ''));
            $label = sanitize_text_field((string) ($root['label'] ?? ''));

            if ($source === '' || $path === null) {
                continue;
            }

            $roots[] = [
                'source' => $source,
                'path' => $path,
                'label' => $label,
                'initially_expanded' => !empty($root['initially_expanded']),
            ];
        }

        return [
            'module_id' => max(0, (int) ($config['module_id'] ?? 0)),
            'roots' => $roots,
            'sort_order' => $this->normalizeSortOrder((string) ($config['sort_order'] ?? 'name_asc')),
            'allowed_extensions' => $this->extensions->sanitizeExtensions((array) ($config['allowed_extensions'] ?? [])),
        ];
    }

    private function normalizeSortOrder(string $sortOrder): string
    {
        $allowed = ['name_asc', 'name_desc', 'date_desc', 'date_asc', 'type_asc'];

        return in_array($sortOrder, $allowed, true) ? $sortOrder : 'name_asc';
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', wp_unslash($path));
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
