<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Mapper\Legacy;

use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Model\SubSection;
use SimpleXMLElement;

class SubSectionMapper
{
    public static function mapSubSection(
        AbstractProjectionHandler $projectionHandler,
        SimpleXMLElement $e,
        SubSection $subSection
    ): false|SubSection {
        if (!$subSection->getIdentifier()) {
            return false;
        }

        if ($e->data === false) {
            return false;
        }

        if ($e->data->Id !== false) {
            $subSection->setId((string) $e->data->Id);
        } else {
            return false;
        }

        if ($e->data->Name !== false) {
            $subSection->setName((string) $e->data->Name);
        } else {
            return false;
        }

        if ($e->data->Location->roaddescription && $e->data->Location->roaddescription->direction !== false) {
            $subSection->setDirection((string) $e->data->Location->roaddescription->direction);
        }

        $coordinates = [];

        /** @var SimpleXMLElement $coordinateDescriptionElement */
        if ($e->data->Location->co_description !== null) {
            foreach ($e->data->Location->co_description as $coordinateDescriptionElement) {
                /** @var SimpleXMLElement $coordinateElement */
                foreach ($coordinateDescriptionElement->co as $coordinateElement) {
                    if ($coordinateElement->x !== false) {
                        $x = (string) $coordinateElement->x;
                    } else {
                        continue;
                    }

                    if ($coordinateElement->y !== false) {
                        $y = (string) $coordinateElement->y;
                    } else {
                        continue;
                    }

                    $coordinatePair = $projectionHandler->transformPoint(['x' => $x, 'y' => $y]);
                    if ($coordinates === [] || $coordinatePair !== end($coordinates)) {
                        $coordinates[] = $coordinatePair;
                    }
                }
            }
        }

        if (count($coordinates) < 1) {
            // TODO: Handle no coordinates
            return false;
        }

        $subSection->setCoordinates($coordinates);

        return $subSection;
    }
}
