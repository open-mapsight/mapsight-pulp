<?php

declare(strict_types=1);
/** @noinspection PhpUndefinedFieldInspection */

namespace OpenMapsight\pulpconcert\TrafficData;

use Exception;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\TrafficData\Mapper\LosMapper;
use OpenMapsight\pulpconcert\TrafficData\Model\Los;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseLosHandler extends AbstractHandler
{
    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse(
            $data,
            Los::class,
            // NOTE: no legacy support!
            static fn(SimpleXMLElement $e, Los $los): Los|false => LosMapper::mapLos($e, $los)
        );
        $this->pushFile($file);
    }
}
