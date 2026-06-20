# Pulp Monorepo

Monorepo for Pulp packages - a stream-based file processing library for PHP, inspired by Gulp.

## Packages

- **[pulp](packages/pulp):** Core library providing the stream-based processing engine.
- **[pulp-cache](packages/pulp-cache):** Generic caching helpers for Pulp pipelines.
- **[pulp-gtfs](packages/pulp-gtfs):** GTFS helpers for generating stops and lines GeoJSON.
- **[pulp-zip](packages/pulp-zip):** ZIP archive helpers for Pulp pipelines.
- **[pulp-geojson](packages/pulp-geojson):** Tools for handling, transforming, and converting GeoJSON data.
- **[pulp-xml](packages/pulp-xml):** Handlers for parsing and manipulating XML data.
- **[pulp-json](packages/pulp-json):** Handlers for JSON and JSONP decoding and encoding.
- **[pulp-geocsv](packages/pulp-geocsv):** Utility to convert CSV files with geographic data to GeoJSON.
- **[pulp-soap](packages/pulp-soap):** SOAP source handler for Pulp streams.
- **[pulp-tic](packages/pulp-tic):** Specialized handlers for parsing Traffic Information Center (TIC) XML data.
- **[pulp-concert](packages/pulp-concert):** Specialized handlers for parsing Concert XML data (traffic, parking, etc.).
- **[geojson-reproject](packages/geojson-reproject):** Utility for re-projecting GeoJSON coordinates.

## Installation

Install dependencies for all packages from the root:

```bash
composer install
```

## Testing

You can run tests for each package individually. Navigate to the package directory and run `composer test`:

```bash
cd packages/pulp
composer test
```

To run tests for all packages that have them, run the following command from the root:

```bash
composer test
```

GitHub Actions runs the full test suite on every pull request and push to `main` across the supported PHP versions: 8.2, 8.3, 8.4, and 8.5. It also runs a non-blocking `nightly` job so upcoming PHP compatibility issues show up early.

PHPUnit is configured to fail on notices, warnings, deprecations, PHPUnit warnings, and PHPUnit deprecations from the package source. Indirect dependency deprecations are ignored, so third-party internals do not hide Pulp compatibility issues.

To reproduce the supported PHP matrix locally with Docker, run:

```bash
bin/test-php-matrix
```

To run only selected versions, set `PHP_VERSIONS`:

```bash
PHP_VERSIONS="8.4 8.5" bin/test-php-matrix
```

Or run the PHP script directly:
```bash
php test.php
```

## Code Quality

Run PHP CS Fixer:
```bash
vendor/bin/php-cs-fixer fix
```

Run Rector:
```bash
vendor/bin/rector process
```

## Releasing

This monorepo is set up to automatically split and release packages to separate repositories in the `open-mapsight` organization on GitHub.

### How it works

1. **GitHub Action:** The `.github/workflows/release.yml` workflow runs on every push to `main` and every package release tag.
2. **Splitting:** It uses the `danharrin/monorepo-split-github-action` to split each directory in `packages/` into its own repository.
3. **Main branches:** Every push to `main` updates the `main` branch in each split repository.
4. **Package tags:** Package releases use scoped monorepo tags in the form `<package>@v<version>` (for example, `pulp-json@v1.1.0`). The workflow strips the package prefix and pushes `v<version>` to only that package's split repository.
5. **Packagist:** Once the split repositories are updated and tagged, Packagist will pick up the new versions.

### Releasing a Package

Use package-scoped tags so each package can follow its own semantic version:

```bash
git tag pulp-json@v1.1.0
git push origin pulp-json@v1.1.0
```

This releases `mapsight/pulp-json` as `v1.1.0` without changing the versions of the other packages.

### Repo Host

By default, the release script pushes to **GitHub** (`github.com`). If you ever need to split to a self-hosted instance (like GitLab), you can set the `split-repository-host` parameter in the `.github/workflows/release.yml` file.

### Packagist Integration

1. Get your API Token from your [Packagist profile](https://packagist.org/profile/).
2. Add your Packagist username as a secret named `PACKAGIST_USER` and your API Token as `PACKAGIST_TOKEN` in this monorepo's GitHub settings.
3. The release workflow will then notify Packagist via `curl` after each split.

### Adding a New Package to the Release

When adding a package under `packages/`, make sure the split release target is ready before merging to `main`:

1. **Add the package directory:** Create `packages/<package-name>` with its own `composer.json` and README.
2. **Add it to the release matrix:** Add `<package-name>` to `.github/workflows/release.yml`.
3. **Create and initialize the target repository:** Create `open-mapsight/<package-name>` and make sure it has an initialized `main` branch. An empty initial commit is enough; the split action cannot push to a completely empty repository with no `main` ref.
4. **Grant token access:** Make sure the `ACCESS_TOKEN` PAT used by this monorepo can write to the new target repository.
5. **Set up Packagist:** Add the package on Packagist so the release workflow's update notification can refresh it after each split.
