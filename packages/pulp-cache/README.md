# Pulp Cache

Generic caching helpers for Pulp pipelines.

## Features

- **Remember expensive sources:** Wrap any source pipeline and reuse cached output on fresh cache hits.
- **Pass-through caching:** Cache any file that flows through a pipeline, independent of where it came from.
- **Stale fallback:** Keep jobs working with the last cached result if an upstream source fails.
- **Multiple file support:** Cache and restore one or more Pulp `File` objects via a manifest.
- **Raw or serialized payloads:** String payloads are stored as raw files; decoded arrays/objects are serialized.

## Installation

```bash
composer require mapsight/pulp-cache
```

## Remember a Source Pipeline

Use `remember()` when you want cache hits to skip an expensive upstream operation, such as a large HTTP download.

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpCache;

$source = Pulp::start()
    ->pipe(Pulp::srcHttp(
        'GET',
        'https://example.com/data.zip',
        ['timeout' => 120],
        'data.zip'
    ));

$files = Pulp::start()
    ->pipe(PulpCache::remember($source, __DIR__ . '/cache', [
        'key' => 'example-data',
        'ttl' => 86400,
        'fallbackToStale' => true,
    ]))
    ->run();
```

On a fresh cache hit, the wrapped `$source` pipeline is not executed.

## Cache Within a Pipeline

Use `cache()` as a regular pass-through handler when the upstream pipeline should still be part of the flow.

```php
Pulp::start()
    ->pipe(Pulp::src('*.geojson', __DIR__ . '/input'))
    ->pipe(PulpCache::cache(__DIR__ . '/cache', [
        'ttl' => 3600,
    ]))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

## Options

- `key` (`string`): Stable cache key. Defaults to `remember` for `remember()` and the current file name for `cache()`.
- `ttl` (`int`): Cache lifetime in seconds. Defaults to `86400`.
- `ttl < 0`: Cache never expires.
- `ttl = 0`: Always refresh/write the cache.
- `fallbackToStale` (`bool`): For `remember()`, return stale cached files if the wrapped source fails. Defaults to `true`.

## Cache Layout

Each cache key gets its own directory:

```text
cache/
  example-data/
    manifest.json
    0000.zip
    0001.json
```

`manifest.json` stores the original Pulp file names, payload encoding, and cache file paths.

## Notes

- Keep cache keys stable and independent of secrets.
- Do not use a public web directory as the cache directory if cached input contains private URLs, credentials, or paid data.
- `remember()` is best for source/subpipeline caching. `cache()` is best for caching an intermediate file in an already-running pipeline.
