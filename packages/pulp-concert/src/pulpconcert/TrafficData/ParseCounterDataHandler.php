<?php

declare(strict_types=1);
/** @noinspection PhpUndefinedFieldInspection */

namespace OpenMapsight\pulpconcert\TrafficData;

use Exception;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Mapper\CounterDataMapper;
use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseCounterDataHandler extends AbstractProjectionHandler
{
    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse($data, Counter::class, static fn(SimpleXMLElement $e, Counter $counter): array => CounterDataMapper::mapData($e, $counter));

        $this->pushFile($file);
    }
}
