# Pulp ZIP

ZIP helpers for Pulp packages and scripts.

## Features

- **Unzip in pipelines:** Expand ZIP payloads from a Pulp `File` into regular Pulp files.
- **Zip in pipelines:** Collect regular Pulp files into one ZIP archive.
- **Temporary file handling:** Uses temporary files internally because PHP `ZipArchive` works with paths.

## Installation

```bash
composer require mapsight/pulp-zip
```

Requires PHP `ext-zip`.

## Usage

Use `PulpZip::unzip()` after any source handler that emits ZIP bytes:

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpZip;

Pulp::start()
    ->pipe(Pulp::srcHttp('GET', $url, [], 'data.zip'))
    ->pipe(PulpZip::unzip('.*\.csv'))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

`unzip($patterns)` emits each matching archive entry as a normal Pulp `File`.
Patterns use Pulp's regular-expression file matching, the same as `Pulp::fileSwitch()`.

Use `PulpZip::zip()` to collect incoming files into one archive:

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.json', __DIR__ . '/data'))
    ->pipe(PulpZip::zip('export.zip'))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

## Error Handling

The handlers throw `RuntimeException` when:

- A temporary ZIP file cannot be created.
- The ZIP archive cannot be opened.
- A ZIP entry path is unsafe, for example an absolute path or a path containing `..`.
- A cache or temp file cannot be read or written by the caller.

## Notes

- This package intentionally keeps the API small. It is not a full archive extraction library.
- For large ZIP files, prefer combining this package with `mapsight/pulp-cache` so downloads are cached before parsing.
