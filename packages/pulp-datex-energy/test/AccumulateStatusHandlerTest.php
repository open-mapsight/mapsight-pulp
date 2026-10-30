<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy\dev\test;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexEnergy;
use OpenMapsight\pulpdatexenergy\DatexEnergyStatus;
use OpenMapsight\PulpMobilithek;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AccumulateStatusHandlerTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = tempnam(sys_get_temp_dir(), 'datex-test-');
    }

    protected function tearDown(): void
    {
        unlink($this->cachePath);
    }

    public function testSnapshotMidStreamResetsThenLaterDeltaWins(): void
    {
        $files = $this->accumulate([
            $this->packet('old', 'available', '2026-10-30T05:00:00Z'),
            $this->packet('new', 'available', '2026-10-30T05:00:01Z', 'snapshotPull'),
            $this->packet('new', 'charging', '2026-10-30T05:00:02Z'),
        ]);
        self::assertSame(['new'], array_keys($files[0]->content['points']));
        self::assertSame('charging', $files[0]->content['points']['new']['status']);
        self::assertSame($files[0]->content, json_decode(file_get_contents($this->cachePath), true));
    }

    public function testSnapshotMetadataWorksForPullAndPushAndOverridesHeader(): void
    {
        foreach (['snapshotPull', 'snapshotPush'] as $protocol) {
            $packet = $this->packet('new', 'available', '2026-10-30T05:00:01Z', $protocol);
            self::assertSame('SNAPSHOT', DatexEnergyStatus::packetType($packet->content, 'DELTA'));
        }
    }

    public function testOlderPointsCannotOverwriteNewerValuesIncludingOffsetsAndFractions(): void
    {
        $files = $this->accumulate([
            $this->packet('a', 'charging', '2026-10-30T05:00:00.500Z'),
            $this->packet('a', 'available', '2026-10-30T06:00:00.499+01:00'),
        ]);
        self::assertSame('charging', $files[0]->content['points']['a']['status']);
    }

    public function testHeadProbeUpdatesPointsButPreservesSequentialCursorAcrossRuns(): void
    {
        $first = $this->packet('a', 'available', '2026-10-30T05:00:00Z');
        $first->httpLastModified = 'Fri, 30 Oct 2026 05:00:00 GMT';
        $probe = $this->packet('a', 'charging', '2026-10-30T05:00:02Z');
        $probe->httpLastModified = 'Fri, 30 Oct 2026 05:00:02 GMT';
        $probe->mobilithekAdvanceCursor = false;
        $files = $this->accumulate([$first, $probe]);
        self::assertSame($first->httpLastModified, $files[0]->content['lastModified']);
        $files = $this->accumulate([$this->packet('a', 'available', '2026-10-30T05:00:01Z')]);
        self::assertSame('charging', $files[0]->content['points']['a']['status']);
    }

    public function testDrainAndAccumulatorProcessSnapshotDeltasAndHeadCollisionTogether(): void
    {
        $responses = [];
        foreach ([
            ['a', 'available', '2026-10-30T05:00:01Z', 'snapshotPull', 'Fri, 30 Oct 2026 05:00:01 GMT', '1'],
            ['b', 'charging', '2026-10-30T05:00:02Z', 'delta', 'Fri, 30 Oct 2026 05:00:02 GMT', '2'],
        ] as [$id, $status, $updated, $protocol, $modified, $etag]) {
            $responses[] = new Response(200, ['Last-Modified' => $modified, 'ETag' => $etag], json_encode(
                $this->packet($id, $status, $updated, $protocol)->content
            ));
        }
        $responses[] = new Response(304);
        $responses[] = new Response(200, [
            'Last-Modified' => 'Fri, 30 Oct 2026 05:00:02 GMT', 'ETag' => 'collision',
        ], json_encode($this->packet('a', 'charging', '2026-10-30T05:00:02Z')->content));
        $files = Pulp::start()->pipe(PulpMobilithek::drainMobilithek(
            'sub',
            '/test.p12',
            'password',
            null,
            'status.json',
            [],
            ['client' => new Client(['handler' => new MockHandler($responses)])]
        ))->pipe(PulpDatexEnergy::accumulateStatus($this->cachePath))->run();
        self::assertSame(2, $files[0]->content['pointCount']);
        self::assertSame('charging', $files[0]->content['points']['a']['status']);
        self::assertSame('charging', $files[0]->content['points']['b']['status']);
        self::assertSame('Fri, 30 Oct 2026 05:00:02 GMT', $files[0]->content['lastModified']);
        self::assertSame(2, $files[0]->content['accumulatorVersion']);
    }

    public function testMalformedPacketDoesNotCommitPartialRun(): void
    {
        $this->accumulate([$this->packet('a', 'available', '2026-10-30T05:00:00Z')]);
        $before = file_get_contents($this->cachePath);
        $invalid = new File('invalid.json');
        $invalid->content = ['error' => 'no publication'];
        try {
            $this->accumulate([$this->packet('a', 'charging', '2026-10-30T05:00:01Z'), $invalid]);
            self::fail('Expected invalid publication');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('no status publication', $exception->getMessage());
        }
        self::assertSame($before, file_get_contents($this->cachePath));
    }

    public function testAtomicReplacementPreservesCacheReadPermissions(): void
    {
        chmod($this->cachePath, 0o664);
        $this->accumulate([$this->packet('a', 'available', '2026-10-30T05:00:00Z')]);
        clearstatcache(true, $this->cachePath);
        self::assertSame(0o664, fileperms($this->cachePath) & 0o777);
    }

    public function testDownloadFailureLeavesCacheAndCursorUntouched(): void
    {
        $this->accumulate([$this->packet('a', 'available', '2026-10-30T05:00:00Z')]);
        $before = file_get_contents($this->cachePath);
        $response = new Response(200, ['Last-Modified' => 'Fri, 30 Oct 2026 05:00:01 GMT'], json_encode(
            $this->packet('a', 'charging', '2026-10-30T05:00:01Z')->content
        ));
        try {
            Pulp::start()->pipe(PulpMobilithek::drainMobilithek(
                'sub',
                '/test.p12',
                'password',
                'Fri, 30 Oct 2026 05:00:00 GMT',
                'status.json',
                [],
                ['client' => new Client(['handler' => new MockHandler([$response, new Response(503)])])]
            ))->pipe(PulpDatexEnergy::accumulateStatus($this->cachePath))->run();
            self::fail('Expected failed request');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('503', $exception->getMessage());
        }
        self::assertSame($before, file_get_contents($this->cachePath));
    }

    public function testNotModifiedDoesNotMoveCursorOrTouchDisk(): void
    {
        $this->accumulate([$this->packet('a', 'available', '2026-10-30T05:00:00Z')]);
        $before = file_get_contents($this->cachePath);
        $file = new File('not-modified.json');
        $file->httpStatus = 304;
        $file->httpLastModified = 'Fri, 30 Oct 2026 07:00:00 GMT';
        $this->accumulate([$file]);
        self::assertSame($before, file_get_contents($this->cachePath));
    }

    private function accumulate(array $packets): array
    {
        return Pulp::start()->pipe(Pulp::src($packets))
            ->pipe(PulpDatexEnergy::accumulateStatus($this->cachePath))->run();
    }

    private function packet(string $id, string $status, string $updated, string $protocol = 'delta'): File
    {
        $file = new File('status.json');
        $file->httpStatus = 200;
        $file->httpType = '';
        $file->content = ['messageContainer' => [
            'exchangeInformation' => ['dynamicInformation' => ['exchangeContext' => ['codedExchangeProtocol' => $protocol]]],
            'payload' => [['aegiEnergyInfrastructureStatusPublication' => ['energyInfrastructureSiteStatus' => [[
                'reference' => ['idG' => 'site'],
                'energyInfrastructureStationStatus' => [[
                    'reference' => ['idG' => 'station'], 'lastUpdated' => $updated,
                    'refillPointStatus' => [['aegiElectricChargingPointStatus' => [
                        'reference' => ['idG' => $id], 'status' => ['value' => $status],
                    ]]],
                ]],
            ]]]]],
        ]];

        return $file;
    }
}
