<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData;

use Exception;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\ParkingData\Model\ParkingData;
use OpenMapsight\pulpconcert\ParkingData\Model\SubTypeOccupancyParkingData;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseParkingDataHandler extends AbstractHandler
{
    protected static function convertPHWerteOpeningState(string $phWerteString): string
    {
        if ($phWerteString === 'offen') {
            return ParkingData::OPENING_STATE_OPEN;
        }

        return ParkingData::OPENING_STATE_CLOSED;
    }

    protected static function convertPHWerteSubType(string $phWerteString): string
    {
        return match ($phWerteString) {
            'Kurzparker' => ParkingData::SUB_TYPE_SHORT_TERM,
            'Dauerparker' => ParkingData::SUB_TYPE_LONG_TERM,
            default => ParkingData::SUB_TYPE_OTHER,
        };
    }

    protected static function convertPHWerteTrend(string $phWerteString): ?string
    {
        return match ($phWerteString) {
            'steigend' => ParkingData::TENDENCY_INCREASING,
            'fallend' => ParkingData::TENDENCY_DECREASING,
            'gleichbleibend' => ParkingData::TENDENCY_CONSTANT,
            default => null,
        };
    }

    /**
     * @param SimpleXMLElement $e
     * @param ParkingData $parkingData
     * @return ParkingData
     * @throws Exception
     */
    protected static function mapPHWerte(SimpleXMLElement $e, ParkingData $parkingData): ParkingData
    {
        $base = $e->data->PH_Werte;

        $parkingData->setId((string) $base->Identifier);
        $parkingData->setTimestamp(Utils::convertConcertDateTime($base->Zeitstempel->Zeitpunkt));
        $parkingData->setOpeningState(self::convertPHWerteOpeningState((string) $base->Oeffnungszustand));
        $parkingData->setState((string) $base->Status);

        $shortTermOccupancy = 0;
        $shortTermCapacity = 0;
        $shortTermDriveIn = 0;
        $shortTermDriveOut = 0;

        $subTypeOccupancyData = [];
        foreach ($base->Parker as $subTypeBase) {
            $occupancyParkingData = new SubTypeOccupancyParkingData();
            $occupancyParkingData->setSubType(self::convertPHWerteSubType((string) $subTypeBase->Typ));
            $occupancyParkingData->setOccupancy((int) $subTypeBase->Belegung);
            $occupancyParkingData->setCapacity((int) $subTypeBase->Kapazitaet);
            $occupancyParkingData->setTrend(self::convertPHWerteTrend((string) $subTypeBase->Tendenz));
            $occupancyParkingData->setDriveIn((int) $subTypeBase->Eingefahrene);
            $occupancyParkingData->setDriveOut((int) $subTypeBase->Ausgefahrene);

            // Only short time occupancy is relevant for website
            if ($occupancyParkingData->getSubType() === ParkingData::SUB_TYPE_SHORT_TERM) {
                $shortTermOccupancy += $occupancyParkingData->getOccupancy();
                $shortTermCapacity += $occupancyParkingData->getCapacity();
                $shortTermDriveIn += $occupancyParkingData->getDriveIn();
                $shortTermDriveOut += $occupancyParkingData->getDriveOut();
            }

            $subTypeOccupancyData[] = $occupancyParkingData;
        }

        $parkingData->setOccupancy($shortTermOccupancy);
        $parkingData->setCapacity($shortTermCapacity);
        $parkingData->setDriveIn($shortTermDriveIn);
        $parkingData->setDriveOut($shortTermDriveOut);
        $parkingData->setTrend(array_reduce($subTypeOccupancyData, static function (SubTypeOccupancyParkingData $carry, SubTypeOccupancyParkingData $occupancyParkingData): SubTypeOccupancyParkingData {
            /** @var SubTypeOccupancyParkingData|null $carry */
            if (!$carry instanceof SubTypeOccupancyParkingData || $carry->getCapacity() < $occupancyParkingData->getOccupancy()) {
                return $occupancyParkingData;
            }

            return $carry;
        })->getTrend());
        $parkingData->setSubTypeOccupancyData($subTypeOccupancyData);

        return $parkingData;
    }

    /**
     * @param SimpleXMLElement $e
     * @param ParkingData $parkingData
     * @return ParkingData
     * @throws Exception
     */
    protected static function mapOcpi2(SimpleXMLElement $e, ParkingData $parkingData): ParkingData
    {
        /** @var SimpleXMLElement $data */
        $data = $e->data;
        $parkingData->setId((string) $data->Id);
        $parkingData->setTimestamp(Utils::convertConcertDateTime($data->Timeline->Timestamp));
        $parkingData->setOpeningState((string) $data->OpeningState);
        $parkingData->setState((string) $data->State);

        $shortTermOccupancy = 0;
        $shortTermCapacity = 0;
        $shortTermDriveIn = 0;
        $shortTermDriveOut = 0;

        $subTypeOccupancyData = [];
        foreach ($data->Value as $subTypeBase) {
            $occupancyParkingData = new SubTypeOccupancyParkingData();
            $occupancyParkingData->setSubType((string) $subTypeBase->Type);
            $occupancyParkingData->setOccupancy((int) $subTypeBase->Occupancy);
            $occupancyParkingData->setCapacity((int) $subTypeBase->Capacity);
            $occupancyParkingData->setTrend((string) $subTypeBase->Trend);
            $occupancyParkingData->setDriveIn((int) $subTypeBase->DriveIn);
            $occupancyParkingData->setDriveOut((int) $subTypeBase->DriveOut);
            $occupancyParkingData->setPredictionInterval((int) $subTypeBase->PredictionInterval);

            // Only _current_ (prediction interval = 0) short term occupancy is relevant for website
            if (
                $occupancyParkingData->getSubType() === ParkingData::SUB_TYPE_SHORT_TERM &&
                $occupancyParkingData->getPredictionInterval() === 0
            ) {
                $shortTermOccupancy += $occupancyParkingData->getOccupancy();
                $shortTermCapacity += $occupancyParkingData->getCapacity();
                $shortTermDriveIn += $occupancyParkingData->getDriveIn();
                $shortTermDriveOut += $occupancyParkingData->getDriveOut();
            }

            $subTypeOccupancyData[] = $occupancyParkingData;
        }

        $parkingData->setOccupancy($shortTermOccupancy);
        $parkingData->setCapacity($shortTermCapacity);
        $parkingData->setDriveIn($shortTermDriveIn);
        $parkingData->setDriveOut($shortTermDriveOut);
        $parkingData->setTrend(array_reduce($subTypeOccupancyData, static function (SubTypeOccupancyParkingData $carry, SubTypeOccupancyParkingData $occupancyParkingData): SubTypeOccupancyParkingData {
            /** @var SubTypeOccupancyParkingData|null $carry */
            if (!$carry instanceof SubTypeOccupancyParkingData || $carry->getCapacity() < $occupancyParkingData->getOccupancy()) {
                return $occupancyParkingData;
            }

            return $carry;
        })->getTrend());
        $parkingData->setSubTypeOccupancyData($subTypeOccupancyData);

        return $parkingData;
    }

    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse($data, ParkingData::class, static function (SimpleXMLElement $e, ParkingData $parkingData): array {
            if (!empty($e->data->PH_Werte)) {
                // as seen for VMZHB, Parkhäuser
                $parkingData = self::mapPHWerte($e, $parkingData);
            } else {
                // as seen for Stadt Braunschweig
                $parkingData = self::mapOcpi2($e, $parkingData);
            }

            return [$parkingData->getId(), $parkingData];
        });
        $this->pushFile($file);
    }
}
