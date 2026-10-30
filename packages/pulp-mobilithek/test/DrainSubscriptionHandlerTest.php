<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmobilithek\dev\test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OpenMapsight\Pulp;
use OpenMapsight\PulpMobilithek;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DrainSubscriptionHandlerTest extends TestCase
{
    private const T0 = 'Fri, 30 Oct 2026 05:00:00 GMT';
    private const T1 = 'Fri, 30 Oct 2026 05:00:01 GMT';
    private const T2 = 'Fri, 30 Oct 2026 05:00:02 GMT';

    public function testDrainsInOrderAndDeduplicatesHeadByEtag(): void
    {
        $history = [];
        $files = $this->runSource([
            $this->packet(self::T1, '1'), $this->packet(self::T2, '2'),
            new Response(304), $this->packet(self::T2, '2'),
        ], $history);
        self::assertCount(2, $files);
        self::assertSame([self::T0, self::T1, self::T2, ''], array_map(
            static fn(array $request): string => $request['request']->getHeaderLine('If-Modified-Since'),
            $history
        ));
        self::assertSame('2', $files[1]->content);
    }

    public function testBootstrapStartsAtOldestSnapshotAndProbeDoesNotAdvanceCursor(): void
    {
        $history = [];
        $files = $this->runSource([
            $this->packet(self::T1, '1'), new Response(304), $this->packet(self::T1, 'collision'),
        ], $history, null);
        self::assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $history[0]['request']->getHeaderLine('If-Modified-Since'));
        self::assertCount(2, $files);
        self::assertFalse($files[1]->mobilithekAdvanceCursor);
    }

    public function testCapDoesNotFetchLatestAndSkipBacklog(): void
    {
        $history = [];
        $files = $this->runSource([$this->packet(self::T1, '1')], $history, self::T0, ['maxPackets' => 1]);
        self::assertCount(1, $history);
        self::assertCount(1, $files);
        self::assertSame(self::T1, $files[0]->httpLastModified);
    }

    public function testNoDataAndDisabledProbeEmitNothing(): void
    {
        $history = [];
        self::assertSame([], $this->runSource([new Response(204)], $history, self::T0, ['probeLatest' => false]));
        self::assertCount(1, $history);
    }

    public function testHeadProbeDoesNotRetainLowercaseConditionalOverrides(): void
    {
        $history = [];
        $this->runSource([new Response(304), new Response(204)], $history, self::T0, [], [
            'headers' => ['if-modified-since' => 'bad', 'if-none-match' => 'bad'],
        ]);
        self::assertFalse($history[1]['request']->hasHeader('If-Modified-Since'));
        self::assertFalse($history[1]['request']->hasHeader('If-None-Match'));
    }

    public function testMissingLastModifiedFails(): void
    {
        $history = [];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no valid Last-Modified');
        $this->runSource([new Response(200, [], 'packet')], $history);
    }

    public function testNonAdvancingCursorFailsInsteadOfLooping(): void
    {
        $history = [];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cursor did not advance');
        $this->runSource([$this->packet(self::T0, 'repeated')], $history);
    }

    public function testHttpErrorFailsInsteadOfEmittingInvalidPacket(): void
    {
        $history = [];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503');
        $this->runSource([new Response(503)], $history);
    }

    private function packet(string $time, string $etag): Response
    {
        return new Response(200, ['Last-Modified' => $time, 'ETag' => $etag, 'Type' => 'DELTA'], $etag);
    }

    private function runSource(array $responses, array &$history, ?string $cursor = self::T0, array $options = [], array $guzzleOptions = []): array
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        $options['client'] = new Client(['handler' => $stack]);

        return Pulp::start()->pipe(PulpMobilithek::drainMobilithek(
            'test-sub',
            '/test.p12',
            'test-password',
            $cursor,
            'status.json',
            $guzzleOptions,
            $options
        ))->run();
    }
}
