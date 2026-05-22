<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use Exception;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Mapper\TrafficMessageMapper;
use OpenMapsight\pulpconcert\TrafficData\Model\TrafficMessage;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseTrafficMessagesHandler extends AbstractProjectionHandler
{
    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse($data, TrafficMessage::class, function (SimpleXMLElement $e, TrafficMessage $trafficMessage): TrafficMessage|false {
            if ($trafficMessage->getIdentifier() && $trafficMessage->getObjectState() !== 'deleted') {
                return TrafficMessageMapper::mapTrafficMessage($this, $e, $trafficMessage);
            }

            return false;
        });
        $this->pushFile($file);
    }
}
