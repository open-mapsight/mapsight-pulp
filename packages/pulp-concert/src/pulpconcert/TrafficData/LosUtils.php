<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use function json_encode;

use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\TrafficData\Model\CounterCountDatum;
use OpenMapsight\pulpconcert\TrafficData\Model\LosTable;
use OpenMapsight\pulpconcert\TrafficData\Model\LosTableAlgorithm;
use SimpleXMLElement;

class LosUtils
{
    public const ALGORITHM_OCCUPANCY_BASED = 'AlgoLOSOccBased';

    public static $TABLE_ARRAY_SEPARATOR = ';';
    public static $TABLE_MATRIX_SEPARATOR = '][';
    public static $TABLE_MATRIX_LEFT_TRIM = '[';
    public static $TABLE_MATRIX_RIGHT_TRIM = ']';

    /**
     * @param SimpleXMLElement $rootElement
     *
     * @return LosTable|null
     */
    public static function parseLosTable(SimpleXMLElement $rootElement)
    {
        if (!$rootElement->ParamSet) {
            return null;
        }

        /** @var LosTable $losTable */
        $losTable = new LosTable();

        /** @var SimpleXMLElement $e */
        $paramSetElement = $rootElement->ParamSet[0];

        $losTable->setIdentifier((string) $paramSetElement->attributes()->{'name'});

        // parse states
        /** @var boolean[] $algorithmStates */
        $algorithmStates = [];
        foreach ($paramSetElement->Algostate as $algorithmStateElement) {
            [$key, $value] = self::parseAlgorithmState($algorithmStateElement);
            $algorithmStates[$key] = $value;
        }

        // parse algorithms
        /** @var LosTableAlgorithm[] $algorithms */
        $algorithms = [];
        foreach ($paramSetElement->Params as $parametersElement) {
            $algorithms[] = self::parseAlgorithm($parametersElement);
        }

        // copy states to algorithm objects
        foreach ($algorithms as $algorithm) {
            $identifier = $algorithm->getIdentifier();

            if ($identifier && isset($algorithmStates[$identifier])) {
                $algorithm->setIsEnabled($algorithmStates[$identifier]);
            }
        }

        $losTable->setAlgorithms($algorithms);

        return $losTable;
    }

    /**
     * @param SimpleXMLElement $algorithmStateElement
     *
     * @return array
     */
    public static function parseAlgorithmState(SimpleXMLElement $algorithmStateElement): array
    {
        return [
            (string) $algorithmStateElement->attributes()->{'algoRef'},
            ((string) $algorithmStateElement->attributes()->{'enabled'}) === 'true',
        ];
    }

    /**
     * @param SimpleXMLElement $parametersElement
     *
     * @return LosTableAlgorithm
     */
    public static function parseAlgorithm(SimpleXMLElement $parametersElement): LosTableAlgorithm
    {
        $algorithm = new LosTableAlgorithm();

        // NOTE: Currently ignoring vehType attributes on Param elements completely assuming "all"
        // NOTE: Hysteresis are ignored (for now)

        foreach ($parametersElement->Param as $paramElement) {
            $key = (string) $paramElement->attributes()->{'name'};
            $value = (string) $paramElement;

            switch ($key) {
                case 'oThresholdArr':
                    $algorithm->setOThresholds(self::parseLosTableArray($value));
                    break;

                case 'vThresholdArr':
                    $algorithm->setVolumeThresholds(self::parseLosTableArray($value));
                    break;

                case 'sThresholdArr':
                    $algorithm->setSpeedThresholds(self::parseLosTableArray($value));
                    break;

                case 'LOSArr':
                    $algorithm->setLosMatrix(self::parseLosTableMatrix($value));
                    break;

                default:
            }
        }

        $algorithm->setIdentifier((string) $parametersElement->attributes()->{'algoRef'});

        return $algorithm;
    }

    /**
     * @param $arrayString
     *
     * @return number[]
     */
    public static function parseLosTableArray($arrayString): array
    {
        $values = explode(self::$TABLE_ARRAY_SEPARATOR, (string) $arrayString);
        $values = array_map(floatval(...), $values);

        return $values;
    }

