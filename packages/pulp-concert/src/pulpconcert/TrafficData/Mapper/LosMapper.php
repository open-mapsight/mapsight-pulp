<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Mapper;

use OpenMapsight\pulpconcert\TrafficData\Model\Los;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class LosMapper
{
    public static function mapLos(SimpleXMLElement $e, Los $los): false|Los
    {
        if (!$los->getIdentifier()) {
            return false;
        }

        if ($e->data === false) {
            return false;
        }

        if ($e->data->id !== false) {
            $los->setId((string) $e->data->id);
        } else {
            return false;
        }

        if ($e->data->los !== false) {
            $los->setLos((int) $e->data->los);
        }

        if ($e->data->predictedLos !== false) {
            $los->setPredictedLos((int) $e->data->predictedLos);
        }

        if ($e->data->predictionTimestamp !== false) {
            $los->setPredictionTimestamp(Utils::convertConcertDateTime($e->data->predictionTimestamp));
        }

        if ($e->data->quality !== false) {
            $los->setQuality((int) $e->data->quality);
        }

        $timeline = [];
        if ($e->data->timeline->timestamp !== false) {
            $timeline[] = Utils::convertConcertDateTime($e->data->timeline->timestamp);
        }
        $los->setTimeline($timeline);

        return $los;
    }
}
