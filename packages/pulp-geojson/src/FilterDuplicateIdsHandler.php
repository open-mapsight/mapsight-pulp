<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class FilterDuplicateIdsHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        $ids = [];
        $file->content = Utils::mapFilterFeatures(
            $file->content,
            function (array $feature) use (&$ids): ?array {
                $id = $feature['id'] ?? $feature['properties']['id'] ?? null;

                // We keep features without an id. Use another filter to remove them if needed.
                if ($id === null) {
                    return $feature;
                }

                if (in_array((string) $id, $ids, true)) {
                    return null;
                }
                $ids[] = $id;
                return $feature;
            }
        );

        $this->pushFile($file);
    }
}
