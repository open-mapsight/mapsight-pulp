<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpGeoJSON\Utils;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class SetIdsHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input.*\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::setIds())
            ->run();

        $this->assertCount(4, $res);

        foreach ($res as $file) {
            Utils::assertFile($file);
            $ids = array_map(
                function (array $feature) {
                    $this->assertEquals($feature['id'], $feature['properties']['id']);
                    return $feature['id'];
                },
                Utils::extractFeatures($file->content)
            );

            // check for unique ids
            foreach (array_count_values($ids) as $count) {
                $this->assertEquals(1, $count);
            }
        }
    }
}
