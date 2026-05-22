<?php

declare(strict_types=1);

namespace OpenMapsight\pulpxml;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use SimpleXMLElement;

class StripXMLNamespacesHandler extends AbstractHandler
{
    private function stripNamespaceAttributes(string $xmlString): string
    {
        $xmlString = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlString);
        $xmlString = preg_replace('/(<\/*)[^>:]+:/', '$1', (string) $xmlString);
        $xmlString = preg_replace('/xsi:type="[^"]*"/i', '', (string) $xmlString);

        return $xmlString;
    }

    public function onFile(File $file): void
    {
        if ($file->content instanceof SimpleXMLElement) {
            $xmlString = $file->content->asXML();
            $xmlString = $this->stripNamespaceAttributes($xmlString);
            $file->content = ParseSimpleXMLHandler::parseXml($xmlString);
        } else {
            $file->content = $this->stripNamespaceAttributes((string) $file->content);
        }

        $this->pushFile($file);
    }
}
