<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use geoPHP;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Point;

class SpreadPointClustersHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            [
                'key' => 'options',
                'default' => [
                    'spread' => 0.00002,
                    'spreadFunction' => self::class . '::arrangeNodes',
                ],
                'handler' => 'merge',
            ],
        ];
    }

    /** @noinspection PhpUnused */
    public static function arrangeNodes($radius, $centerPoint, $totalNumberOfNodes): array
    {
        $newNodes = [];
        $totalProcessed = 0;

        $curDistanceAwayFromCenter = $radius * 2;

        // Place the very first circle in the center, as the algorithm won't work for n==1
        $newNodes[] = $centerPoint;
        $totalProcessed++;

        while ($totalProcessed < $totalNumberOfNodes) {
            $numberThatFits = round(M_PI / asin($radius / $curDistanceAwayFromCenter));

            $numberToFit = $numberThatFits;
            if ($numberToFit > $totalNumberOfNodes - $totalProcessed) {
                $numberToFit = $totalNumberOfNodes - $totalProcessed;
            }

            for ($j = 0; $j < $numberToFit; $j++) {
                // ang to center
                $ang = M_PI * 2 * $j / $numberThatFits - M_PI;
                $np = [];
                $np[0] = $centerPoint[0] + cos($ang) * $curDistanceAwayFromCenter;
                $np[1] = $centerPoint[1] + sin($ang) * $curDistanceAwayFromCenter;

                $newNodes[] = $np;
                $totalProcessed++;
            }

            $curDistanceAwayFromCenter += $radius * 2;
        }

        return $newNodes;
    }

    /** @noinspection PhpUnused */
    public static function arrangeNodesSpread($radius, $centerPoint, $totalNumberOfNodes): array
    {
        $newNodes = [];
        $totalProcessed = 0;

        $curDistanceAwayFromCenter = $radius * 2;

        // Place the very first circle in the center, as the algorithm won't work for n==1
        $newNodes[0][0] = $centerPoint[0];
        $newNodes[0][1] = $centerPoint[1];
        $totalProcessed++;

        while ($totalProcessed < $totalNumberOfNodes) {
            $numberThatFits = round(M_PI / asin($radius / $curDistanceAwayFromCenter));

            $numberToFit = $numberThatFits;
            if ($numberToFit > $totalNumberOfNodes - $totalProcessed) {
                $numberToFit = $totalNumberOfNodes - $totalProcessed;
            }

            for ($j = 0; $j < $numberToFit; $j++) {
                // ang to center
                $ang = M_PI * 2 * $j / $numberToFit - M_PI;
                $node = [];
                $node[0] = $centerPoint[0] + cos($ang) * $curDistanceAwayFromCenter;
                $node[1] = $centerPoint[1] + sin($ang) * $curDistanceAwayFromCenter;

                $newNodes[] = $node;
                $totalProcessed++;
            }

            $curDistanceAwayFromCenter += $radius * 2;
        }

        return $newNodes;
    }

    /** @noinspection PhpUnused */
    public static function arrangeRect($radius, $centerPoint, $totalNumberOfNodes): array
    {
        $newNodes = [];
        $totalProcessed = 0;

        $grid = [];
        $width = ceil(sqrt($totalNumberOfNodes));
        $height = round(sqrt($totalNumberOfNodes));
        $middleTop = (int) floor($height / 2) - 1;
        $middleBottom = (int) ceil($height / 2) - 1;

        if ($middleTop !== $middleBottom) {
            for ($i = 0; $i < $width && $totalProcessed < $totalNumberOfNodes; $i++) {
                $grid[$middleBottom][$i] = 1;
                $totalProcessed++;
            }
        }

        for ($i = 0; $i < $width && $totalProcessed < $totalNumberOfNodes; $i++) {
            $grid[$middleTop][$i] = 1;
            $totalProcessed++;
        }

        for ($j = $middleBottom + 1, $jj = $middleTop - 1; $j < $height; $j++, $jj--) {
            for ($i = 0; $i < $width && $totalProcessed < $totalNumberOfNodes; $i++) {
                if ($jj >= 0) {
                    $grid[$jj][$i] = 1;
                    $totalProcessed++;
                }

                if ($totalProcessed < $totalNumberOfNodes) {
                    $grid[$j][$i] = 1;
                    $totalProcessed++;
                }
            }
        }

        ksort($grid);

        $heightPx = $height * $radius * 2;
        foreach ($grid as $k => $kValue) {
            $itemsInRow = count($kValue);

            $rowWidthPx = $itemsInRow * ($radius * 2);

            for ($l = 0; $l < $itemsInRow; $l++) {
                $newNodes[] = [
                    $centerPoint[0] - $rowWidthPx / 2 + $radius + $l * ($radius * 2),
                    $centerPoint[1] - $heightPx / 2 + $radius + $k * ($radius * 2),
                ];
            }
        }

        return $newNodes;
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $clusters = [];

        /** @var array $data */
        $data = $file->content;

        foreach ($data['features'] as $i => $f) {
            if (empty($f['geometry'])) {
                continue;
            }

            try {
                $geometry = geoPHP::load(json_encode($f['geometry']), 'json');
            } catch (Exception) {
                continue;
            }

            if ($geometry instanceof Point) {
                $point = $geometry->coords;
                $clusterId = json_encode($point);
                $clusters[$clusterId][] = $i;
            }
        }

        foreach ($clusters as $clusterId => $cluster) {
            $point = json_decode((string) $clusterId);
            $nodes = call_user_func(
                $this->cp->options['spreadFunction'],
                $this->cp->options['spread'],
                $point,
                count($cluster)
            );

            for ($i = 0, $iMax = count($cluster); $i < $iMax; $i++) {
                $data['features'][$cluster[$i]]['geometry']['coordinates'] = $nodes[$i];
            }
        }

        $this->pushFile($file);
    }
}
