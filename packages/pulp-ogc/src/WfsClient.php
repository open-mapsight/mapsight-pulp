<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/** Read features from one OGC Web Feature Service endpoint as GeoJSON. */
class WfsClient
{
    public const VERSIONS = ['2.0.0', '1.1.0', '1.0.0'];

    /** KVP keys this client sets. Neither the endpoint nor vendor parameters may set them. */
    private const MANAGED_KEYS = [
        'service', 'version', 'request', 'typename', 'typenames', 'outputformat', 'srsname',
        'bbox', 'propertyname', 'sortby', 'count', 'maxfeatures', 'startindex', 'resulttype',
        'featureid', 'resourceid', 'storedquery_id',
    ];

    private const OPTIONS = [
        'client', 'headers', 'version', 'outputFormat', 'maxPages', 'maxFeatures', 'maxResponseBytes', 'timeout',
    ];

    private const REQUEST_KEYS = [
        'srsName', 'bbox', 'bboxCrs', 'propertyNames', 'sortBy', 'pageSize', 'limit', 'params',
    ];

    private const MAX_ERROR_TEXT = 500;

    private readonly UriInterface $endpoint;
    /** @var array<string, true> lower-cased keys of the endpoint's own query */
    private readonly array $endpointKeys;
    private readonly ClientInterface $client;
    private readonly array $headers;
    private readonly string $version;
    private readonly string $outputFormat;
    private readonly int $maxPages;
    private readonly int $maxFeatures;
    private readonly int $maxResponseBytes;
    private readonly int $timeout;

    /**
     * @param string $endpoint The service URL, for example `https://maps.example.org/geoserver/ows`.
     *        It may carry a query that the server needs on every request (such as `map=`),
     *        but none of the WFS parameters this client sets.
     * @param array{
     *     client?: ClientInterface,
     *     headers?: array<string, string|list<string>>,
     *     version?: '2.0.0'|'1.1.0'|'1.0.0',
     *     outputFormat?: string,
     *     maxPages?: int,
     *     maxFeatures?: int,
     *     maxResponseBytes?: int,
     *     timeout?: int
     * } $options
     */
    public function __construct(string $endpoint, array $options = [])
    {
        self::assertKnownKeys($options, self::OPTIONS, 'WFS client option');
        if ($endpoint === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $endpoint) === 1) {
            throw new InvalidArgumentException('A WFS endpoint must not contain whitespace, control characters or backslashes.');
        }
        try {
            $uri = new Uri($endpoint);
        } catch (Throwable) {
            throw new InvalidArgumentException('A WFS endpoint must be a valid URL.');
        }
        if (!in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getHost() === ''
            || $uri->getUserInfo() !== '' || $uri->getFragment() !== '') {
            throw new InvalidArgumentException('A WFS endpoint must be an absolute HTTP(S) URL without credentials or fragment.');
        }
        $keys = [];
        foreach (self::queryKeys($uri->getQuery()) as $key) {
            if (in_array($key, self::MANAGED_KEYS, true)) {
                throw new InvalidArgumentException('The WFS endpoint query must not set ' . strtoupper($key) . '; the client sets it per request.');
            }
            $keys[$key] = true;
        }

        $client = $options['client'] ?? new Client();
        if (!$client instanceof ClientInterface) {
            throw new InvalidArgumentException('The WFS HTTP client must implement GuzzleHttp\\ClientInterface.');
        }
        $headers = $options['headers'] ?? [];
        if (!is_array($headers)) {
            throw new InvalidArgumentException('WFS headers must be an array.');
        }
        $version = $options['version'] ?? '2.0.0';
        if (!in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException('WFS version must be one of ' . implode(', ', self::VERSIONS) . '.');
        }
        $outputFormat = $options['outputFormat'] ?? 'application/json';
        if (!is_string($outputFormat) || trim($outputFormat) === '') {
            throw new InvalidArgumentException('WFS outputFormat must be a nonempty string.');
        }

