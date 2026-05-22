<?php

declare(strict_types=1);

namespace OpenMapsight\pulpjson;

use JsonException;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class EncodeJSONHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['options'];
    }

    /**
     * @throws JsonException
     */
    public function onFile(File $file): void
    {
        $file->content = json_encode($file->content, JSON_THROW_ON_ERROR | ($this->cp->options ?? 0));
        $this->pushFile($file);
    }
}
