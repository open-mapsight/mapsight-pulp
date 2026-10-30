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

## Drain a delta subscription

`srcMobilithek()` fetches a single response. For delta feeds use
`drainMobilithek()` instead: Mobilithek returns the **next** buffered packet
following `If-Modified-Since`, not the latest one. Fetching only once per job
can build an ever-growing backlog.

```php
$files = Pulp::start()
    ->pipe(PulpMobilithek::drainMobilithek(
        subscriptionId: $subscriptionId,
        certPath: $certPath,
        certPassword: $certPassword,
        ifModifiedSince: $cache['lastModified'] ?? null,
        aliasFileName: 'status.json',
        options: ['maxPackets' => 200],
    ))
    ->pipe(\OpenMapsight\PulpDatexEnergy::accumulateStatus($cachePath))
    ->run();
```

- Without a cursor the first request uses 1970 to retrieve the oldest buffered
  snapshot, as specified in [Mobilithek TSSB section 4.8](https://mobilithek.info/cms/downloads/tssb-de).
- Emits each 200 response separately with `httpLastModified`, `httpEtag`, and
  `httpType`. Stops on 304/204 or after `maxPackets` ordered responses (default 200).
- The optional final request without a cursor (`probeLatest`, default true)
  deduplicates the head by ETag (body hash fallback). It runs only after reaching
  304/204, never when the cap has interrupted the backlog.
- Probe files carry `mobilithekAdvanceCursor = false`: the accumulator must apply
  their point updates **without advancing the sequential cursor**. A packet may
  have arrived between the 304 and the probe, leaving earlier deltas unread.
- Missing/non-advancing Last-Modified, HTTP failures, and download failures throw.
  Downstream accumulators should commit their cursor only when the run completes.
- Time-based pull cannot retrieve every historical packet from the same second.
  The head probe mitigates collisions; it does not guarantee lossless delivery.
- Serialize runs. Consumers must apply snapshots and deltas in order and ignore
  older point timestamps. `PulpDatexEnergy::accumulateStatus()` implements this.

Push requires an application HTTPS receiver with client-certificate verification
and a durable inbox; this package is a pipeline library, not an HTTP server.
A receiver can enqueue files for the same Pulp accumulator. Acknowledge only
persisted packets and monitor delivery: after two failed push attempts Mobilithek
can suspend deliveries until the next full packet (TSSB section 4.7.2).

## Durable push inbox (optional PDO SQLite extension)

The library does not terminate TLS or run an HTTP server. An application's
certificate-authenticated HTTPS receiver can persist a decoded packet with:

```php
use OpenMapsight\pulpmobilithek\PushInbox;

$inbox = new PushInbox('/private/persistent-local-directory/inbox.sqlite');
$inbox->enqueue($json, ['subscriptionId' => $subscriptionId]);
// Return HTTP 200 with an empty body only after enqueue succeeds.
```

The parent directory must already exist, be writable and private. Use persistent
local disk outside the web root; do not use NFS. SQLite uses a rollback journal
and `synchronous=EXTRA`. Concurrent enqueue calls serialize their capacity checks
and inserts. The default limit is 256 MiB / 10,000 pending packets; exhaustion
throws so the receiver can return an error rather than silently drop data.

`enqueue()` returns false for a duplicate subscription/body hash, including a
packet already processed within the seven-day retry receipt window. Both new
and duplicate persisted deliveries may be acknowledged. Hash the decompressed
body by passing decoded JSON, so gzip recompression does not defeat retry dedup.

```php
$inbox->consume(function (array $rows): void {
    // Apply rows in order through Pulp and publish the resulting GeoJSON.
    // Return only after all durable state/output writes succeed.
    // Throw on failure; the inbox will retain the complete batch for retry.
}, limit: 200);
```

Only one consumer enters the callback at a time. The callback runs without a
SQLite transaction, so incoming deliveries continue while processing is slow.
After it returns, the batch is marked processed and its large bodies released.
A crash before acknowledgment can replay a batch: use an idempotent accumulator,
keep push and pull cursors separate, and serialize shared state writers. File
outputs must also be synced before returning; a database receipt alone cannot
make an unsynced output file durable. `stats()` exposes safe pending depth/bytes,
oldest pending age, and latest receipt/processing times without payloads.

When mixing push with pull, pass publication timestamps to the DATEX accumulator
(the handler extracts them automatically). Its snapshot watermark prevents old
snapshot retries or pre-baseline deltas from deleting/resurrecting newer state.

## Notes

- Certificate path, password, and subscription ID stay caller-supplied.
- `srcMobilithek()` only configures `Pulp::srcHttp`. Cache with `PulpCache::remember`.
