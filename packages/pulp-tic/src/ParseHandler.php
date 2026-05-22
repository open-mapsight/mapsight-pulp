<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulptic\OpenMapsight\pulptic\Tic2\Utils;
use SimpleXMLElement;

class ParseHandler extends AbstractHandler
{
    protected static $tic2EventXPath = '/IFS/IFN';
    protected static $tic3EventXPath = '/TIC/TrafficAndTravelEvent';
    protected static $tic3TemplateXPath = '/TIC/Template';

    public function onFile(File $file): void
    {
        $dom = simplexml_load_string($file->content);
        $file->content = $this->parse($dom);
        $this->pushFile($file);
    }

    /**
     * @param SimpleXMLElement $dom
     *
     * @return array
     */
    private function parse(SimpleXMLElement $dom): array
    {
        // auto detect tic2 or tic3 xml format
        if ($dom->xpath(self::$tic2EventXPath)) {
            return array_map(Utils::parseEntry(...), $dom->xpath(self::$tic2EventXPath));
        }
        if ($dom->xpath(self::$tic3EventXPath)) {
            return array_map(OpenMapsight\pulptic\Tic3\Utils::parseEntry(...), $dom->xpath(self::$tic3EventXPath));
        }
        if ($dom->xpath(self::$tic3TemplateXPath)) {
            return array_map(OpenMapsight\pulptic\Tic3\Utils::parseTemplateEntry(...), $dom->xpath(self::$tic3TemplateXPath));
        }
        // TODO: Error/exception handling
        return [];
    }
}
