<?php

declare(strict_types=1);

namespace OpenMapsight\pulpjson;

use JsonException;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class EncodeJSONPHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['paddingName', 'options'];
    }

    /**
     * @throws JsonException
     */
    public function onFile(File $file): void
    {
        $content = json_encode($file->content, JSON_THROW_ON_ERROR | ($this->cp->options ?? 0));
        $content = $this->cp->paddingName . '(' . $content . ');';
        $file->content = $content;
        $this->pushFile($file);
    }
}