        $this->endpoint = $uri;
        $this->endpointKeys = $keys;
        $this->client = $client;
        $this->headers = $headers;
        $this->version = $version;
        $this->outputFormat = $outputFormat;
        $this->maxPages = self::limit($options, 'maxPages', 1000, 100000);
        $this->maxFeatures = self::limit($options, 'maxFeatures', 100000, 10000000);
        $this->maxResponseBytes = self::limit($options, 'maxResponseBytes', 50000000, 1000000000);
        $this->timeout = self::limit($options, 'timeout', 60, 3600);
    }

    /**
     * Read all features of one feature type. Returns a GeoJSON FeatureCollection after every
     * page succeeded; a failure never returns a partial collection.
     *
     * The result has `type`, `features` and, when known, a GeoJSON 2008 named `crs` member
     * (from the response, or else the requested `srsName`). Feature ids are passed through
     * as the server sent them. Server metadata such as `numberMatched` and per-page `bbox`
     * is checked, then dropped.
     *
     * @param array{
     *     srsName?: string,
     *     bbox?: array{0: int|float, 1: int|float, 2: int|float, 3: int|float},
     *     bboxCrs?: string,
     *     propertyNames?: list<string>,
     *     sortBy?: string,
     *     pageSize?: int,
     *     limit?: int,
     *     params?: array<string, string|int|float|bool>
     * } $request
     */
    public function getFeature(string $typeName, array $request = []): array
    {
        if (trim($typeName) === '' || preg_match('/[\x00-\x1f\x7f]/', $typeName) === 1) {
            throw new InvalidArgumentException('A WFS type name must be a nonempty string without control characters.');
        }
        self::assertKnownKeys($request, self::REQUEST_KEYS, 'WFS request key');
        $params = $this->baseParams($typeName, $request);
        $pageSize = $this->optionalInt($request, 'pageSize', $this->maxFeatures);
        $limit = $this->optionalInt($request, 'limit', $this->maxFeatures);
        if ($pageSize !== null && $this->version !== '2.0.0') {
            throw new InvalidArgumentException('Paging (pageSize) requires WFS 2.0.0.');
        }
        $vendorParams = $this->vendorParams($request['params'] ?? []);

        $features = [];
        $seenIds = [];
        $matched = null;
        $crs = null;
        $start = 0;
        $pages = 0;
        while (true) {
            $count = $pageSize ?? $limit;
            if ($limit !== null) {
                $count = min($count, $limit - count($features));
                if ($count <= 0) {
                    break;
                }
            }
            if (++$pages > $this->maxPages) {
                throw new RuntimeException('WFS paging for "' . $typeName . '" exceeded the page limit.');
            }
            $pageParams = $params;
            if ($count !== null) {
                $pageParams[$this->version === '2.0.0' ? 'COUNT' : 'MAXFEATURES'] = (string) $count;
            }
            if ($pageSize !== null) {
                $pageParams['STARTINDEX'] = (string) $start;
            }
            $document = $this->page($typeName, $pageParams + $vendorParams);

            $pageFeatures = $document['features'];
            $returned = count($pageFeatures);
            if (is_int($document['numberReturned'] ?? null) && $document['numberReturned'] !== $returned) {
                throw new RuntimeException('WFS response for "' . $typeName . '" reports ' . $document['numberReturned']
                    . ' returned features but contains ' . $returned . '.');
            }
            if ($count !== null && $returned > $count) {
                throw new RuntimeException('WFS returned ' . $returned . ' features for "' . $typeName . '" where at most '
                    . $count . ' were requested.');
            }
            $pageMatched = self::matched($document);
            if ($pageMatched !== null) {
                if ($matched !== null && $matched !== $pageMatched) {
                    throw new RuntimeException('WFS feature count for "' . $typeName . '" changed during paging.');
                }
                $matched = $pageMatched;
            }
            $pageCrs = Crs::fromGeoJson($document);
            if ($pages > 1 && $pageCrs !== $crs) {
                throw new RuntimeException('WFS pages for "' . $typeName . '" declare different CRS.');
            }
            $crs = $pageCrs;

            foreach ($pageFeatures as $feature) {
                $id = $feature['id'] ?? null;
                if (is_string($id) || is_int($id)) {
                    if (isset($seenIds[(string) $id])) {
                        throw new RuntimeException('WFS returned feature "' . $id . '" of "' . $typeName
                            . '" twice. Paging needs a stable order; set sortBy to a unique property.');
                    }
                    $seenIds[(string) $id] = true;
                }
                $features[] = $feature;
            }
            if (count($features) > $this->maxFeatures) {
                throw new RuntimeException('WFS response for "' . $typeName . '" exceeded the feature limit.');
            }

            $start += $returned;
            if ($pageSize === null || $returned < $count || ($matched !== null && $start >= $matched)) {
                break;
            }
        }

        if ($matched !== null) {
            $expected = $limit === null ? $matched : min($limit, $matched);
            if (count($features) !== $expected) {
                throw new RuntimeException('WFS returned ' . count($features) . ' of ' . $expected . ' features for "'
                    . $typeName . '". The server limits its responses; set pageSize below that limit.');
            }
        }

        $srsName = $request['srsName'] ?? null;
        if ($srsName !== null && $crs !== null && !Crs::same($srsName, $crs)) {
            throw new RuntimeException('WFS answered "' . $typeName . '" in ' . $crs . ' instead of the requested ' . $srsName . '.');
        }
        $crs ??= $srsName;

        $collection = ['type' => 'FeatureCollection'];
        if ($crs !== null) {
            $collection['crs'] = ['type' => 'name', 'properties' => ['name' => $crs]];
        }
        $collection['features'] = $features;

        return $collection;
    }

    /** @return array<string, string> */
    private function baseParams(string $typeName, array $request): array
    {
        $params = [
            'SERVICE' => 'WFS',
            'VERSION' => $this->version,
            'REQUEST' => 'GetFeature',
            ($this->version === '2.0.0' ? 'TYPENAMES' : 'TYPENAME') => $typeName,
            'OUTPUTFORMAT' => $this->outputFormat,
        ];
        foreach (['srsName' => 'SRSNAME', 'sortBy' => 'SORTBY'] as $key => $param) {
            if (array_key_exists($key, $request)) {
                if (!is_string($request[$key]) || trim($request[$key]) === '') {
                    throw new InvalidArgumentException($key . ' must be a nonempty string.');
                }
                $params[$param] = $request[$key];
            }
        }
        if (array_key_exists('bbox', $request)) {
            $bbox = $request['bbox'];
            if (!is_array($bbox) || !array_is_list($bbox) || count($bbox) !== 4) {
                throw new InvalidArgumentException('bbox must be a list of four numbers.');
            }
            foreach ($bbox as $value) {
                if (!(is_int($value) || is_float($value)) || !is_finite((float) $value)) {
                    throw new InvalidArgumentException('bbox must be a list of four finite numbers.');
                }
            }
            $value = implode(',', array_map(self::number(...), $bbox));
            if (array_key_exists('bboxCrs', $request)) {
                if ($this->version === '1.0.0') {
                    throw new InvalidArgumentException('WFS 1.0.0 has no bbox CRS; give the bbox in the feature type\'s CRS.');
                }
                if (!is_string($request['bboxCrs']) || trim($request['bboxCrs']) === '') {
                    throw new InvalidArgumentException('bboxCrs must be a nonempty string.');
                }
                $value .= ',' . $request['bboxCrs'];
            }
            $params['BBOX'] = $value;
        } elseif (array_key_exists('bboxCrs', $request)) {
            throw new InvalidArgumentException('bboxCrs requires bbox.');
        }
        if (array_key_exists('propertyNames', $request)) {
            $names = $request['propertyNames'];
            if (!is_array($names) || !array_is_list($names) || $names === []) {
                throw new InvalidArgumentException('propertyNames must be a nonempty list of names.');
            }
            foreach ($names as $name) {
                if (!is_string($name) || trim($name) === '' || str_contains($name, ',')) {
                    throw new InvalidArgumentException('propertyNames must be nonempty names without commas.');
                }
            }
            $params['PROPERTYNAME'] = implode(',', $names);
        }

        return $params;
    }

    /** @return array<string, string> */
    private function vendorParams(mixed $params): array
    {
        if (!is_array($params)) {
            throw new InvalidArgumentException('params must be an array of vendor parameters.');
        }
        $result = [];
        $seen = [];
        foreach ($params as $key => $value) {
            $lower = strtolower((string) $key);
            if (!is_string($key) || trim($key) === '' || preg_match('/[\x00-\x20\x7f=&]/', $key) === 1) {
                throw new InvalidArgumentException('Vendor parameter names must be nonempty strings without spaces, "=" or "&".');
            }
            if (in_array($lower, self::MANAGED_KEYS, true)) {
                throw new InvalidArgumentException('Vendor parameter ' . $key . ' is set by the client; use the matching request key.');
            }
            if (isset($this->endpointKeys[$lower]) || isset($seen[$lower])) {
                throw new InvalidArgumentException('Vendor parameter ' . $key . ' is given twice.');
            }
            $seen[$lower] = true;
            $result[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value), is_string($value) => (string) $value,
                is_float($value) && is_finite($value) => self::number($value),
                default => throw new InvalidArgumentException('Vendor parameter ' . $key . ' must be a string, number or boolean.'),
            };
        }

        return $result;
    }

    /** @param array<string, string> $params */
    private function page(string $typeName, array $params): array
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $existing = $this->endpoint->getQuery();
        $uri = $this->endpoint->withQuery($existing === '' ? $query : $existing . '&' . $query);
        $label = 'WFS GetFeature for "' . $typeName . '"';

        try {
            $response = $this->client->request('GET', $uri, [
                'headers' => array_merge([
                    'Accept' => 'application/geo+json, application/json;q=0.9, application/xml;q=0.5, text/xml;q=0.5',
                ], $this->headers),
                'allow_redirects' => false,
                'http_errors' => false,
                'connect_timeout' => min(15, $this->timeout),
                'timeout' => $this->timeout,
                'read_timeout' => $this->timeout,
                'stream' => true,
            ]);
        } catch (Throwable $error) {
            // Transport messages repeat the request URL, which may carry access keys.
            $reason = $error instanceof RequestException || $error instanceof ConnectException
                ? ($error->getHandlerContext()['error'] ?? null)
                : null;
            throw new RuntimeException($label . ' failed' . (is_string($reason) && $reason !== ''
                ? ': ' . self::clean(str_replace((string) $uri, '[request]', $reason)) : '.'));
        }

        $body = $response->getBody();
        try {
            $content = '';
            while (!$body->eof()) {
                $chunk = $body->read(min(65536, $this->maxResponseBytes - strlen($content) + 1));
                if ($chunk === '' && !$body->eof()) {
                    throw new RuntimeException($label . ' could not be read completely.');
                }
                $content .= $chunk;
                if (strlen($content) > $this->maxResponseBytes) {
                    throw new RuntimeException($label . ' exceeded the response byte limit.');
                }
            }
        } finally {
            $body->close();
        }

        $status = $response->getStatusCode();
        $isXml = str_starts_with(ltrim($content), '<');
        if ($isXml && ($report = self::exceptionReport($content)) !== null) {
            [$code, $locator, $text] = $report;
            throw new WfsException(
                $label . ' failed' . ($code !== null ? ' with ' . $code : '') . ($text !== '' ? ': ' . $text : '.'),
                $code,
                $locator,
                $status,
            );
        }
        if ($status !== 200) {
            throw new RuntimeException($label . ' returned HTTP ' . $status . '.');
        }
        if ($isXml) {
            throw new RuntimeException($label . ' returned XML instead of GeoJSON; check that the server supports outputFormat '
                . $this->outputFormat . '.');
        }

        try {
            $document = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException($label . ' did not return valid UTF-8 JSON.');
        }
        self::assertFeatureCollection($document, $label);

        return $document;
    }

    private static function assertFeatureCollection(mixed $document, string $label): void
    {
        if (!is_array($document) || ($document['type'] ?? null) !== 'FeatureCollection'
            || !is_array($document['features'] ?? null) || !array_is_list($document['features'])) {
            throw new RuntimeException($label . ' did not return a GeoJSON FeatureCollection.');
        }
        foreach ($document['features'] as $feature) {
            if (!is_array($feature) || ($feature['type'] ?? null) !== 'Feature'
                || !array_key_exists('geometry', $feature)
                || !($feature['geometry'] === null || (is_array($feature['geometry']) && is_string($feature['geometry']['type'] ?? null)))
                || !(($feature['properties'] ?? null) === null || is_array($feature['properties']))) {
                throw new RuntimeException($label . ' returned an invalid GeoJSON feature.');
            }
        }
    }

    /** @return array{0: ?string, 1: ?string, 2: string}|null code, locator and text of the first exception */
    private static function exceptionReport(string $content): ?array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($content, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $dom->documentElement;
        if (!$loaded || !$root instanceof DOMElement
            || !in_array($root->localName, ['ExceptionReport', 'ServiceExceptionReport'], true)) {
            return null;
        }
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element->localName === 'Exception') {
                $texts = [];
                foreach ($element->getElementsByTagName('*') as $child) {
                    if ($child->localName === 'ExceptionText') {
                        $texts[] = $child->textContent;
                    }
                }

                return [self::attribute($element, 'exceptionCode'), self::attribute($element, 'locator'), self::clean(implode(' ', $texts))];
            }
            if ($element->localName === 'ServiceException') {
                return [self::attribute($element, 'code'), self::attribute($element, 'locator'), self::clean($element->textContent)];
            }
        }

        return [null, null, ''];
    }

    private static function attribute(DOMElement $element, string $name): ?string
    {
        $value = self::clean($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    private static function clean(string $text): string
    {
        $text = trim((string) preg_replace('/[\x00-\x20\x7f]+/', ' ', $text));
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return mb_strlen($text) > self::MAX_ERROR_TEXT ? mb_substr($text, 0, self::MAX_ERROR_TEXT) . '…' : $text;
    }

    private static function matched(array $document): ?int
    {
        foreach (['numberMatched', 'totalFeatures'] as $key) {
            if (is_int($document[$key] ?? null) && $document[$key] >= 0) {
                return $document[$key];
            }
        }

        return null;
    }

    /** @return list<string> lower-cased keys */
    private static function queryKeys(string $query): array
    {
        $keys = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair !== '') {
                $keys[] = strtolower(rawurldecode(strtok($pair, '=') ?: ''));
            }
        }

        return $keys;
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        $text = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private function optionalInt(array $request, string $key, int $maximum): ?int
    {
        if (!array_key_exists($key, $request)) {
            return null;
        }
        $value = $request[$key];
        if (!is_int($value) || $value < 1 || $value > $maximum) {
            throw new InvalidArgumentException($key . ' must be an integer between 1 and ' . $maximum . '.');
        }

        return $value;
    }

    private static function assertKnownKeys(array $values, array $known, string $kind): void
    {
        foreach (array_keys($values) as $key) {
            if (!in_array($key, $known, true)) {
                throw new InvalidArgumentException('Unknown ' . $kind . ' "' . $key . '".');
            }
        }
    }

    private static function limit(array $options, string $name, int $default, int $maximum): int
    {
        $value = $options[$name] ?? $default;
        if (!is_int($value) || $value < 1 || $value > $maximum) {
            throw new InvalidArgumentException($name . ' must be an integer between 1 and ' . $maximum . '.');
        }

        return $value;
    }
}
