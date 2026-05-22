<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

abstract class AbstractMergeIntoHandler extends AbstractHandler
{
    protected $PARAMETER_DATA_PULP = 'dataPulp';
    protected $PARAMETER_MERGE_NOT_OKAY = 'mergeNotOkay';
    protected $PARAMETER_MAX_AGE = 'maxAge';
    protected $data = [];

    public function onFile(File $file): void
    {
        $file = $this->loopFeatures($file, $this->getData($this->PARAMETER_DATA_PULP));
        $this->pushFile($file);
    }

    /**
     * @param File $file
     * @param mixed $data
     *
     * @return mixed
     */
    abstract protected function loopFeatures($file, $data);

    protected function getData($parameterName)
    {
        if (empty($this->data[$parameterName])) {
            $results = $this->cp->{$parameterName}->run();
            $this->data[$parameterName] = $results[0]->content;
        }

        return $this->data[$parameterName];
    }

    protected function getConstructorParamDefs(): array
    {
        return [$this->PARAMETER_DATA_PULP, $this->PARAMETER_MERGE_NOT_OKAY, $this->PARAMETER_MAX_AGE];
    }

    /**
     * @param      $targetObject
     * @param File $file
     * @param      $data
     */
    protected function handleFeature(&$targetObject, &$file, array $data)
    {
        $externalId = $this->getFeatureExternalId($targetObject);

        // skip if no data is found
        if (empty($externalId) || empty($data[$externalId])) {
            return;
        }

        $featureData = $data[$externalId];

        // skip if status is not okay
        if ($this->cp->{$this->PARAMETER_MERGE_NOT_OKAY} !== true && !$this->isFeatureStatusOkay($featureData)) {
            return;
        }

        // skip if too old
        if ($this->cp->{$this->PARAMETER_MAX_AGE} > 0) {
            $ts = $this->getFeatureTimestamp($featureData);
            $oldestTs = time() - $this->cp->{$this->PARAMETER_MAX_AGE};

            if ($ts < $oldestTs) {
                return;
            }
        }

        $this->mergeFeatureProperties($featureData, $targetObject, $file, $data);
    }

    /**
     * @param mixed $targetObject
     *
     * @return mixed
     */
    abstract protected function getFeatureExternalId($targetObject);

    /**
     * @param mixed $featureData
     *
     * @return bool
     */
    abstract protected function isFeatureStatusOkay($featureData): bool;

    /**
     * @param mixed $featureData
     *
     * @return int
     */
    abstract protected function getFeatureTimestamp($featureData): int;

    abstract protected function mergeFeatureProperties($featureData, &$targetObject, &$file, $data);
}
