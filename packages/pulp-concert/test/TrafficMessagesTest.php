<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\dev\test;

use OpenMapsight\pulp\File;
use OpenMapsight\Pulp;
use OpenMapsight\PulpConcert;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class TrafficMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Berlin');
    }

    public function testTrafficMessagesGeoJsonDoesNotTriggerSimpleXmlResetDeprecation(): void
    {
        $file = new File('traffic-message.xml');
        $file->content = new SimpleXMLElement(<<<'XML'
<dataList>
    <ds>
        <identifier>
            <ident>traffic-message-source-id</ident>
            <source>braunschweig</source>
        </identifier>
        <tstore>2024-01-10T10:15:00Z</tstore>
        <objectState>active</objectState>
        <data>
            <admin>
                <id>message-1</id>
                <name>Roadworks A391</name>
                <subtype>roadworks</subtype>
                <severity>medium</severity>
                <category>traffic</category>
            </admin>
            <description>Lane closure</description>
            <validity kind="validity">
                <from>2024-01-10T10:00:00Z</from>
                <until>2024-01-10T12:00:00Z</until>
            </validity>
            <validity kind="daily_validity">
                <from>2024-01-10T08:00:00Z</from>
                <until>2024-01-10T18:00:00Z</until>
            </validity>
            <location>
                <roaddescription>
                    <road>A391</road>
                    <road>B4</road>
                </roaddescription>
                <restriction>
                    <other>left lane closed</other>
                </restriction>
                <co_description>
                    <co>
                        <x>605000</x>
                        <y>5792000</y>
                    </co>
                    <co>
                        <x>605100</x>
                        <y>5792100</y>
                    </co>
                </co_description>
            </location>
        </data>
    </ds>
</dataList>
XML);

        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_DEPRECATED && str_contains($message, 'reset(): Calling reset() on an object is deprecated')) {
                $deprecations[] = $message;
                return true;
            }

            return false;
        });

        try {
            $res = Pulp::start()
                ->pipe(Pulp::src($file))
                ->pipe(PulpConcert::parseTrafficMessages())
                ->pipe(PulpConcert::trafficMessagesToGeoJSON())
                ->run();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertCount(1, $res);
        $this->assertEqualsWithDelta(
            [
                'type' => 'FeatureCollection',
                'crs' => ['type' => 'EPSG', 'properties' => ['code' => '4326']],
                'features' => [
                    [
                        'type' => 'Feature',
                        'id' => 'message-1',
                        'geometry' => [
                            'type' => 'GeometryCollection',
                            'geometries' => [
                                [
                                    'type' => 'LineString',
                                    'coordinates' => [
                                        [10.538748949971936, 52.26834302487074],
                                        [10.540245004079699, 52.26922265146747],
                                    ],
                                ],
                            ],
                        ],
                        'properties' => [
                            'name' => 'Roadworks A391',
                            'description' => 'Lane closure',
                            'subType' => 'roadworks',
                            'category' => 'traffic',
                            'roadName' => 'A391, B4',
                            'restrictions' => 'left lane closed',
                            'id' => 'message-1',
                        ],
                        'when' => [
                            'start' => '2024-01-10T11:00:00+0100',
                            'end' => '2024-01-10T13:00:00+0100',
                            'dailyStartTime' => '09:00:00+01:00',
                            'dailyEndTime' => '19:00:00+01:00',
                        ],
                    ],
                ],
            ],
            $res[0]->content,
            0.00001
        );
    }
}
