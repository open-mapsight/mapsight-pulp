<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpGeoCSV;
use OpenMapsight\pulpgeocsv\Domain\ProjectionInfo;
use PHPUnit\Framework\TestCase;

class CsvHandlerTest extends TestCase
{
    public function testCsvCanBeDecodedAndEncoded(): void
    {
        $file = new File('places.csv');
        $file->content = "name;x;y\nBerlin;13.4;52.5";

        $result = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoCSV::fromCsv())
            ->pipe(Pulp::results(function (array $files): void {
                $this->assertSame(['name', 'x', 'y'], $files[0]->content['columns']);
                $this->assertSame([['Berlin', '13.4', '52.5']], $files[0]->content['rows']);
            }))
            ->pipe(PulpGeoCSV::toCsv())
            ->run();

        $this->assertSame("name;x;y\nBerlin;13.4;52.5\n", $result[0]->content);
    }

    public function testReprojectAddsConfiguredOutputColumn(): void
    {
        $file = new File('places.csv');
        $file->content = [
            'type' => 'text/csv',
            'columns' => ['x', 'y'],
            'rows' => [
                ['13,4', '52,5'],
            ],
        ];

        $input = ProjectionInfo::build(
            ProjectionInfo::TYPE_X_Y,
            'x',
            'y',
            null,
            null,
            'EPSG:4326',
            ',',
            ','
        );
        $output = ProjectionInfo::build(
            ProjectionInfo::TYPE_XY,
            null,
            null,
            'coords',
            null,
            'EPSG:4326',
            ',',
            '|'
        );

        $result = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoCSV::reproject($input, $output))
            ->run();

        $this->assertSame(['x', 'y', 'coords'], $result[0]->content['columns']);
        $this->assertSame('13,4|52,5', $result[0]->content['rows'][0][2]);
    }
}
