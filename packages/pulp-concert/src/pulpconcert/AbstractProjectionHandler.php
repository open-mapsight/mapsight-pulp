<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert;

use OpenMapsight\pulp\AbstractHandler;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

abstract class AbstractProjectionHandler extends AbstractHandler
{
    /** @var Proj4php $proj4 */
    protected $proj4;

    /** @var Proj $proj4 */
    protected $projectionWgs84UtmZone32N;

    /** @var Proj $proj4 */
    protected $projectionWgs84;

    public function onStart(): void
    {
        $this->proj4 = new Proj4php();
        $this->projectionWgs84UtmZone32N = new Proj('+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs', $this->proj4);
        $this->projectionWgs84 = new Proj('+proj=longlat +datum=WGS84 +no_defs', $this->proj4);
    }

    /**
     * @param $coordinatePair array with x and y
     *
     * @return float[]
     */
    public function transformPoint(array $coordinatePair): array
    {
        $point = $this->proj4->transform(
            $this->projectionWgs84UtmZone32N,
            $this->projectionWgs84,
            new Point($coordinatePair['x'], $coordinatePair['y'], $this->projectionWgs84UtmZone32N)
        );

        return [$point->x, $point->y];
    }
}
