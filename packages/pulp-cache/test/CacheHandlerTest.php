<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpCache;
use PHPUnit\Framework\TestCase;

class CacheHandlerTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testCacheReturnsFreshCachedFile(): void
    {
        $cacheDirectory = $this->createTemporaryDirectory();

        $file = new File('data.txt');
        $file->content = 'fresh content';

        $firstResult = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpCache::cache($cacheDirectory))
            ->run();

        $this->assertSame('fresh content', $firstResult[0]->content);

        $changedFile = new File('data.txt');
        $changedFile->content = 'changed content';

        $secondResult = Pulp::start()
            ->pipe(Pulp::src($changedFile))
            ->pipe(PulpCache::cache($cacheDirectory))
            ->run();

        $this->assertSame('fresh content', $secondResult[0]->content);
    }

    public function testCacheCanStoreNonStringContent(): void
    {
        $cacheDirectory = $this->createTemporaryDirectory();

        $file = new File('data');
        $file->content = ['value' => 42];

        Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpCache::cache($cacheDirectory))
            ->run();

        $changedFile = new File('data');
        $changedFile->content = ['value' => 13];

        $result = Pulp::start()
            ->pipe(Pulp::src($changedFile))
            ->pipe(PulpCache::cache($cacheDirectory))
            ->run();

        $this->assertSame(['value' => 42], $result[0]->content);
    }

    public function testRememberKeepsCacheOnNotModified(): void
    {
        $cacheDirectory = $this->createTemporaryDirectory();

        $fresh = new File('sites.geojson');
        $fresh->content = ['type' => 'FeatureCollection', 'features' => [['id' => 'a']]];
        $fresh->httpStatus = 200;

        Pulp::start()
            ->pipe(PulpCache::remember(
                Pulp::start()->pipe(Pulp::src($fresh)),
                $cacheDirectory,
                ['key' => 'static', 'ttl' => 0, 'keepCacheOnNotModified' => true]
            ))
            ->run();

        $notModified = new File('sites.geojson');
        $notModified->content = '';
        $notModified->httpStatus = 304;

        $result = Pulp::start()
            ->pipe(PulpCache::remember(
                Pulp::start()->pipe(Pulp::src($notModified)),
                $cacheDirectory,
                ['key' => 'static', 'ttl' => 0, 'keepCacheOnNotModified' => true]
            ))
            ->run();

        $this->assertSame(['type' => 'FeatureCollection', 'features' => [['id' => 'a']]], $result[0]->content);
    }

    public function testRememberKeepsCacheWhenSourceIsEmpty(): void
    {
        $cacheDirectory = $this->createTemporaryDirectory();

        $fresh = new File('sites.geojson');
        $fresh->content = ['kept' => true];

        Pulp::start()
            ->pipe(PulpCache::remember(
                Pulp::start()->pipe(Pulp::src($fresh)),
                $cacheDirectory,
                ['key' => 'static', 'ttl' => 0, 'keepCacheWhenEmpty' => true]
            ))
            ->run();

        $result = Pulp::start()
            ->pipe(PulpCache::remember(
                Pulp::start()
                    ->pipe(Pulp::src($fresh))
                    ->pipe(Pulp::filter(static fn (): bool => false)),
                $cacheDirectory,
                ['key' => 'static', 'ttl' => 0, 'keepCacheWhenEmpty' => true]
            ))
            ->run();

        $this->assertSame(['kept' => true], $result[0]->content);
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/pulp-cache-test-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
