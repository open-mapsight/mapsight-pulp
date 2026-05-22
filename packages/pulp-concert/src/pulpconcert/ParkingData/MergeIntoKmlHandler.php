<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData;

use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractMergeIntoKMLHandler;
use OpenMapsight\pulpconcert\ParkingData\Model\ParkingData;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class MergeIntoKmlHandler extends AbstractMergeIntoKMLHandler
{
    protected $PARAMETER_DATA_PULP = 'parkingDataPulp';

    private array $tendencyLabelMap = [
        ParkingData::TENDENCY_CONSTANT => 'konstant',
        ParkingData::TENDENCY_DECREASING => 'fallend',
        ParkingData::TENDENCY_INCREASING => 'steigend',
    ];

    private array $openingStateLabelMap = [
        ParkingData::OPENING_STATE_OPEN => 'offen',
        ParkingData::OPENING_STATE_CLOSED => 'geschlossen',
    ];

    private array $stateLabelMap = [
        ParkingData::STATE_OKAY => 'o.k.',
        ParkingData::STATE_NOT_OKAY => 'unbekannt',
    ];

    /**
     * @param ParkingData $featureData
     *
     * @return bool
     */
    protected function isFeatureStatusOkay($featureData): bool
    {
        return $featureData->getState() === ParkingData::STATE_OKAY;
    }

    /**
     * @param ParkingData $featureData
     *
     * @return int
     */
    protected function getFeatureTimestamp($featureData): int
    {
        return Utils::parsePulpDateTimeObject($featureData->getTimestamp())->getTimestamp();
    }

    /**
     * @param ParkingData $featureData
     * @param SimpleXMLElement $targetObject
     * @param File $file
     * @param                   $data
     */
    protected function mergeFeatureProperties($featureData, &$targetObject, &$file, $data)
    {
        // TODO: use whitelist (as used for GeoJSON) instead
        $this->addData($targetObject, 'stellplaetze', $featureData->getCapacity());
        $this->addData($targetObject, 'freieplaetze', $featureData->getFree());
        $this->addData($targetObject, 'belegteplaetze', $featureData->getOccupancy());
        $this->addData($targetObject, 'auslastung', $featureData->getOccupancyRate());
        $this->addData($targetObject, 'auslastung_wert', $featureData->getOccupancyRate());
        $this->addData($targetObject, 'zeitpunkt', $featureData->getTimestamp());
        $this->addData($targetObject, 'tendenz', $this->tendencyLabelMap[$featureData->getTrend()]);
        $this->addData($targetObject, 'status', $this->stateLabelMap[$featureData->getState()]);
        $this->addData($targetObject, 'oeffnungszustand', $this->openingStateLabelMap[$featureData->getOpeningState()]);

        $free = $featureData->getFree();
        $occupancyRate = $featureData->getOccupancyRate();
        $tendencyLabel = $this->tendencyLabelMap[$featureData->getTrend()];
        $openingStateLabel = $this->openingStateLabelMap[$featureData->getOpeningState()];

        $current = '<h3>Status</h3>';
        $current .= $openingStateLabel . '<br>';
        $current .= 'Aktuelle Belegung: ' . $occupancyRate . '%<br>';
        $current .= 'Freie Plätze: ' . $free . '<br>';
        $current .= 'Tendenz: ' . $tendencyLabel . '<br><br>';
        $this->addCData('currentData', $current, $targetObject);
    }
}
