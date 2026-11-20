<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc\dev\test;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use OpenMapsight\Pulp;
use OpenMapsight\PulpOgc;
use OpenMapsight\pulpogc\WfsClient;
use OpenMapsight\pulpogc\WfsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WfsClientTest extends TestCase
{
    private const ENDPOINT = 'https://maps.example.test/geoserver/ows';
    private const TYPE = 'demo:districts';
    private const CRS = 'urn:ogc:def:crs:EPSG::25832';

    public function testPagesThroughAFeatureTypeWithStableOrder(): void
    {
        $history = [];
        $client = $this->client([
            $this->page([$this->feature('d.1'), $this->feature('d.2')], 5),
            $this->page([$this->feature('d.3'), $this->feature('d.4')], 5),
            $this->page([$this->feature('d.5')], 5),
        ], $history);

        $collection = $client->getFeature(self::TYPE, [
            'srsName' => 'EPSG:25832',
            'sortBy' => 'district_no',
            'pageSize' => 2,
        ]);

        self::assertSame(['d.1', 'd.2', 'd.3', 'd.4', 'd.5'], array_column($collection['features'], 'id'));
        self::assertSame(['type', 'crs', 'features'], array_keys($collection));
        self::assertSame(self::CRS, $collection['crs']['properties']['name']);
        self::assertCount(3, $history);
        $first = $this->query($history[0]['request']);
        self::assertSame([
            'SERVICE' => 'WFS',
            'VERSION' => '2.0.0',
            'REQUEST' => 'GetFeature',
            'TYPENAMES' => self::TYPE,
            'OUTPUTFORMAT' => 'application/json',
            'SRSNAME' => 'EPSG:25832',
            'SORTBY' => 'district_no',
            'COUNT' => '2',
            'STARTINDEX' => '0',
        ], $first);
        self::assertSame('2', $this->query($history[1]['request'])['STARTINDEX']);
        self::assertSame('4', $this->query($history[2]['request'])['STARTINDEX']);
        self::assertFalse($history[0]['options']['allow_redirects']);
    }

    public function testStopsWithoutAnEmptyRequestWhenTheMatchedCountIsReached(): void
    {
        $history = [];
        $client = $this->client([
            $this->page([$this->feature('d.1'), $this->feature('d.2')], 4),
            $this->page([$this->feature('d.3'), $this->feature('d.4')], 4),
        ], $history);

        self::assertCount(4, $client->getFeature(self::TYPE, ['pageSize' => 2])['features']);
        self::assertCount(2, $history);
    }

    public function testPagesWithoutReportedCountsUntilAShortPage(): void
    {
        $history = [];
        $client = $this->client([
            $this->page([$this->feature('d.1'), $this->feature('d.2')]),
            $this->page([]),
        ], $history);

        self::assertCount(2, $client->getFeature(self::TYPE, ['pageSize' => 2])['features']);
        self::assertCount(2, $history);
    }

    public function testLimitCapsTheReadAndAllowsAPartialResult(): void
    {
        $history = [];
        $client = $this->client([
            $this->page([$this->feature('d.1'), $this->feature('d.2')], 10),
            $this->page([$this->feature('d.3')], 10),
        ], $history);

        $collection = $client->getFeature(self::TYPE, ['pageSize' => 2, 'limit' => 3]);

        self::assertCount(3, $collection['features']);
        self::assertSame('1', $this->query($history[1]['request'])['COUNT']);
    }

    public function testUsesWfs1ParameterNames(): void
    {
        $history = [];
        $client = $this->client([$this->page([$this->feature('d.1')], 1)], $history, ['version' => '1.0.0']);

        $client->getFeature(self::TYPE, ['limit' => 10, 'bbox' => [1, 2.5, 3, 4]]);

        $query = $this->query($history[0]['request']);
        self::assertSame(self::TYPE, $query['TYPENAME']);
        self::assertSame('10', $query['MAXFEATURES']);
        self::assertSame('1,2.5,3,4', $query['BBOX']);
        self::assertArrayNotHasKey('TYPENAMES', $query);
        self::assertArrayNotHasKey('STARTINDEX', $query);
    }

    public function testKeepsTheEndpointQueryAndAddsVendorParameters(): void
    {
        $history = [];
        $client = $this->client([$this->page([])], $history, [], self::ENDPOINT . '?map=demo.map');

        $client->getFeature(self::TYPE, [
            'params' => ['CQL_FILTER' => "kind = 'primary'", 'strict' => true],
            'propertyNames' => ['name', 'geom'],
            'bbox' => [500000, 5700000, 510000, 5710000],
            'bboxCrs' => 'EPSG:25832',
        ]);

        $query = $this->query($history[0]['request']);
        self::assertSame('demo.map', $query['map']);
        self::assertSame("kind = 'primary'", $query['CQL_FILTER']);
        self::assertSame('true', $query['strict']);
        self::assertSame('name,geom', $query['PROPERTYNAME']);
        self::assertSame('500000,5700000,510000,5710000,EPSG:25832', $query['BBOX']);
    }

    public function testRejectsAServerSideTruncation(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1'), $this->feature('d.2')], 3)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned 2 of 3 features');
        $client->getFeature(self::TYPE);
    }

    public function testUsesTotalFeaturesWhenNumberMatchedIsMissing(): void
    {
        $page = $this->page([$this->feature('d.1')], null, ['totalFeatures' => 2]);
        $client = $this->client([$page]);

        $this->expectExceptionMessage('returned 1 of 2 features');
        $client->getFeature(self::TYPE);
    }

    public function testRejectsAChangedCountDuringPaging(): void
    {
        $client = $this->client([
            $this->page([$this->feature('d.1')], 3),
            $this->page([$this->feature('d.2')], 4),
        ]);

        $this->expectExceptionMessage('changed during paging');
        $client->getFeature(self::TYPE, ['pageSize' => 1]);
    }

    public function testRejectsRepeatedFeaturesFromUnstablePaging(): void
    {
        $client = $this->client([
            $this->page([$this->feature('d.1'), $this->feature('d.2')], 4),
            $this->page([$this->feature('d.2'), $this->feature('d.3')], 4),
        ]);

        $this->expectExceptionMessage('twice');
        $client->getFeature(self::TYPE, ['pageSize' => 2]);
    }

    public function testRejectsMoreFeaturesThanRequested(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1'), $this->feature('d.2')], 2)]);

        $this->expectExceptionMessage('at most 1 were requested');
        $client->getFeature(self::TYPE, ['pageSize' => 1]);
    }

    public function testRejectsAnInconsistentReturnedCount(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1')], 1, ['numberReturned' => 2])]);

        $this->expectExceptionMessage('reports 2 returned features but contains 1');
        $client->getFeature(self::TYPE);
    }

    public function testReportsOwsExceptionsWithTheirCode(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><ows:ExceptionReport xmlns:ows="http://www.opengis.net/ows/1.1" version="2.0.0">'
            . '<ows:Exception exceptionCode="InvalidParameterValue" locator="typeName">'
            . '<ows:ExceptionText>Feature type demo:missing unknown</ows:ExceptionText></ows:Exception></ows:ExceptionReport>';
        $client = $this->client([new Response(400, ['Content-Type' => 'application/xml'], $xml)]);

        try {
            $client->getFeature('demo:missing');
            self::fail('An exception report must fail the read.');
        } catch (WfsException $error) {
            self::assertSame('InvalidParameterValue', $error->exceptionCode);
            self::assertSame('typeName', $error->locator);
            self::assertSame(400, $error->httpStatus);
            self::assertStringContainsString('Feature type demo:missing unknown', $error->getMessage());
        }
    }

    public function testReportsWfs1ServiceExceptionsSentWithStatus200(): void
    {
        $xml = '<ServiceExceptionReport version="1.2.0"><ServiceException code="InvalidParameterValue">'
            . "\n  Illegal property name: missing\n</ServiceException></ServiceExceptionReport>";
        $client = $this->client([new Response(200, ['Content-Type' => 'text/xml'], $xml)], options: ['version' => '1.0.0']);

        $this->expectException(WfsException::class);
        $this->expectExceptionMessage('failed with InvalidParameterValue: Illegal property name: missing');
        $client->getFeature(self::TYPE);
    }

    #[DataProvider('invalidResponses')]
    public function testRejectsResponsesThatAreNotFeatureCollections(Response $response, string $message): void
    {
        $client = $this->client([$response]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $client->getFeature(self::TYPE);
    }

    public static function invalidResponses(): array
    {
        return [
            'server error' => [new Response(503, [], 'Service unavailable'), 'returned HTTP 503'],
            'redirect' => [new Response(302, ['Location' => 'https://elsewhere.example.test/']), 'returned HTTP 302'],
            'gml' => [new Response(200, [], '<wfs:FeatureCollection xmlns:wfs="http://www.opengis.net/wfs/2.0"/>'), 'returned XML instead of GeoJSON'],
            'invalid json' => [new Response(200, [], '{"type":'), 'valid UTF-8 JSON'],
            'latin-1 json' => [new Response(200, [], "{\"type\":\"FeatureCollection\",\"features\":[],\"x\":\"\xF6\"}"), 'valid UTF-8 JSON'],
            'feature' => [new Response(200, [], '{"type":"Feature","geometry":null,"properties":{}}'), 'not return a GeoJSON FeatureCollection'],
            'features object' => [new Response(200, [], '{"type":"FeatureCollection","features":{"a":1}}'), 'not return a GeoJSON FeatureCollection'],
            'bad feature' => [new Response(200, [], '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"coordinates":[]}}]}'), 'invalid GeoJSON feature'],
        ];
    }

    public function testErrorsDoNotRepeatTheRequestUrlOrHeaders(): void
    {
        $handler = new MockHandler([
            static fn(Request $request) => throw new ConnectException(
                'cURL error 7: connection to ' . $request->getUri() . ' refused',
                $request,
                null,
                ['error' => 'Failed to connect to ' . $request->getUri() . ' port 443'],
            ),
        ]);
        $client = new WfsClient(self::ENDPOINT . '?apikey=test-only-secret', [
            'client' => new Client(['handler' => HandlerStack::create($handler)]),
            'headers' => ['Authorization' => 'Bearer test-only-token'],
        ]);

        try {
            $client->getFeature(self::TYPE);
            self::fail('A transport failure must fail the read.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Failed to connect to [request] port 443', $error->getMessage());
            self::assertStringNotContainsString('test-only-secret', $error->getMessage());
            self::assertStringNotContainsString('test-only-token', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    public function testEnforcesTheResponseByteLimit(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1')])], options: ['maxResponseBytes' => 50]);

        $this->expectExceptionMessage('response byte limit');
        $client->getFeature(self::TYPE);
    }

    public function testEnforcesTheFeatureLimit(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1'), $this->feature('d.2')])], options: ['maxFeatures' => 1]);

        $this->expectExceptionMessage('feature limit');
        $client->getFeature(self::TYPE);
    }

    public function testEnforcesThePageLimit(): void
    {
        $client = $this->client([
            $this->page([$this->feature('d.1')]),
            $this->page([$this->feature('d.2')]),
        ], options: ['maxPages' => 1]);

        $this->expectExceptionMessage('page limit');
        $client->getFeature(self::TYPE, ['pageSize' => 1]);
    }

    public function testRejectsAnAnswerInAnotherCrs(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1')], 1)]);

        $this->expectExceptionMessage('instead of the requested EPSG:4326');
        $client->getFeature(self::TYPE, ['srsName' => 'EPSG:4326']);
    }

    public function testRecordsTheRequestedCrsWhenTheResponseHasNone(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1')], 1, ['crs' => null])]);

        $collection = $client->getFeature(self::TYPE, ['srsName' => 'EPSG:3035']);

        self::assertSame('EPSG:3035', $collection['crs']['properties']['name']);
    }

    #[DataProvider('invalidRequests')]
    public function testRejectsInvalidRequests(array $request, string $message, array $options = []): void
    {
        $client = $this->client([], options: $options);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $client->getFeature(self::TYPE, $request);
    }

    public static function invalidRequests(): array
    {
        return [
            'unknown key' => [['srs' => 'EPSG:4326'], 'Unknown WFS request key "srs"'],
            'paging on 1.1' => [['pageSize' => 10], 'requires WFS 2.0.0', ['version' => '1.1.0']],
            'managed vendor param' => [['params' => ['count' => 5]], 'is set by the client'],
            'managed vendor param case' => [['params' => ['TypeNames' => 'x']], 'is set by the client'],
            'duplicate vendor param' => [['params' => ['a' => 1, 'A' => 2]], 'given twice'],
            'vendor array' => [['params' => ['a' => [1]]], 'must be a string, number or boolean'],
            'short bbox' => [['bbox' => [1, 2, 3]], 'four numbers'],
            'bbox crs on 1.0' => [['bbox' => [1, 2, 3, 4], 'bboxCrs' => 'EPSG:4326'], 'no bbox CRS', ['version' => '1.0.0']],
            'bbox crs alone' => [['bboxCrs' => 'EPSG:4326'], 'requires bbox'],
            'empty property names' => [['propertyNames' => []], 'nonempty list'],
            'zero page size' => [['pageSize' => 0], 'pageSize must be an integer'],
        ];
    }

    #[DataProvider('invalidEndpoints')]
    public function testRejectsUnsafeEndpoints(string $endpoint): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WfsClient($endpoint);
    }

    public static function invalidEndpoints(): array
    {
        return [
            ['maps.example.test/ows'],
            ['ftp://maps.example.test/ows'],
            ['https://user:pass@maps.example.test/ows'],
            ['https://maps.example.test/ows#x'],
            ['https://maps.example.test/ows?service=WFS'],
            ['https://maps.example.test/ows?TYPENAMES=demo:x'],
            ["https://maps.example.test/o ws"],
        ];
    }

    public function testRejectsUnknownOptions(): void
    {
        $this->expectExceptionMessage('Unknown WFS client option "pagesize"');
        new WfsClient(self::ENDPOINT, ['pagesize' => 10]);
    }

    public function testEmitsTheCollectionAsAPulpFile(): void
    {
        $client = $this->client([$this->page([$this->feature('d.1')], 1)]);

        $files = Pulp::start()
            ->pipe(PulpOgc::srcWfs($client, self::TYPE, 'districts.geojson', ['srsName' => 'EPSG:25832']))
            ->run();

        self::assertCount(1, $files);
        self::assertSame('districts.geojson', $files[0]->fileName);
        self::assertSame('d.1', $files[0]->content['features'][0]['id']);
    }

    private function client(array $responses, ?array &$history = null, array $options = [], string $endpoint = self::ENDPOINT): WfsClient
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new WfsClient($endpoint, $options + ['client' => new Client(['handler' => $stack])]);
    }

    private function page(array $features, ?int $matched = null, array $extra = []): Response
    {
        $document = ['type' => 'FeatureCollection', 'features' => $features];
        if ($matched !== null) {
            $document['numberMatched'] = $matched;
            $document['numberReturned'] = count($features);
        }
        $document['crs'] = ['type' => 'name', 'properties' => ['name' => self::CRS]];
        $document = array_merge($document, $extra);
        $document = array_filter($document, static fn($value): bool => $value !== null);

        return new Response(200, ['Content-Type' => 'application/json;charset=UTF-8'], json_encode($document, JSON_THROW_ON_ERROR));
    }

    private function feature(string $id): array
    {
        return [
            'type' => 'Feature',
            'id' => $id,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[500000.125, 5700000.25], [500010.5, 5700000.25], [500010.5, 5700010.75], [500000.125, 5700000.25]]],
            ],
            'geometry_name' => 'geom',
            'properties' => ['district_no' => substr($id, 2)],
        ];
    }

    /** @return array<string, string> */
    private function query(Request $request): array
    {
        $values = [];
        foreach (explode('&', $request->getUri()->getQuery()) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $values[rawurldecode($key)] = rawurldecode($value);
        }

        return $values;
    }
}
