<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use geoPHP;

use function is_string;
use function json_encode;

use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Throwable;

class ValidateGeometries extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['options'];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        $log = $this->cp->options['logSkipExceptions'] ?? 'stderr';

        $file->content = Utils::mapFilterFeatures(
            (array) $file->content,
            function (array $feature) use ($log): array {
                $idStr = !empty($feature['id']) && is_string($feature['id']) ? $feature['id'] : 'no id';

                return Utils::mapFilterGeometryFromFeature(
                    $feature,
                    function ($geom) use ($idStr, $log) {
                        try {
                            geoPHP::load(json_encode($geom), 'json');
                            return $geom;
                        } catch (Throwable $err) {
                            $err = new Exception(
                                'Processing GeoRss geometry failed for id "' . $idStr . '"',
                                0,
                                $err
                            );

                            if (
                                $this->cp->options['skipExceptions'] ?? false === true
                                && $log !== 'feature'
                            ) {
                                \OpenMapsight\pulp\Utils::log($log, $err);
                                return null;
                            }

                            throw $err;
                        }
                    },
                    $log === 'feature'
                );
            }
        );

        $this->pushFile($file);
    }
}
