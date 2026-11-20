# Pulp OGC

Read OGC Web Feature Services (WFS) as GeoJSON in Pulp pipelines. The client pages
through a feature type and only returns a collection once every page has arrived
and the server's reported feature count matches. It reports server exceptions with
their OGC code and enforces request limits. Requires PHP 8.2 or later.

## Installation

```bash
composer require mapsight/pulp-ogc
```

## Example

Read a feature type in its native projected CRS, reproject it locally, and publish
only the properties you choose, under ids that stay the same between imports:

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpOgc;
use OpenMapsight\pulpogc\WfsClient;

$wfs = new WfsClient('https://maps.example.org/geoserver/ows');

Pulp::start()
    ->pipe(PulpOgc::srcWfs($wfs, 'demo:districts', 'districts.geojson', [
        'srsName' => 'EPSG:25832',
        'sortBy' => 'district_no',
        'pageSize' => 1000,
    ]))
    ->pipe(PulpGeoJSON::reproject('EPSG:4326', ['includeZCoordinate' => false]))
    ->pipe(PulpGeoJSON::map(static fn(array $feature): array => [
        'type' => 'Feature',
        'id' => 'district-' . $feature['properties']['district_no'],
        'geometry' => $feature['geometry'],
        'properties' => ['name' => $feature['properties']['name']],
    ]))
    ->pipe(PulpJSON::encodeJSON())
    ->pipe(Pulp::dest($outputDirectory))
    ->run();
```

`mapsight/pulp-geojson` and `mapsight/pulp-json` are separate packages. Install
them for the reprojection, mapping and encoding steps in this example.

## Reading features

`WfsClient::getFeature($typeName, $request)` returns a decoded GeoJSON
FeatureCollection. `PulpOgc::srcWfs($client, $typeName, $fileName, $request)`
emits the same collection as one Pulp file.

The result contains `type`, `features` and, when known, a GeoJSON 2008 named `crs`
member. The `crs` comes from the response or, if the response has none, from the
requested `srsName`. `PulpGeoJSON::reproject()` reads it as the source projection.
Features are passed through as the server sent them, including server-generated
ids and extra members such as `geometry_name`. Server metadata such as
`numberMatched`, `timeStamp` and per-page `bbox` is checked and then dropped.

| Request key | Meaning |
| --- | --- |
| `srsName` | Output CRS (`SRSNAME`). The response must declare the same CRS; spellings such as `EPSG:25832` and `urn:ogc:def:crs:EPSG::25832` are equal. |
| `bbox`, `bboxCrs` | Four numbers, plus an optional CRS name (WFS 1.1.0 and 2.0.0). |
| `propertyNames` | List of properties to return (`PROPERTYNAME`). Include the geometry property if you need geometries. |
| `sortBy` | Passed as `SORTBY`. The syntax depends on the WFS version, for example `name ASC` in 2.0.0. |
| `pageSize` | Read in pages of this size using `COUNT` and `STARTINDEX`. WFS 2.0.0 only. |
| `limit` | Read at most this many features. If the server reports its matched count, exactly the smaller of the two must arrive. |
| `params` | Vendor parameters such as `CQL_FILTER` or `FILTER`. They cannot override parameters the client sets. |

Unknown request keys and client options throw, so a typo cannot silently drop a
filter.

## Completeness and paging

A read either returns every matching feature or throws. The client checks:

- The server's `numberMatched` (or GeoServer's `totalFeatures`) against the
  number of features read. Servers cap responses (GeoServer's "maximum number of
  features", for example), and without this check a capped response looks like a
  complete one. If a read fails this check, set a `pageSize` below the server's cap.
- That `numberReturned` matches the page and that no page holds more features than
  requested.
- That the matched count and the CRS stay the same on every page.
- That no feature id appears twice. A repeated id usually means the server has no
  stable order across pages; sort by a unique property.

Paging stops at the first short page or once the matched count is reached, so a
complete read needs no trailing empty request. If the server reports no count,
paging continues until a short page arrives. Paging is not a snapshot: data
changed during a read can still produce a consistent-looking result.

## Coordinate precision and axis order

Servers often round output coordinates. GeoServer, for example, writes a fixed
number of decimals per layer. In a projected CRS in metres, four decimals are
sub-millimetre. In EPSG:4326, four decimals are 7 to 11 metres, enough to move
a boundary that runs along a street to the other side. Prefer requesting the
feature type's native projected CRS and reprojecting in the pipeline.

Axis order is passed through unchanged. GeoJSON output from common servers is
east/north (longitude/latitude). A server that follows the formal latitude-first
axis order of EPSG:4326 in its GeoJSON is another reason to request a projected
CRS instead.

## Text encoding

Some data stores read UTF-8 attributes as ISO-8859-1 and emit them as UTF-8 again
(`Ã¶` instead of `ö`). Fix the store's charset where possible; otherwise add
`Pulp::repairDoubleEncodedUtf8()` from `mapsight/pulp` after `srcWfs()`.

## Client options

`new WfsClient($endpoint, $options)` accepts:

| Option | Default | Meaning |
| --- | --- | --- |
| `version` | `2.0.0` | `2.0.0`, `1.1.0` or `1.0.0`. WFS 1.x uses `TYPENAME` and `MAXFEATURES` and cannot page. |
| `outputFormat` | `application/json` | The server's GeoJSON output format name, for example `geojson` on some servers. |
| `client` | new Guzzle client | A Guzzle `ClientInterface`, for TLS configuration, proxies or tests. |
| `headers` | none | Request headers, for example `Authorization`. |
| `maxPages` | 1,000 | Pages per read. |
| `maxFeatures` | 100,000 | Features per read. |
| `maxResponseBytes` | 50,000,000 | Bytes per response. |
| `timeout` | 60 | Request and read timeout in seconds; the connect timeout is at most 15 seconds. |

The endpoint must be an absolute HTTP(S) URL without credentials or fragment. It
may carry a query the server needs on every request, such as MapServer's `map=`,
but not the WFS parameters the client sets. Redirects are not followed; configure
the final URL. Certificate validation stays on.

## Errors

Exception reports (`ows:ExceptionReport` and WFS 1.0 `ServiceExceptionReport`) throw
`WfsException`. This applies whether the server sends them with HTTP 200 or with an
error status. The exception carries `exceptionCode`, `locator` and `httpStatus`,
and its message includes the server's exception text, shortened to 500 characters.
Other failures throw `RuntimeException`; invalid arguments throw
`InvalidArgumentException`.

Error messages name the feature type but not the request URL. Transport errors are
not chained, because their messages repeat the URL, which may contain an access
key. Headers never appear in messages.

## Scope

This package reads features through WFS GetFeature with GeoJSON output. It does not
parse GML, read capabilities, run stored queries or write data (WFS-T). It does not
serve features, and it does not claim OGC conformance for its output.

## Development

Run `vendor/bin/phpunit --testsuite pulp-ogc` from the monorepo root. Tests use
mocked HTTP responses and synthetic data only.
