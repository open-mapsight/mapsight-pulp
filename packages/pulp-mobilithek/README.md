# Pulp Mobilithek

Shared Mobilithek subscription source helper for Pulp pipelines. DATEX
packages re-export the same `srcMobilithek()` util so a subscription GET
is configured the same way everywhere.

## Features

- **Mobilithek src helper:** Configures `Pulp::srcHttp` with the default
  subscription URL, `Accept-Encoding: gzip`, and P12 client-cert curl options.
  Certificate path, password, and subscription ID stay caller-supplied.
- **Guzzle defaults:** Same gzip, timeout, and query-parameter defaults used
  by `mapsight/pulp-datex-energy`, `mapsight/pulp-datex-fuel`, and
  `mapsight/pulp-datex-roadworks`.

## Installation

```bash
composer require mapsight/pulp-mobilithek
```

This package depends on `mapsight/pulp`. The DATEX packages already require
it and expose the same methods on their facades.

## Fetch a subscription

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpCache;
use OpenMapsight\PulpMobilithek;

$source = Pulp::start()
    ->pipe(PulpMobilithek::srcMobilithek(
        subscriptionId: $subscriptionId,
        certPath: $certPath,
        certPassword: $certPassword,
        ifModifiedSince: $ifModifiedSince,
        aliasFileName: 'mobilithek.xml',
        guzzleOptions: ['timeout' => 180, 'http_errors' => false],
        options: ['sink' => true, 'successStatuses' => [200, 304]],
    ));

$files = Pulp::start()
    ->pipe(PulpCache::remember($source, __DIR__ . '/cache', [
        'key' => 'mobilithek-subscription',
        'ttl' => 86400,
        'fallbackToStale' => true,
    ]))
    ->run();
```

`PulpDatexEnergy::srcMobilithek()`, `PulpDatexFuel::srcMobilithek()`, and
`PulpDatexRoadworks::srcMobilithek()` call this helper. Use whichever
facade you already depend on.

Pass `sink => true` on `srcMobilithek()` (a core `Pulp::srcHttp` option)
when the response should stay a path-backed file instead of a PHP string.

## Notes

- Certificate path, password, and subscription ID stay caller-supplied.
- `srcMobilithek()` only configures `Pulp::srcHttp`. Cache with `PulpCache::remember`.
