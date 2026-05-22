# Pulp Monorepo

Monorepo for Pulp packages - a stream-based file processing library for PHP, inspired by Gulp.

## Packages

- **[pulp](packages/pulp):** Core library providing the stream-based processing engine.
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

This monorepo is set up to automatically split and release packages to separate repositories in the `mapsight` organization on GitHub.

### How it works

1. **GitHub Action:** The `.github/workflows/release.yml` workflow runs on every push to `main` and every new tag.
2. **Splitting:** It uses the `danharrin/monorepo-split-github-action` to split each directory in `packages/` into its own repository.
3. **Tags:** When you tag the monorepo (e.g., `git tag v1.0.0 && git push --tags`), the workflow will also tag the split repositories with the same version.
4. **Packagist:** Once the split repositories are updated and tagged, Packagist will pick up the new versions.

### Repo Host

By default, the release script pushes to **GitHub** (`github.com`). If you ever need to split to a self-hosted instance (like GitLab), you can set the `split-repository-host` parameter in the `.github/workflows/release.yml` file.

### Packagist Integration

There are two ways to ensure Packagist stays up to date:

#### 1. GitHub Webhooks (Recommended)
This is the standard and easiest way. For each split repository (e.g., `mapsight/pulp`):
1. Log in to [Packagist.org](https://packagist.org).
2. Submit the repository URL if you haven't already.
3. In the package page on Packagist, look for "GitHub Hook" and follow the instructions to set up a webhook in your GitHub repository settings. This will notify Packagist automatically whenever a split occurs.

#### 2. Packagist API Token
If you prefer to trigger the update directly from this GitHub Action:
1. Get your API Token from your [Packagist profile](https://packagist.org/profile/).
2. Add it as a secret named `PACKAGIST_TOKEN` in this monorepo's GitHub settings.
3. Add a step to `.github/workflows/release.yml` to notify Packagist via `curl`.

Example step to add after the split:
```yaml
      - name: Notify Packagist
        run: curl -XPOST -H'Content-Type:application/json' "https://packagist.org/api/update-package?username=YOUR_USERNAME&apiToken=${{ secrets.PACKAGIST_TOKEN }}&repository=https://github.com/mapsight/${{ matrix.package }}"
```

### Setup Requirements

To make the release workflow work, you need to:

1. **Create the Target Repositories:** Ensure that all repositories (e.g., `mapsight/pulp`, `mapsight/pulp-geojson`, etc.) exist in the `mapsight` organization.
   - You can use the provided script to automate this:
     ```bash
     ./setup-repos.sh
     ```
     *(Requires [GitHub CLI](https://cli.github.com/) and being logged in to the `mapsight` organization)*
2. **GitHub Personal Access Token:** Create a GitHub Personal Access Token (PAT) with `repo` scope.
3. **Repository Secret:** Add the PAT as a secret named `ACCESS_TOKEN` in this monorepo's GitHub settings (`Settings > Secrets and variables > Actions`).
