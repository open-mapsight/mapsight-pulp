<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use Exception;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpgeojson\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testExtractFeaturesFeature(): void
    {
        $json = [
            'type' => 'Feature',
            'testKey' => 'testVal',
        ];

        $res = Utils::extractFeatures($json);

        $this->assertSame([$json], $res);
    }

    public function testExtractFeaturesUnrecognizedType(): void
    {
        $json = [
            'type' => 'FooBar',
            'testKey' => 'testVal',
        ];

        $exception = null;
        try {
            Utils::extractFeatures($json);
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(
            'Unrecognized GeoJSON type: FooBar',
            $exception->getMessage()
        );
    }

    public function testAssertGeoJsonFileUnsupportedType(): void
    {
        $types = ['FeatureCollection', 'Feature', 'FooBar'];
        foreach ($types as $typeI => $type) {
            $f = new File('testFile');
            $f->content = [
                'type' => $type,
                'testKey' => 'testVal',
            ];

            $exception = null;
            try {
                Utils::assertGeoJSONFile($f);
            } catch (Exception $e) {
                $exception = $e;
            }

            if ($typeI < 2) {
                $this->assertNull($exception);
            } else {
                $this->assertNotNull($exception);
                $this->assertSame(
                    'Unsupported root element type("FooBar") in "testFile"',
                    $exception->getMessage()
                );
            }
        }
    }
}
