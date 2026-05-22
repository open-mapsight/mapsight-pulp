<?php

declare(strict_types=1);

namespace OpenMapsight\pulpxml;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use SimpleXMLElement;

class ParseSimpleXMLHandler extends AbstractHandler
{
    public static function parseXml(string $xmlString): SimpleXMLElement
    {
        return simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NSCLEAN);
    }

    public function onFile(File $file): void
    {
        $file->content = self::parseXml($file->content);
        $this->pushFile($file);
    }
}
