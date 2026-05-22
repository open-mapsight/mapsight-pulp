<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Mapper;

use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Model\TrafficMessage;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class TrafficMessageMapper
{
    public static function mapTrafficMessage(AbstractProjectionHandler $projectionHandler, SimpleXMLElement $e, TrafficMessage $trafficMessage): TrafficMessage
    {
        if ($e->data->admin->id !== false) {
            $trafficMessage->setId((string) $e->data->admin->id);
        }

        if ($e->data->admin->name !== false) {
            $trafficMessage->setName((string) $e->data->admin->name);
        }

        if ($e->data->description !== false) {
            $trafficMessage->setDescription((string) $e->data->description);
        }

        if ($e->data->admin->subtype !== false) {
            $trafficMessage->setSubType((string) $e->data->admin->subtype);
        }

        if ($e->data->admin->severity !== false) {
            $trafficMessage->setSeverity((string) $e->data->admin->severity);
        }

        if ($e->data->admin->category !== false) {
            $trafficMessage->setCategory((string) $e->data->admin->category);
        }

        foreach ($e->data->location->roaddescription->road as $roadName) {
            $previous = trim((string) $trafficMessage->getRoadName());
            $previous = $previous === '' || $previous === '0' ? '' : $previous . ', ';
            $trafficMessage->setRoadName($previous . ((string) $roadName));
        }

        foreach ($e->xpath('./data/validity[@kind=\'validity\']/from') as $val) {
            $previous = $trafficMessage->getTotalValidityStart();
            $dateTime = Utils::parseConcertDateTimeObject($val);

            if (
                $previous === null ||
                Utils::parsePulpDateTimeObject($previous) > $dateTime
            ) {
                $trafficMessage->setTotalValidityStart(Utils::formatDateTimeObject($dateTime));
            }
        }

        foreach ($e->xpath('./data/validity[@kind=\'validity\']/until') as $val) {
            $previous = $trafficMessage->getTotalValidityEnd();
            $dateTime = Utils::parseConcertDateTimeObject($val);

            if (
                $previous === null ||
                Utils::parsePulpDateTimeObject($previous) < $dateTime
            ) {
                $trafficMessage->setTotalValidityEnd(Utils::formatDateTimeObject($dateTime));
            }
        }

        foreach ($e->xpath('./data/validity[@kind=\'daily_validity\']/from') as $val) {
            $previous = $trafficMessage->getDailyStartTime();
            $dateTime = Utils::parseConcertDateTimeObject($val);

            if (
                $previous === null ||
                Utils::parsePulpDateTimeObject($previous, TrafficMessage::TIME_FORMAT) > $dateTime
            ) {
                $trafficMessage->setDailyStartTime(Utils::formatDateTimeObject($dateTime, TrafficMessage::TIME_FORMAT));
            }
        }

        foreach ($e->xpath('./data/validity[@kind=\'daily_validity\']/until') as $val) {
            $previous = $trafficMessage->getDailyEndTime();
            $dateTime = Utils::parseConcertDateTimeObject($val);

            if (
                $previous === null ||
                Utils::parsePulpDateTimeObject($previous, TrafficMessage::TIME_FORMAT) < $dateTime
            ) {
                $trafficMessage->setDailyEndTime(Utils::formatDateTimeObject($dateTime, TrafficMessage::TIME_FORMAT));
            }
        }

        if ($e->data->location->restriction->other !== null) {
            foreach ($e->data->location->restriction->other as $restriction) {
                $restrictions = $trafficMessage->getRestrictions();
                $restrictions = is_array($restrictions) ? $restrictions : [];
                $restrictions[] = (string) $restriction;
                $trafficMessage->setRestrictions($restrictions);
            }
        }

        $geometries = self::mapGeometries($projectionHandler, $e);

        $trafficMessage->setGeometries($geometries);

        return $trafficMessage;
    }

    protected static function mapGeometries(AbstractProjectionHandler $projectionHandler, SimpleXMLElement $e): array
    {
        $geometries = [];
        if ($e->data->location->co_description !== null) {
            foreach ($e->data->location->co_description as $coordinateDescription) {
                $coordinates = [];

                foreach ($coordinateDescription->co as $coordinatePair) {
                    $coordinate = [];

                    if (($val = reset($coordinatePair->x)) !== false) {
                        $coordinate['x'] = (string) $val;
                    } else {
                        continue;
                    }

                    if (($val = reset($coordinatePair->y)) !== false) {
                        $coordinate['y'] = (string) $val;
                    } else {
                        continue;
                    }

                    $coordinates[] = $coordinate;
                }

                if (count($coordinates) === 0) {
                    continue;
                }

                if (count($coordinates) === 1) {
                    $geometries[] = [
                        'type' => 'Point',
                        'coordinates' => $projectionHandler->transformPoint($coordinates[0]),
                    ];
                } elseif (count($coordinates) < 4) {
                    $geometries[] = [
                        'type' => 'LineString',
                        'coordinates' => array_map($projectionHandler->transformPoint(...), $coordinates),
                    ];
                } else {
                    $first = reset($coordinates);
                    $last = end($coordinates);
                    $isArea = $first['x'] == $last['x'] && $first['y'] == $last['y'];

                    $coordinates = array_map($projectionHandler->transformPoint(...), $coordinates);
                    $geometries[] = [
                        'type' => $isArea ? 'Polygon' : 'LineString',
                        'coordinates' => $isArea ? [$coordinates] : $coordinates,
                    ];
                }
            }
        }
        return $geometries;
    }
}
