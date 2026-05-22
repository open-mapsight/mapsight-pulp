<?php

declare(strict_types=1);
/** @noinspection PhpUndefinedFieldInspection */

namespace OpenMapsight\pulpconcert\TrafficData;

use Exception;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Mapper\Legacy\CounterMapper as LegacyCounterMapper;
use OpenMapsight\pulpconcert\TrafficData\Model\Counter;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseCountersHandler extends AbstractProjectionHandler
{
    private $dataFiles = [];
    private $derivedDataFiles = [];

    public function onStart(): void
    {
        if ($this->cp->dataPulp) {
            foreach ($this->cp->dataPulp->run() as $result) {
                $this->dataFiles[] = $result;
            }
        }

        if ($this->cp->derivedDataPulp) {
            foreach ($this->cp->derivedDataPulp->run() as $result) {
                $this->derivedDataFiles[] = $result;
            }
        }
    }

    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $elements = Utils::parseConcertResponse($data, Counter::class, function (SimpleXMLElement $e, Counter $counter): array|false {
            if ($e->data !== false && $e->data->MBeschreibung !== false) {
                return LegacyCounterMapper::mapDescription($e, $counter);
            }

            return false;
        });

        foreach ($this->dataFiles as $dataFile) {
            $elements = Utils::parseConcertResponse($dataFile->content, Counter::class, fn(SimpleXMLElement $e, Counter $counter): array|false => LegacyCounterMapper::mapData($e, $counter), $elements);
        }

        foreach ($this->derivedDataFiles as $derivedDataFile) {
            $elements = Utils::parseConcertResponse($derivedDataFile->content, Counter::class, fn(SimpleXMLElement $e, Counter $counter): array|false => LegacyCounterMapper::mapDerivedData($e, $counter), $elements);
        }

        $file->content = $elements;

        $this->pushFile($file);
    }

    protected function getConstructorParamDefs(): array
    {
        return ['dataPulp', 'derivedDataPulp'];
    }
}
