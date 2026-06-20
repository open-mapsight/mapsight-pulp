<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\dev\test;

use OpenMapsight\pulp\File;
use OpenMapsight\Pulp;
use OpenMapsight\PulpConcert;
use OpenMapsight\pulpconcert\ParkingData\Model\ParkingData;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class ParkingDataTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Berlin');
    }

    public function testOcpi2ShortTermCurrentValuesAreParsed(): void
    {
        $file = new File('parking.xml');
        $file->content = new SimpleXMLElement(<<<'XML'
<root>
    <ds>
        <identifier>
            <ident>parking-source-id</ident>
            <source>ocpi2</source>
        </identifier>
        <tstore>2024-01-10T10:15:00Z</tstore>
        <objectState>active</objectState>
        <data>
            <Id>parking-1</Id>
            <Timeline>
                <Timestamp>2024-01-10T10:15:00Z</Timestamp>
            </Timeline>
            <OpeningState>open</OpeningState>
            <State>ok</State>
            <Value>
                <Type>shortterm</Type>
                <Occupancy>12</Occupancy>
                <Capacity>40</Capacity>
                <Trend>increasing</Trend>
                <DriveIn>5</DriveIn>
                <DriveOut>2</DriveOut>
                <PredictionInterval>0</PredictionInterval>
            </Value>
            <Value>
                <Type>shortterm</Type>
                <Occupancy>18</Occupancy>
                <Capacity>40</Capacity>
                <Trend>decreasing</Trend>
                <DriveIn>8</DriveIn>
                <DriveOut>3</DriveOut>
                <PredictionInterval>0</PredictionInterval>
            </Value>
            <Value>
                <Type>shortterm</Type>
                <Occupancy>30</Occupancy>
                <Capacity>40</Capacity>
                <Trend>constant</Trend>
                <DriveIn>13</DriveIn>
                <DriveOut>5</DriveOut>
                <PredictionInterval>15</PredictionInterval>
            </Value>
        </data>
    </ds>
</root>
XML);

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpConcert::parseParkingData())
            ->run();

        $this->assertCount(1, $res);
        $parkingData = $res[0]->content['parking-1'] ?? null;
        $this->assertInstanceOf(ParkingData::class, $parkingData);
        $this->assertSame(30, $parkingData->getOccupancy());
        $this->assertSame(80, $parkingData->getCapacity());
        $this->assertSame(13, $parkingData->getDriveIn());
        $this->assertSame(5, $parkingData->getDriveOut());
        $this->assertSame('increasing', $parkingData->getTrend());
    }
}
