<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmobilithek\dev\test;

use OpenMapsight\pulpmobilithek\PushInbox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PushInboxTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/push-inbox-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->path = $this->directory . '/inbox.sqlite';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testCommittedDeliverySurvivesWriterExitAndDeduplicatesAfterProcessing(): void
    {
        $script = 'require $argv[1]; $q = new \\OpenMapsight\\pulpmobilithek\\PushInbox($argv[2]); $q->enqueue("packet", ["subscriptionId"=>"123"]); exit(23);';
        $process = proc_open([PHP_BINARY, '-r', $script, __DIR__ . '/../src/PushInbox.php', $this->path], [], $pipes);
        self::assertIsResource($process);
        self::assertSame(23, proc_close($process));
        $inbox = new PushInbox($this->path);
        self::assertSame(1, $inbox->stats()['pendingPackets']);
        self::assertSame(1, $inbox->consume(static function (array $rows): void {
            self::assertSame('packet', $rows[0]['body']);
            self::assertSame('123', $rows[0]['metadata']['subscriptionId']);
        }));
        self::assertFalse($inbox->enqueue('packet', ['subscriptionId' => '123']));
        self::assertSame(0, $inbox->stats()['pendingPackets']);
    }

    public function testFailedPublicationIsRetriedAndPreservesBody(): void
    {
        $inbox = new PushInbox($this->path);
        $inbox->enqueue('retry-me', []);
        try {
            $inbox->consume(static function (): void {
                throw new RuntimeException('publish failed');
            });
            self::fail('Expected publication failure');
        } catch (RuntimeException $error) {
            self::assertSame('publish failed', $error->getMessage());
        }
        $retry = new PushInbox($this->path);
        self::assertSame(8, $retry->stats()['pendingBytes']);
        $retry->consume(static function (array $rows): void {
            self::assertSame('retry-me', $rows[0]['body']);
        });
        self::assertSame(0, $retry->stats()['pendingBytes']);
    }

    public function testWorkerIsExclusiveButNewReceptionContinuesDuringPublication(): void
    {
        $inbox = new PushInbox($this->path);
        $other = new PushInbox($this->path);
        $inbox->enqueue('first', []);
        $inbox->consume(static function (array $rows) use ($other): void {
            self::assertSame(0, $other->consume(static function (): void {
                self::fail('Second worker must not enter');
            }));
            self::assertTrue($other->enqueue('arrived-during-publication', []));
            self::assertCount(1, $rows);
        });
        self::assertSame(1, $other->stats()['pendingPackets']);
        $other->consume(static function (array $rows): void {
            self::assertSame('arrived-during-publication', $rows[0]['body']);
        });
    }

    public function testCapacityIsBoundedButRetriesAreStillAcknowledgedAndDrainFreesSpace(): void
    {
        $inbox = new PushInbox($this->path, 5, 1);
        self::assertTrue($inbox->enqueue('12345', []));
        self::assertFalse($inbox->enqueue('12345', []));
        try {
            $inbox->enqueue('x', []);
            self::fail('Expected capacity failure');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('capacity', $error->getMessage());
        }
        $inbox->consume(static function (): void {});
        self::assertTrue($inbox->enqueue('x', []));
    }

    public function testBatchesKeepArrivalOrderAndSubscriptionIdentity(): void
    {
        $inbox = new PushInbox($this->path);
        $inbox->enqueue('same-body', ['subscriptionId' => '1']);
        $inbox->enqueue('same-body', ['subscriptionId' => '2']);
        self::assertSame(1, $inbox->consume(static function (array $rows): void {
            self::assertSame('1', $rows[0]['metadata']['subscriptionId']);
        }, 1));
        self::assertSame(1, $inbox->stats()['pendingPackets']);
        $inbox->consume(static function (array $rows): void {
            self::assertSame('2', $rows[0]['metadata']['subscriptionId']);
        });
        self::assertNotNull($inbox->stats()['lastProcessedAt']);
    }
}
