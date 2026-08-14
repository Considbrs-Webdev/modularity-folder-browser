# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin that adds a "Folder Browser" module to the Modularity plugin framework. It exposes selected server filesystem folders as a public, accessible document browser: editors pick folders from admin-configured "sources" (safe filesystem roots defined in code, not by users), visitors browse the resulting folder tree and download allowed files, and real filesystem paths are never exposed in HTML, JSON, or download URLs.

Requires WordPress, Modularity, ACF Pro, and the Municipio component library (for Blade view rendering). It is not a standalone plugin — it registers a module into the Modularity module system and has no functionality on its own.

## Commands

```bash
composer install       # PHP autoloading (PSR-4: ModularityFolderBrowser\ -> source/php/)
npm install
npm run build           # vite build -> assets/dist (production)
npm run dev              # vite build --watch --mode development

npm run i18n              # regenerate .pot, .mo, .php translation files (requires wp-cli)
npm run i18n:pot
npm run i18n:mo
npm run i18n:php
```

There is no test suite, linter config, or CI config in this repo — don't invent commands for these.

`build.php` is a deploy-time script (not for local dev) that runs composer/npm install+build and can strip dev-only files (`--cleanup`) for production packaging.

Assets are cache-busted via `assets/dist/manifest.json` (`Helper\CacheBust`), produced by `vite-config-factory` (see `vite.config.mjs`). PHP enqueue code (`App.php`) reads the manifest to find the current hashed filename — if `assets/dist/` isn't built, enqueue silently no-ops (and prints a warning when `WP_DEBUG` is on).

## Architecture

### Security model — read this before touching path handling

The entire plugin is built around one invariant: **absolute filesystem paths must never reach the browser or leave `source/php`.** Everything the frontend/admin JS receives is relative paths, opaque tokens, and REST-generated download URLs.

- **Sources** (`Service/SourceRepository`) are the only place absolute paths are configured — via the `MODULARITY_FILE_BROWSER_SOURCES` constant or the `Modularity/Module/FolderBrowser/Sources` filter. Sources are validated (`realpath()`, must be a readable directory, must be absolute) and invalid ones are silently dropped.
- **All relative paths** from requests go through `Service/PathResolver::normalizeRelativePath()` (rejects absolute paths, `.`/`..` segments, dotfiles, null bytes) and then `resolveInside()`, which re-resolves via `realpath()` and checks the result is still inside the source's base path — this is the traversal guard, applied on every request, not just at input time.
- **File extensions** are policed independently by `Service/FileExtensionPolicy`, which has a hardcoded blocklist (`php`, `sh`, `exe`, etc.) that cannot be overridden by filters, plus a filterable allowlist (`Modularity/Module/FolderBrowser/AllowedFileTypes`). Per-module overrides can only narrow the global allowlist, never widen it.
- Any change to `PathResolver`, `SourceRepository`, or `FileExtensionPolicy` needs to preserve these invariants — this is the plugin's actual attack surface (path traversal, arbitrary file read/download, extension bypass).

### Two ways a module instance gets configuration: module_id vs instance_token

Persisted Modularity modules have a real `module_id` (a post ID) and REST requests fetch config live from ACF fields via `get_fields($moduleId)`.

Inline Gutenberg block instances have no persisted post, so `module_id=0` is used along with an opaque `instance_token`. `Service/InstanceTokenStore` snapshots the resolved config (roots, sort order, allowed extensions) into a WP transient at render time (`Module/FileBrowser::createInstanceToken()`), and REST requests pass the token back to look up that frozen config (`Rest/RestController::getModuleConfig()`). This is why REST endpoints for `tree`, `search`, and `download` all accept both `module_id` and `instance_token` — check both code paths when changing config resolution.

### Request flow

1. `App.php` wires WP hooks: registers the module (`init`), enqueues public/admin scripts+styles, and boots `AcfFields\AcfFieldLoader` and `Rest\RestController`.
2. `Module\FileBrowser` (extends `Modularity\Module`) is the module itself — `data()` builds the initial server-rendered view model (resolves roots, does the *first* directory listing eagerly so the initial page has content without a JS round-trip), `template()` points at `Module/views/file-browser.blade.php`.
3. After initial render, all further folder expansion, search, and downloads happen through REST (`Rest\RestController`), backed by the same service classes (`SourceRepository`, `PathResolver`, `DirectoryScanner`, `InstanceTokenStore`) used at initial render — the two code paths must stay consistent since they authorize/resolve the same data.
4. `Service\DirectoryScanner` does the actual filesystem walk (`DirectoryIterator`), builds folder/file listings, sorts them (`sortItems()` — supports name/date/type ordering), and caches per-directory listings in a transient keyed by directory+module+root+sort+extensions+instance_token (`Modularity/Module/FolderBrowser/CacheTTL` filter, default 10 minutes).
5. Downloads stream through `Rest\RestController::download()` — it re-validates path and extension, then `readfile()`s the resolved file with a forced `Content-Disposition: attachment`, never redirecting to a real path.

### ACF fields

`AcfFields/json/instance-settings.json` / `AcfFields/php/instance-settings.php` are ACF's auto-exported field group definitions (managed by `AcfExportManager`, wired in `modularity-folder-browser.php`'s `acf/init` hook — don't hand-edit the generated PHP export without also updating the JSON, or re-export from ACF UI). `AcfFields\AcfFieldLoader` dynamically populates two field choice lists at edit time: the `source` field's choices from `SourceRepository::getSourceChoices()`, and the allowed-file-types override field's choices from `FileExtensionPolicy`'s current allowlist — both are skipped when editing the field group itself to avoid chicken-and-egg issues.

### Frontend JS (vanilla, no framework)

- `source/js/modularity-folder-browser.js` — public-facing folder tree/search/download UI, talks to the public REST endpoints (`/tree`, `/search`, `/download`), reads config from the `ModularityFolderBrowser` localized object (`App::enqueueScripts()`).
- `source/js/modularity-folder-browser-admin.js` — ACF admin folder-picker modal (lazy-loaded checkbox tree), talks to the `/admin/folders` REST endpoint, reads config from `ModularityFolderBrowserAdmin` (`App::enqueueAdminScripts()`, includes a REST nonce since this endpoint requires `edit_posts`).
- Both are separate Vite entries (see `vite.config.mjs`) — there's no shared bundle/module between them.

### Extension points

The plugin's public API is entirely WordPress filters (documented in full with examples in `README.md`): `Modularity/Module/FolderBrowser/Sources`, `.../AllowedFileTypes`, `.../FileIcon`, `.../FolderIcon`, `.../DownloadIcon`, `.../CacheTTL`, `.../InstanceToken/CacheTTL`, and `.../Frontend/FilterDebounce`. When adding a new configurable behavior, follow this pattern rather than inventing constants or options.

`Helper/IconResolver` maps file extensions to icon categories (archive, audio, image, etc., backed by `source/icons/*.svg`) and exposes both the resolved map and filters for overriding individual icons.
