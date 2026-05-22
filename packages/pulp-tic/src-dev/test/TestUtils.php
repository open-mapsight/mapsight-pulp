<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic\dev\test;

use DateTime;
use PHPUnit_Framework_TestCase;

class TestUtils
{
    public static function normalizeArray(array $arr): array
    {
        return array_map(
            function ($val) {
                if ($val instanceof DateTime) {
                    return $val->format(DateTime::ISO8601);
                }

                if (is_array($val)) {
                    return self::normalizeArray($val);
                }

                return $val;
            },
            $arr
        );
    }

    public static function assertJsonSameFile(string $fileExpected, $actually): void
    {
        $expected = file_get_contents(__DIR__ . '/../../test/files/' . $fileExpected);
        $expected = json_decode($expected, true);

        $actually = self::normalizeArray($actually);

        PHPUnit_Framework_TestCase::assertEquals(
            $expected,
            $actually,
            '',
            0.00001
        );
    }
}
