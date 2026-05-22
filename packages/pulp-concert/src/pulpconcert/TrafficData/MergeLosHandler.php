<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\Model\LosTargetInterface;
use OpenMapsight\pulpconcert\TrafficData\Model\Los;

class MergeLosHandler extends AbstractHandler
{
    private $losData;

    public function onFile(File $file): void
    {
        /** @var LosTargetInterface[] $elements */
        $elements = $file->content;
        $losData = $this->getLosData();

        foreach ($elements as $element) {
            $props = $element->getAdditionalProperties();
            $props ??= [];

            /** @var Los $losElement */
            foreach ($losData as $losElement) {
                if ($losElement->getRelatedId() == $element->getId()) {
                    $losProps = $losElement->getAllPropertiesAsArray();

                    $element->setAdditionalProperties(array_merge($props, $losProps));
                }
            }
        }


        $this->pushFile($file);
    }

    protected function getLosData()
    {
        if ($this->losData === null) {
            $results = $this->cp->losPulp->run();
            $this->losData = $results[0]->content;
        }

        return $this->losData;
    }

    protected function getConstructorParamDefs(): array
    {
        return ['losPulp'];
    }
}
