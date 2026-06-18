# Pulp Monorepo

Monorepo for Pulp packages - a stream-based file processing library for PHP, inspired by Gulp.

## Packages

- **[pulp](packages/pulp):** Core library providing the stream-based processing engine.
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

1. **GitHub Action:** The `.github/workflows/release.yml` workflow runs on every push to `main` and every new tag.
2. **Splitting:** It uses the `danharrin/monorepo-split-github-action` to split each directory in `packages/` into its own repository.
3. **Tags:** When you tag the monorepo (e.g., `git tag v1.0.0 && git push --tags`), the workflow will also tag the split repositories with the same version.
4. **Packagist:** Once the split repositories are updated and tagged, Packagist will pick up the new versions.

### Repo Host

By default, the release script pushes to **GitHub** (`github.com`). If you ever need to split to a self-hosted instance (like GitLab), you can set the `split-repository-host` parameter in the `.github/workflows/release.yml` file.

### Packagist Integration

1. Get your API Token from your [Packagist profile](https://packagist.org/profile/).
2. Add your Packagist username as a secret named `PACKAGIST_USER` and your API Token as `PACKAGIST_TOKEN` in this monorepo's GitHub settings.
3. The release workflow will then notify Packagist via `curl` after each split.

### Setup Requirements

To make the release workflow work, you need to:

1. **Create the Target Repositories:** Ensure that all repositories (e.g., `open-mapsight/pulp`, `open-mapsight/pulp-geojson`, etc.) exist in the `open-mapsight` organization.
2. **GitHub Personal Access Token:** Create a GitHub Personal Access Token (PAT) with `repo` scope.
3. **Repository Secret:** Add the PAT as a secret named `ACCESS_TOKEN` in this monorepo's GitHub settings (`Settings > Secrets and variables > Actions`).
