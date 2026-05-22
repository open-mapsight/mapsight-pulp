<?php

declare(strict_types=1);

namespace OpenMapsight\pulpxml;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;
use SimpleXMLElement;

class GetXPathSingleHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['xPath'];
    }

    /**
     * @param SimpleXMLElement $parent
     * @param string $xPath
     * @return bool|SimpleXMLElement
     */
    public static function getSingleElementByXPath(SimpleXMLElement $parent, string $xPath): mixed
    {
        $array = $parent->xpath($xPath);
        return reset($array);
    }

    public function onFile(File $file): void
    {
        if (!$file->content instanceof SimpleXMLElement) {
            throw new RuntimeException(static::class . ' requires files containing a SimpleXMLElement');
        }

        $file->content = self::getSingleElementByXPath($file->content, $this->cp->xPath);
        $this->pushFile($file);
    }
}
