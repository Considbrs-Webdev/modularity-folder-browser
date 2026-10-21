# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-22

### Added

- Folder Browser module for Modularity: show selected server folders as an accessible, downloadable document browser. Editors pick folders from admin-configured sources, and real file paths are never exposed.
- Folder picker in the module settings, with an optional top folder name that wraps the selected folders.
- Single file display mode: show one selected file as a download card instead of a folder tree.
- Search across the selected folders.
- File type, size, modified date and description can be shown or hidden per module. Downloads can be shown as text or as an icon, and files can be sorted by name, date or type.
- File type icons, with an allowed-file-types setting that can narrow the global list per module.
- Support for using the module as a Gutenberg block.
- Swedish translation.
- Filters for sources, allowed file types, icons, cache lifetime and search debounce (see README).

### Fixed

- Folder and file names keep their dashes and underscores.
- Downloads and folder expansion work correctly when a configured folder is no longer available.
