<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testEnsureDirectoryCreatesAndReturnsPath(): void
    {
        $directory = sys_get_temp_dir() . '/pulp-ensure-' . bin2hex(random_bytes(8)) . '/nested';

        try {
            $this->assertSame($directory, Pulp::ensureDirectory($directory));
            $this->assertDirectoryExists($directory);
            $this->assertSame($directory, Pulp::ensureDirectory($directory));
        } finally {
            @rmdir($directory);
            @rmdir(dirname($directory));
        }
    }

    public function testEnsureDirectoryRejectsEmptyPath(): void
    {
        $this->expectException(RuntimeException::class);
        Pulp::ensureDirectory('');
    }
}
