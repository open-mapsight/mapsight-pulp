<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Mapper\Legacy;

use Exception;
use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\TrafficData\Model\CounterCountDatum;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class CounterMapper
{
    /**
     * @param SimpleXMLElement $e
     * @param Counter $counter
     * @return array|bool
     * @throws Exception
     */
    public static function mapDescription(SimpleXMLElement $e, Counter $counter): false|array
    {
        $base = $e->data->MBeschreibung;

        if ($base->Identifier !== false) {
            $counter->setId((string) $base->Identifier);
        } else {
            return false;
        }

        if ($base->Name !== false) {
            $counter->setName((string) $base->Name);
        } else {
            return false;
        }

        $location = $base->Lage;

        if ($location !== false && $location !== null) {
            if ($location->Stadt !== false) {
                $counter->setLocationCity((string) $location->Stadt);
            }

            if ($location->Strasse !== false) {
                $counter->setLocationStreet((string) $location->Strasse);
            }

            if ($location->Kreuzung !== false) {
                $counter->setLocationCrossingName((string) $location->Kreuzung);
            }

            if ($location->AnzahlErfassterSpuren !== false) {
                $counter->setNumberOfLanes((int) $location->AnzahlErfassterSpuren);
            }
        }


        if ($base->Subsystem !== false) {
            $counter->setAdditionalProperty('subsystem', (string) $base->Subsystem);
        }

        if ($base->Verbundene !== false && $base->Verbundene !== null) {
            $connectedIds = [];

            foreach ($base->Verbundene as $connectedId) {
                if ($connectedId !== false) {
                    $connectedIds[] = (string) $connectedId;
                }
            }

            if (count($connectedIds)) {
                $counter->setAdditionalProperty('connectedIds', $connectedIds);
            }
        }

        return [$counter->getId(), $counter];
    }

    /**
     * @param SimpleXMLElement $e
     * @param Counter $counter
     * @return array|bool
     * @throws Exception
     */
    public static function mapData(SimpleXMLElement $e, Counter $counter): false|array
    {
        if ($e->data === false || $e->data->Messwert === false || !$counter->getIdentifier()) {
            return false;
        }

        $base = $e->data->Messwert;

        if ($base->Identifier !== false) {
            $counter->setId((string) $base->Identifier);
        } else {
            return false;
        }

        if ($base->Status !== false && $counter->getStatus() === Counter::STATUS_UNKNOWN) {
            $counter->setStatus((string) $base->Status);
        }

        if ($base->Zeitstempel) {
            $time = $base->Zeitstempel;

            if ($time->Zeitpunkt !== false) {
                $counter->setTimestamp(Utils::convertConcertDateTime($time->Zeitpunkt));
            }

            if ($time->Intervalldauer !== false) {
                $counter->setDataInterval((int) $time->Intervalldauer);
            }

            if ($time->Erwartungszeitpunkt !== false) {
                $counter->setAdditionalProperty('dataExpectedTimestamp', (string) $time->Erwartungszeitpunkt);
            }
        }

        if ($base->Zaehlung !== null) {
            foreach ($base->Zaehlung as $count) {
                $countDatum = self::mapCountDatum($count);

                if ($countDatum !== false) {
                    $counter->addCountDatum($countDatum);
                }
            }
        }

        return [$counter->getId(), $counter];
    }

    protected static function mapCountDatum(SimpleXMLElement $e): CounterCountDatum
    {
        $countDatum = new CounterCountDatum();

        if ($e->Zaehltyp !== false) {
            $countDatum->setType((string) $e->Zaehltyp);
        } else {
            $countDatum->setType(CounterCountDatum::TYPE_OTHER);
        }

        if ($e->Zaehlwert !== false && (int) $e->Zaehlwert >= 0) {
            $countDatum->setCountValue((int) $e->Zaehlwert);
        }

        if ($e->Geschwindigkeit !== false && (int) $e->Geschwindigkeit > 0) {
            $countDatum->setSpeed((int) $e->Geschwindigkeit);
        }

        if ($e->BelegungInProzent !== false) {
            $countDatum->setOccupancyRate((int) $e->BelegungInProzent);
        }

        return $countDatum;
    }

    /**
     * @param SimpleXMLElement $e
     * @param Counter $counter
     * @return array|bool
     * @throws Exception
     */
    public static function mapDerivedData(SimpleXMLElement $e, Counter $counter): false|array
    {
        if ($e->data === false || $e->data->Berechnung === false || !$counter->getIdentifier()) {
            return false;
        }

        $base = $e->data->Berechnung;

        if ($base->Identifier !== false) {
            $counter->setId((string) $base->Identifier);
        } else {
            return false;
        }

        if ($base->Status !== false && $counter->getStatus() === Counter::STATUS_UNKNOWN) {
            $counter->setStatus((string) $base->Status);
        }

        if ($base->Zeitstempel) {
            $time = $base->Zeitstempel;

            if ($time->Zeitpunkt !== false) {
                $counter->setDerivedDataTimestamp(Utils::convertConcertDateTime($time->Zeitpunkt));
            }

            if ($time->Intervalldauer !== false) {
                $counter->setDerivedDataInterval((int) $time->Intervalldauer);
            }
        }

        if ($base->Verkehrslage !== false) {
            $counter->setTrafficSituation((string) $base->Verkehrslage);
        }

        return [$counter->getId(), $counter];
    }
}
