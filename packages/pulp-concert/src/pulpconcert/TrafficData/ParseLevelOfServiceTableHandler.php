<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use SimpleXMLElement;

class ParseLevelOfServiceTableHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = LosUtils::parseLosTable($data);
        $this->pushFile($file);
    }
}
