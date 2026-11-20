<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepairDoubleEncodedUtf8HandlerTest extends TestCase
{
    #[DataProvider('texts')]
    public function testRepairsOnlyLosslesslyDoubleEncodedText(string $input, string $expected): void
    {
        $this->assertSame($expected, Utils::repairDoubleEncodedUtf8($input));
    }

    public static function texts(): array
    {
        return [
            'umlaut' => ["H\u{00C3}\u{00B6}henweg", 'Höhenweg'],
            'sharp s via C1 control' => ["M\u{00C3}\u{00BC}hlenstra\u{00C3}\u{009F}e", 'Mühlenstraße'],
            'three-byte character' => ["\u{00E2}\u{0082}\u{00AC} 5", '€ 5'],
            'already correct' => ['Höhenweg', 'Höhenweg'],
            'ascii' => ['Hoehenweg', 'Hoehenweg'],
            'mixed forms stay' => ["Höhenweg H\u{00C3}\u{00B6}henweg", "Höhenweg H\u{00C3}\u{00B6}henweg"],
            'beyond latin-1 stays' => ["H\u{00C3}\u{00B6}henweg €", "H\u{00C3}\u{00B6}henweg €"],
            'not valid utf-8 after repair' => ["\u{00C3}\u{00B6}\u{00C3}", "\u{00C3}\u{00B6}\u{00C3}"],
            'empty' => ['', ''],
        ];
    }

    public function testRepairsStringContentAsAWhole(): void
    {
        $file = new File('places.json');
        $file->content = "{\"name\":\"M\u{00C3}\u{00BC}hle\"}";

        $files = Pulp::start()->pipe(Pulp::repairDoubleEncodedUtf8())->run([$file]);

        $this->assertSame('{"name":"Mühle"}', $files[0]->content);
    }

    public function testRepairsEveryStringValueOfArrayContent(): void
    {
        $file = new File('places.geojson');
        $file->content = [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'id' => 7,
                'properties' => [
                    'name' => "Au\u{00C3}\u{009F}enstelle",
                    'tags' => ["Gr\u{00C3}\u{00BC}n", 'Höhe'],
                    "Gr\u{00C3}\u{00B6}\u{00C3}\u{009F}e" => 3,
                    'open' => true,
                ],
                'geometry' => ['type' => 'Point', 'coordinates' => [8.5, 50.25]],
            ]],
        ];

        $files = Pulp::start()->pipe(Pulp::repairDoubleEncodedUtf8())->run([$file]);
        $feature = $files[0]->content['features'][0];

        $this->assertSame('Außenstelle', $feature['properties']['name']);
        $this->assertSame(['Grün', 'Höhe'], $feature['properties']['tags']);
        $this->assertSame(3, $feature['properties']["Gr\u{00C3}\u{00B6}\u{00C3}\u{009F}e"], 'keys stay unchanged');
        $this->assertTrue($feature['properties']['open']);
        $this->assertSame(7, $feature['id']);
        $this->assertSame([8.5, 50.25], $feature['geometry']['coordinates']);
    }
}
