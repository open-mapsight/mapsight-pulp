<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpgeocsv\Domain\ProjectionInfo;
use OpenMapsight\pulpgeocsv\FromCsvHandler;
use OpenMapsight\pulpgeocsv\ReprojectHandler;
use OpenMapsight\pulpgeocsv\ToCsvHandler;

class PulpGeoCSV
{
    /** @noinspection PhpUnused */
    public static function fromCsv(
        string $fieldSeparator = ';',
        string $lineSeparator = "\n",
        string $quoteChar = '"'
    ): FromCsvHandler {
        return new FromCsvHandler($fieldSeparator, $lineSeparator, $quoteChar);
    }

    /** @noinspection PhpUnused */
    public static function reproject(
        ProjectionInfo $input,
        ProjectionInfo $output
    ): ReprojectHandler {
        return new ReprojectHandler($input, $output);
    }

    /** @noinspection PhpUnused */
    public static function toCsv(
        string $fieldSeparator = ';',
        string $lineSeparator = "\n",
        string $quoteChar = '"'
    ): ToCsvHandler {
        return new ToCsvHandler($fieldSeparator, $lineSeparator, $quoteChar);
    }
}
