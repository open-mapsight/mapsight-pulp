<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class FilterGeometryHandler extends AbstractHandler
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

        $file->content = Utils::mapFilterGeometry(
            $file->content,
            fn($geometry) => $this->cp->cb($geometry) ? $geometry : null,
            $this->cp->options['skipExceptions'] ?? false === true
        );

        $this->pushFile($file);
    }
}
