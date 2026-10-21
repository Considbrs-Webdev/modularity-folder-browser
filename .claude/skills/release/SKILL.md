---
name: release
description: Cut a new version of the modularity-folder-browser plugin - bump the version, update CHANGELOG.md, commit, merge dev into main, tag, and publish a GitHub release. Use when the user asks to "release", "cut a version", "bump the version", or "tag a release".
---

# Cutting a release

This plugin has no build/asset step tracked in git (`/assets/` is gitignored — see
"Known gap" at the end). A release here just means: bump the version everywhere it's
declared, write a changelog entry, tag it, and publish GitHub release notes.

Version lives in **three** places and must move together:
- `modularity-folder-browser.php` — ` * Version: X.Y.Z` plugin header
- `package.json` — `"version": "X.Y.Z"`
- `package-lock.json` — two `"version"` fields (root + `packages[""]`)

`composer.json` intentionally has **no** `version` field (Composer reads it from the git tag) —
don't add one.

## 1. Figure out the next version

```bash
git fetch --tags
git describe --tags --abbrev=0          # current version (fails if no tag exists yet)
git log --oneline <last-tag>..dev        # unreleased changes on dev
```

If there is no tag yet, use the version declared in the plugin header as the baseline and
read the full `git log`.

Pick the bump by semver: bug fixes only → patch, backward-compatible features → minor,
breaking change → major. Breaking means renaming/removing a public filter
(`Modularity/Module/FolderBrowser/...`), a REST route, an ACF field name or the module slug
(see CLAUDE.md). When in doubt, ask the user rather than guessing.

## 2. Draft the changelog entry — confirm before writing

Summarize the unreleased commits into **user-facing** bullets (not implementation detail).
Group under `### Added` / `### Changed` / `### Fixed` as needed, Keep-a-Changelog style.
Show the user the draft entry and the chosen version number before proceeding — this is the
one judgment call in the process worth a pause.

## 3. Apply the version bump

```bash
# modularity-folder-browser.php: edit the " * Version:" header directly
npm version <X.Y.Z> --no-git-tag-version --allow-same-version   # syncs package.json + package-lock.json together
```

Insert the confirmed changelog entry at the top of `CHANGELOG.md` (after the header, before
the previous latest entry).

## 4. Verify

```bash
php -l modularity-folder-browser.php
npm run build       # confirm the frontend still builds even though dist isn't committed
npm run i18n        # only if translatable strings changed; needs wp-cli. Make sure no msgstr is empty
```

There is no test suite — don't invent `composer test`.

## 5. Commit

Behavior changes get their own commit(s) before the release (don't bundle a feature/fix into
the release commit). The release commit touches only the version files and changelog:

```bash
git add modularity-folder-browser.php package.json package-lock.json CHANGELOG.md
git commit -m "Release X.Y.Z"
```

## 6. Push, merge to main, tag — confirm before this step

This pushes to the shared remote and publishes a public release; confirm with the user
before running it (unless they already explicitly asked for the full release including
merge + publish in this same request).

```bash
git push origin dev

git checkout main
git merge --ff-only dev     # dev and main should never have diverged; if this fails, stop and investigate rather than force
git push origin main

git tag -a X.Y.Z -m "Release X.Y.Z"
git push origin X.Y.Z

git checkout dev
```

## 7. Publish the GitHub release

Title `vX.Y.Z`, body is the changelog entry verbatim (starting with `## [X.Y.Z] - date`).

```bash
gh release create X.Y.Z --repo Considbrs-Webdev/modularity-folder-browser \
  --title "vX.Y.Z" \
  --notes "$(cat <<'EOF'
## [X.Y.Z] - YYYY-MM-DD

### Added
- ...
EOF
)"
```

## Known gap: Composer installs ship no built assets

`.gitignore` excludes `/assets/`, including the Vite build output (`assets/dist/`). A site
requiring this plugin via Composer from GitHub gets no compiled JS/CSS, and the plugin
silently skips enqueueing when `assets/dist/` is missing. `build.php` builds on the deploy
side; nothing in this release process commits built assets. If asked to address it, the
likely direction (confirm with the user first): keep `dev` source-only, but have CI build and
commit `assets/dist/` onto `main` as part of the release, before tagging.
