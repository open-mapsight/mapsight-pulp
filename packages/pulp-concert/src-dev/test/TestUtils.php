<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\dev\test;

use PHPUnit\Framework\TestCase;

class TestUtils
{
    public static function assertJsonSameFile(string $fileExpected, $actually): void
    {
        $expected = file_get_contents(__DIR__ . '/../../test/files/' . $fileExpected);
        $expected = json_decode($expected, true);
        TestCase::assertEqualsWithDelta(
            $expected,
            $actually,
            0.00001
        );
    }
}
