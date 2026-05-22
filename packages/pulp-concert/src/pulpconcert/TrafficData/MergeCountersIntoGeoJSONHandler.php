<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use function json_encode;

use JsonSerializable;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractMergeIntoGeoJSONHandler;
use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\TrafficData\Model\LosTable;
use OpenMapsight\pulpconcert\Utils;

class MergeCountersIntoGeoJSONHandler extends AbstractMergeIntoGeoJSONHandler
{
    protected $PROPERTY_KEY_EXTERNAL_ID = 'id-messstelle';
    protected $PARAMETER_DATA_PULP = 'counterPulp';

    protected $propertyWhitelist = [
        'id',
        'name',
        'locationCity',
        'locationStreet',
        'locationCrossingName',
        'status',
        'numberOfLanes',
        'trafficSituation',
        'dataTimestamp',
        'dataInterval',
        'derivedDataTimestamp',
        'derivedDataInterval',
        'countData',
        'additionalProperties',
    ];
    private string $PARAMETER_LOS_TABLES_DATA_PULP = 'losTablesPulp';
    private string $PROPERTY_KEY_LOS_TABLE = 'los-Tabelle';
    private string $PROPERTY_KEY_SPEED_FACTOR = 'v-Faktor';
    private string $PROPERTY_KEY_TRAFFIC_SITUATION = 'trafficSituation';
    private string $PROPERTY_KEY_TRAFFIC_SITUATION_DATA = 'trafficSituationData';
    private $losTables;

    /**
     * @param Counter $featureData
     * @return bool
     */
    protected function isFeatureStatusOkay($featureData): bool
    {
        return $featureData->getStatus() === Counter::STATUS_OK;
    }

    /**
     * @param Counter $featureData
     * @return int
     */
    protected function getFeatureTimestamp($featureData): int
    {
        return Utils::parsePulpDateTimeObject($featureData->getTimestamp())->getTimestamp();
    }

    protected function getConstructorParamDefs(): array
    {
        return [
            $this->PARAMETER_DATA_PULP,
            'losTablesPulp',
            $this->PARAMETER_MERGE_NOT_OKAY,
            $this->PARAMETER_MAX_AGE,
        ];
    }

    /**
     * @param Counter|JsonSerializable $featureData
     * @param array $targetObject
     * @param File $file
     * @param $data
     */
    protected function mergeFeatureProperties($featureData, &$targetObject, &$file, $data)
    {
        parent::mergeFeatureProperties($featureData, $targetObject, $file, $data);
        $this->addTrafficSituationProperties($featureData, $targetObject);
    }

    /**
     * calculate Level of Service (LOS) aka traffic situation by LOS tables
     * @param Counter $featureData
     * @param array $targetObject
     */
    private function addTrafficSituationProperties(Counter $featureData, array &$targetObject): void
    {
        if (!isset($this->cp->losTablesPulp, $targetObject['properties']['LOS-Tabelle'])) {
            return;
        }

        $losTableName = $targetObject['properties']['LOS-Tabelle'];
        $losTables = $this->getLosTables();
        $speedFactor = (float) ($targetObject['properties']['v-Faktor'] ?? 1.0);

        if (!isset($losTables[$losTableName])) {
            if (defined('DEBUG') && DEBUG) {
                echo $featureData->getId(), ': LOS-Tabelle nicht gefunden: ', $losTableName, "\n\r";

                if (defined('DEBUG_SHOW_DATA') && DEBUG_SHOW_DATA === true) {
                    echo "\t", 'Daten: ', json_encode($featureData->jsonSerialize(), JSON_PRETTY_PRINT), "\n\r\n\r\n\r";
                }
            }

            return;
        }

        $losTable = $losTables[$losTableName];

        $Belegungsauswertung = isset($targetObject['properties']['Belegungsauswertung']) &&
            (int) $targetObject['properties']['Belegungsauswertung'] === 1;

        [$trafficSituationString, $losData] =
            LosUtils::calculateTrafficSituationByLevelOfServiceTable(
                $featureData,
                $losTable,
                $speedFactor,
                $Belegungsauswertung ? 'AlgoLOSOccBased' : 'AlgoLOSStandard'
            );

        $targetObject['properties'][$this->PROPERTY_KEY_TRAFFIC_SITUATION] = $trafficSituationString;
        $targetObject['properties'][$this->PROPERTY_KEY_TRAFFIC_SITUATION_DATA] = $losData;
    }

    /** @return LosTable[] */
    private function getLosTables(): array
    {
        if ($this->losTables === null) {
            $results = $this->cp->losTablesPulp->run();

            $this->losTables = [];
            foreach ($results as $file) {
                /** @var LosTable $losTable */
                $losTable = $file->content;
                $this->losTables[$losTable->getIdentifier()] = $losTable;
            }
        }

        return $this->losTables;
    }

    protected function handleFeature(&$targetObject, &$file, array $data)
    {
        $this->fallbackIdPropertyToExternalId($targetObject);
        parent::handleFeature($targetObject, $file, $data);
    }

    /**
     * @param array $targetObject
     */
    private function fallbackIdPropertyToExternalId(array &$targetObject): void
    {
        $externalId = $this->getFeatureExternalId($targetObject);

        // copy over id
        if (!isset($targetObject['properties']['id'])) {
            $targetObject['properties']['id'] = $externalId;
        }
    }
}
