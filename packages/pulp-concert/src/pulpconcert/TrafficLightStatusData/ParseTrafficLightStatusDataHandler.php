<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficLightStatusData;

use Exception;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\TrafficLightStatusData\Model\TrafficLightStatus;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseTrafficLightStatusDataHandler extends AbstractHandler
{
    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse($data, TrafficLightStatus::class, static function (SimpleXMLElement $e, TrafficLightStatus $trafficLightStatus): array {
            if (!empty($e->data->Wert)) {
                // Legacy XML
                $value = $e->data->Wert;

                $trafficLightStatus->setId((string) $value->Identifier);
                $trafficLightStatus->setSignalPlan((int) $value->Signalplan);
                $trafficLightStatus->setStatus((string) $value->Status);
                $trafficLightStatus->setStatusCode((int) $value->Statuscode);
            } else {
                // OCPI2
                $value = $e->data;

                $trafficLightStatus->setId((string) $value->identifier);
                $trafficLightStatus->setSignalPlan((int) $value->signalProgram->spNr);
                //$trafficLightStatus->setStatus((string)$value->Status);
                $trafficLightStatus->setStatusCode((int) $value->deviceState);
            }


            return [$trafficLightStatus->getId(), $trafficLightStatus];
        });
        $this->pushFile($file);
    }
}
