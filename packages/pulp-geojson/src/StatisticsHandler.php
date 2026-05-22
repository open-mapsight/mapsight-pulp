<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class StatisticsHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            [
                'key' => 'name',
                'default' => 'statistics',
            ],
        ];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        /** @var array $json */
        $json = $file->content;
        $numberOfFeatures = count(Utils::extractFeatures($json));

        $names = $this->cp->name;

        if (!is_array($names)) {
            $names = [$names];
        }

        $curr = &$json;
        foreach ($names as $i => $name) {
            if (!isset($curr[$name])) {
                $curr[$name] = [];
            }

            if (!is_array($curr[$name])) {
                $name = array_slice($names, 0, $i + 1);
                $name = implode(' => ', $name);
                throw new Exception('key "' . $name . '" exists, but is not an array');
            }

            $curr = &$curr[$name];
        }

        $curr = array_merge($curr, ['featureCount' => $numberOfFeatures]);

        $file->content = $json;
        $this->pushFile($file);
    }
}
