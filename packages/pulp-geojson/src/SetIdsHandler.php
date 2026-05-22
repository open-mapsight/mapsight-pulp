<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class SetIdsHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        $countByIntermId = [];

        $file->content = Utils::mapFilterFeatures(
            $file->content,
            static function (array $feature) use (&$countByIntermId): array {
                $props = $feature['properties'] ?? [];

                $id = $feature['id'] ?? $props['id'] ?? $props['title'] ?? 'pulp_geojson';

                if (!is_string($id) && !is_int($id)) {
                    $id = (string) $id;
                }

                if (isset($countByIntermId[$id])) {
                    $countByIntermId[$id] += 1;
                    $id .= '___' . $countByIntermId[$id];
                } else {
                    $countByIntermId[$id] = 1;
                }

                $feature['id'] = $id;
                $props['id'] = $id;

                $feature['properties'] = $props;

                return $feature;
            }
        );

        $this->pushFile($file);
    }
}
