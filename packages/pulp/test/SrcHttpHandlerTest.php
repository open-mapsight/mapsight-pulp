<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenMapsight\Pulp;
use PHPUnit\Framework\TestCase;

class SrcHttpHandlerTest extends TestCase
{
    public function testLoadsBodyAsStringByDefault(): void
    {
        $result = Pulp::start()
            ->pipe(Pulp::srcHttp(
                'GET',
                'https://example.test/data.json',
                [],
                'data.json',
                ['client' => $this->client(new Response(200, [], '{"ok":true}'))]
            ))
            ->run();

        $this->assertCount(1, $result);
        $this->assertSame('{"ok":true}', $result[0]->content);
        $this->assertSame(200, $result[0]->httpStatus);
    }

    public function testSinkWritesPathBackedFile(): void
    {
        $sink = tempnam(sys_get_temp_dir(), 'pulp-http-sink-');
        $this->assertIsString($sink);

        try {
            $result = Pulp::start()
                ->pipe(Pulp::srcHttp(
                    'GET',
                    'https://example.test/large.json',
                    [],
                    'large.json',
                    [
                        'client' => $this->client(new Response(200, [
                            'Last-Modified' => 'Wed, 01 Jan 2026 00:00:00 GMT',
                            'Type' => 'SNAPSHOT',
                        ], '{"sites":true}')),
                        'sink' => $sink,
                    ]
                ))
                ->run();

            $this->assertCount(1, $result);
            $this->assertSame('{"sites":true}', $result[0]->content);
            $this->assertSame('Wed, 01 Jan 2026 00:00:00 GMT', $result[0]->httpLastModified);
            $this->assertSame('SNAPSHOT', $result[0]->httpType);
            $this->assertSame('{"sites":true}', file_get_contents($sink));
        } finally {
            @unlink($sink);
        }
    }

    public function testSuccessStatusesRejectUnexpectedCodes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned 500');

        Pulp::start()
            ->pipe(Pulp::srcHttp(
                'GET',
                'https://example.test/status',
                ['http_errors' => false],
                'status.json',
                [
                    'client' => $this->client(new Response(500, [], 'nope')),
                    'successStatuses' => [200, 304, 204],
                ]
            ))
            ->run();
    }

    public function testNotModifiedStillEmitsFileWithStatus(): void
    {
        $result = Pulp::start()
            ->pipe(Pulp::srcHttp(
                'GET',
                'https://example.test/status',
                ['http_errors' => false],
                'status.json',
                [
                    'client' => $this->client(new Response(304, [
                        'Last-Modified' => 'Wed, 01 Jan 2026 00:00:00 GMT',
                    ], '')),
                    'successStatuses' => [200, 304, 204],
                ]
            ))
            ->run();

        $this->assertCount(1, $result);
        $this->assertSame(304, $result[0]->httpStatus);
        $this->assertSame('', $result[0]->content);
    }

    private function client(Response $response): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler([$response])),
        ]);
    }
}
