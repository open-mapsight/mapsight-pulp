<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpxml\GetXPathSingleHandler;
use OpenMapsight\pulpxml\ParseSimpleXMLHandler;
use OpenMapsight\pulpxml\StripXMLNamespacesHandler;

class PulpXML
{
    public static function stripXmlNamespaces(): StripXMLNamespacesHandler
    {
        return new StripXMLNamespacesHandler();
    }

    public static function parseSimpleXML(): ParseSimpleXMLHandler
    {
        return new ParseSimpleXMLHandler();
    }

    public static function getXPathSingle($xPath): GetXPathSingleHandler
    {
        return new GetXPathSingleHandler($xPath);
    }
}
