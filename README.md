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
