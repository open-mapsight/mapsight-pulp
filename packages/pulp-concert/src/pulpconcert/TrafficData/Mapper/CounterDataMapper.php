<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Mapper;

use Exception;
use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\TrafficData\Model\CounterCountDatum;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class CounterDataMapper
{
    /**
     * @param SimpleXMLElement $e
     * @param Counter $counter
     * @return array|bool
     * @throws Exception
     */
    public static function mapData(SimpleXMLElement $e, Counter $counter): array
    {
        $base = $e->data;

        $counter->setId((string) $base->id);
        $counter->setStatus(self::mapStatus((int) $base->detectorState));

        $time = $base->timeline;
        $counter->setTimestamp(Utils::convertConcertDateTime($time->timestamp));
        $counter->setDataInterval((int) $time->cycle);
        //$counter->setAdditionalProperty('dataExpectedTimestamp', ???);

        foreach ($base->value as $count) {
            $counter->addCountDatum(self::mapCountDatum($count));
        }

        return [$counter->getId(), $counter];
    }

    protected static function mapStatus(int $state): string
    {
        return match ($state) {
            0 => Counter::STATUS_OK,
            1 => Counter::STATUS_NOK,
            2 => Counter::STATUS_OFFLINE,
            default => Counter::STATUS_UNKNOWN,
        };
    }

    protected static function mapCountDatum(SimpleXMLElement $e): CounterCountDatum
    {
        $countDatum = new CounterCountDatum();
        $attributes = $e->attributes();

        if ($attributes !== null) {
            if ($attributes->vehicle !== false) {
                $countDatum->setType(self::mapCountDatumType((string) $attributes->vehicle));
            } else {
                $countDatum->setType(CounterCountDatum::TYPE_OTHER);
            }

            if ($attributes->count !== false && (int) $attributes->count >= 0) {
                $countDatum->setCountValue((int) $attributes->count);
            }

            if ($attributes->speed !== false && (int) $attributes->speed > 0) {
                $countDatum->setSpeed((int) $attributes->speed);
            }

            if ($attributes->occ !== false) {
                $countDatum->setOccupancyRate((int) $attributes->occ);
            }
        }

        return $countDatum;
    }

    protected static function mapCountDatumType(string $type): string
    {
        return match ($type) {
            'all' => CounterCountDatum::TYPE_TOTAL,
            'car' => CounterCountDatum::TYPE_PKW,
            'car_with_trailer' => CounterCountDatum::TYPE_PKW_WITH_TRAILER,
            'truck' => CounterCountDatum::TYPE_LKW,
            'truck_with_semi_trailer' => CounterCountDatum::TYPE_LKW_WITH_TRAILER,
            'truck_with_trailer' => CounterCountDatum::TYPE_TRACTOR,
            'bus' => CounterCountDatum::TYPE_BUS,
            'delivery_truck' => CounterCountDatum::TYPE_DELIVERY_TRUCK,
            'motorcycle' => CounterCountDatum::TYPE_MOTOR_BIKE,
            'unclassified' => CounterCountDatum::TYPE_OTHER,
            default => CounterCountDatum::TYPE_OTHER,
        };
    }
}