    /**
     * @param $matrixString
     *
     * @return number[][]
     */
    public static function parseLosTableMatrix($matrixString): array
    {
        $matrixString = ltrim((string) $matrixString, self::$TABLE_MATRIX_LEFT_TRIM);
        $matrixString = rtrim($matrixString, self::$TABLE_MATRIX_RIGHT_TRIM);
        $arrayStrings = explode(self::$TABLE_MATRIX_SEPARATOR, $matrixString);

        return array_map(self::parseLosTableArray(...), $arrayStrings);
    }

    /**
     * @param Counter $counter
     * @param LosTable $losTable
     * @param float $speedFactor
     * @param string $algorithmName
     *
     * @return array<string, [array]>
     */
    public static function calculateTrafficSituationByLevelOfServiceTable(
        Counter $counter,
        LosTable $losTable,
        float $speedFactor = 1.0,
        string $algorithmName = 'AlgoLOSStandard'
    ): array {
        $counterCountDatum = $counter->getCountDataForType(CounterCountDatum::TYPE_TOTAL);

        if ($counterCountDatum === null) {
            self::handleCounterError('Daten fehlen', $counter);
            return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'MISSING_DATA']];
        }

        $volume = $counterCountDatum->getCountValue();
        $speed = $speedFactor * $counterCountDatum->getSpeed();
        $occupancyRate = $counterCountDatum->getOccupancyRate();

        $algorithm = $losTable->getAlgorithmByName($algorithmName);
        if (!$algorithm) {
            self::handleCounterError('Unbekannter Algorithmus', $counter);
            return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'ALGORITHM_NOT_IN_TABLE']];
        }

        if (!$losTable) {
            self::handleCounterError('LOS-Tabelle fehlt', $counter);
            return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'TABLE_NOT_FOUND']];
        }

        if (!$volume) {
            self::handleCounterError('Volumen fehlt', $counter);
            return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'MISSING_VOLUME']];
        }

        $volumeIndex = self::getThresholdIndex($algorithm->getVolumeThresholds(), $volume);

        if ($algorithmName === self::ALGORITHM_OCCUPANCY_BASED) {
            if (!$occupancyRate) {
                self::handleCounterError('Belegunswert fehlt', $counter);
                return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'MISSING_OCCUPANCY_RATE']];
            }

            $oIndex = self::getThresholdIndex($algorithm->getOThresholds(), $occupancyRate);
            $los = $algorithm->getLosMatrix()[$volumeIndex][$oIndex];
        } else {

            if ($speed === 0.0) {
                self::handleCounterError('Geschwindigkeit fehlt', $counter);
                return [Counter::TRAFFIC_SITUATION_UNKNOWN, ['error' => 'MISSING_SPEED']];
            }

            $speedIndex = self::getThresholdIndex($algorithm->getSpeedThresholds(), $speed);
            $los = $algorithm->getLosMatrix()[$volumeIndex][$speedIndex];
        }

        $trafficSituationString = match ((int) $los) {
            0 => Counter::TRAFFIC_SITUATION_FREE,
            1 => Counter::TRAFFIC_SITUATION_SLOW,
            2 => Counter::TRAFFIC_SITUATION_JAM,
            default => Counter::TRAFFIC_SITUATION_UNKNOWN,
        };

        return [$trafficSituationString, [
            'error' => false,
            'algorithm' => $algorithmName,
            'speedFactor' => $speedFactor,
            'volume' => $volume,
            'volumeIndex' => $volumeIndex,
            'occupancyRate' => $occupancyRate ?? null,
            'occupancyRateIndex' => $oIndex ?? null,
            'speed' => $speed ?? null,
            'speedIndex' => $speedIndex ?? null,
            'levelOfService' => $los,
        ]];
    }

    /**
     * @param $thresholds
     * @param $value
     *
     * @return int
     */
    private static function getThresholdIndex($thresholds, $value): int
    {
        $i = 0;
        while ($i < count($thresholds) && $value >= $thresholds[$i]) {
            $i++;
        }

        return $i - 1;
    }

    /**
     * @param string $description
     * @param Counter $counter
     */
    protected static function handleCounterError(string $description, Counter $counter)
    {
        if (defined('DEBUG') && DEBUG) {
            echo $counter->getId(), ': ', $description, "\n\r";

            if (defined('DEBUG_SHOW_DATA') && DEBUG_SHOW_DATA === true) {
                echo "\t", 'Meßstellendaten: ', json_encode($counter->jsonSerialize(), JSON_PRETTY_PRINT), "\n\r\n\r\n\r";
            }
        }
    }
}
