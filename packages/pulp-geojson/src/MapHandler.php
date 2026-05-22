<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class MapHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cb', 'options'];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        $file->content = Utils::mapFilterFeatures(
            $file->content,
            fn($feature) => $this->cp->cb($feature),
            $this->cp->options['skipExceptions'] ?? false === true
        );

        $this->pushFile($file);
    }
}
