# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Two things share one repository (intentionally — don't propose splitting it):

1. **A Symfony Flex contrib recipe repository** for UFO-Tech bundles (`index.json` + `recipes/*.json`). Flex reads `index.json` from `github.com/UFO-Tech/recipes` (branch `main`) and fetches recipes via the GitHub contents API using `recipe_template`.
2. **The `ufo-tech/packages` Composer library** (`src/UfoPackage.php`, namespace `Ufo\Packages\`), a runtime helper other UFO-Tech bundles depend on. `.gitattributes` keeps the generator, recipes and tests out of its dist archive.

## Commands

Everything runs inside the `php_recipe` Docker container (`ufotech/php:8.4-fpm`, repo mounted at `/app`); there is no local PHP.

```bash
make up-d          # start container
make composer-i    # install deps
make test          # PHPUnit (tests/)
make check         # generator dry run: exit 1 if index.json/recipes/ are out of date
make generate      # regenerate index.json and recipes/ (writes + deletes stale recipes)
docker exec php_recipe vendor/bin/phpunit --filter GeneratorTest   # single test
```

`GITHUB_TOKEN` from the host is passed into the container (used only for the GitHub fallback).

`make commit-a` amends the last commit, **force-pushes**, and re-creates a remote tag. Interactive and destructive — never run it unless explicitly asked.

## Recipe generation

`bin/generate.php` → `Generator` builds everything in memory, `JsonDumper` diffs/writes.

- **Package list:** `configs/packages.php` (`true` = needs a recipe). Short names get the vendor prefix. The same file is read at runtime by `UfoPackage`, so it ships with the library.
- **Generator settings:** `configs/generator.php` — vendor, recipes repo, branch, dirs, per-package `min_version` / `prerelease` / `github`.
- **Versions:** `ChainVersionSource` → Packagist p2 metadata first, GitHub tags (paginated) only if the package isn't on Packagist. `VersionTags::minors()` collapses to `X.Y`, drops pre-releases, and **sorts ascending — Flex depends on this order** (it iterates the index reversed and picks the first minor ≤ installed).
- **Recipe sources:** `configs/recipes/<vendor>.<name>/` with `X.Y.json` → `X.json` → `default.json` (most specific wins, whole-file replace) and optional `files/` (embedded into recipe `files`; requires `copy-from-recipe`).
- **Config files contain only the manifest** (`bundles`, `copy-from-package`, `env`, …). The generator adds the `manifests`/`manifest` wrapper, `files` and `ref` (sha1 of content — changes exactly when the recipe changes).
- `ManifestValidator` rejects unknown keys, wrappers, non-FQCN bundle classes, bad env names; any error aborts the run with nothing written.
- A package with no stable releases is left out of `index.json` (an empty list makes Flex report a conflict).
- Never edit `recipes/` or `index.json` by hand — they're generated.

Bundle class names in configs must match the file name case exactly (PSR-4 on Linux is case-sensitive; e.g. `Ufo\EAV\UfoEAVBundle`, not `UfoEavBundle`). The validator can't check this — verify against the bundle's `src/`.

## Conventions

- Comments, config comments and Makefile prompts are in Ukrainian; `README-UA.md` is the Ukrainian twin of `README.md` — keep both in sync.
