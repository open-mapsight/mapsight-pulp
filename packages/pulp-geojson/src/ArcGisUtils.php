<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use RuntimeException;

final class ArcGisUtils
{
    /**
     * checks if two points are equal
     *
     * @param array<number>|null $a
     * @param array<number>|null $b
     * @return bool
     */
    public static function pointsEqual(array $a, array $b): bool
    {
        for ($i = 0, $iMax = count($a); $i < $iMax; $i++) {
            if ($a[$i] !== $b[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * checks if the first and last points of a ring are equal and closes the ring
     *
     * @param array<array<number>> $coordinates ring coordinates
     * @return array<array<number>> closed ring coordinates
     */
    public static function closeRing(array $coordinates): array
    {
        if (!self::pointsEqual($coordinates[0], $coordinates[count($coordinates) - 1])) {
            $coordinates[] = $coordinates[0];
        }

        return $coordinates;
    }

    /**
     * determine if polygon ring coordinates are clockwise. clockwise signifies outer ring, counter-clockwise an
     * inner ring or hole. this logic was found
     * at http://stackoverflow.com/questions/1165647/how-to-determine-if-a-list-of-polygon-points-are-in-clockwise-order
     *
     * @param array<array<number>> $coordinates ring coordinates to test
     * @return bool true if clockwise
     */
    public static function ringIsClockwise(array $coordinates): bool
    {
        $total = 0;
        $rLength = count($coordinates);
        $point1 = $coordinates[0];
        for ($i = 0; $i < $rLength - 1; $i++) {
            $point2 = $coordinates[$i + 1];
            $total += ($point2[0] - $point1[0]) * ($point2[1] + $point1[1]);
            $point1 = $point2;
        }
        return $total >= 0;
    }

    /**
     * ported from terraformer.js https://github.com/Esri/Terraformer/blob/master/terraformer.js#L504-L519
     *
     * @param array<number> $a1
     * @param array<number> $a2
     * @param array<number> $b1
     * @param array<number> $b2
     * @return bool
     */
    public static function vertexIntersectsVertex(array $a1, array $a2, array $b1, array $b2): bool
    {
        $uaT = ($b2[0] - $b1[0]) * ($a1[1] - $b1[1]) - ($b2[1] - $b1[1]) * ($a1[0] - $b1[0]);
        $ubT = ($a2[0] - $a1[0]) * ($a1[1] - $b1[1]) - ($a2[1] - $a1[1]) * ($a1[0] - $b1[0]);
        $uB = ($b2[1] - $b1[1]) * ($a2[0] - $a1[0]) - ($b2[0] - $b1[0]) * ($a2[1] - $a1[1]);
        if ($uB !== 0) {
            $ua = $uaT / $uB;
            $ub = $ubT / $uB;
            if ($ua >= 0 && $ua <= 1 && $ub >= 0 && $ub <= 1) {
                return true;
            }
        }

        return false;
    }

    public static function arrayIntersectsArray(array $a = null, array $b = null): bool
    {
        for ($i = 0; $i < count($a) - 1; $i++) {
            for ($j = 0; $j < count($b) - 1; $j++) {
                if (self::vertexIntersectsVertex($a[$i], $a[$i + 1], $b[$j], $b[$j + 1])) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function coordinatesContainPoint(array $coordinates, array $point): bool
    {
        $contains = false;
        $l = count($coordinates);
        $j = $l - 1;
        [$x, $y] = $point;

        for ($i = 0; $i < $l; $j = $i, $i++) {
            [$iX, $iY] = $coordinates[$i];
            [$jX, $jY] = $coordinates[$j];

            if (($iY > $y || $y >= $jY) && ($jY > $y || $y >= $iY)) {
                continue;
            }

            $a = $iX + (($jX - $iX) * ($y - $iY) / ($jY - $iY));
            if ($x < $a) {
                $contains = !$contains;
            }
        }

        return $contains;
    }

    public static function coordinatesContainCoordinates(array $outer, array $inner): bool
    {
        $intersects = self::arrayIntersectsArray($outer, $inner);
        $contains = self::coordinatesContainPoint($outer, $inner[0]);
        return !$intersects && $contains;
    }

    public static function convertRingsToGeoJSON(array $rings): array
    {
        $outerRings = [];
        $holes = [];

        // for each ring
        foreach ($rings as $ring) {
            $ring = self::closeRing($ring);
            if (count($ring) < 4) {
                continue;
            }
            // is this ring an outer ring? is it clockwise?
            if (self::ringIsClockwise($ring)) {
                $polygon = [array_reverse($ring)]; // wind outer rings counterclockwise for RFC 7946 compliance
                $outerRings[] = $polygon; // push to outer rings
            } else {
                $holes[] = array_reverse($ring); // wind inner rings clockwise for RFC 7946 compliance
            }
        }

        $unContainedHoles = [];

        // while there are holes left...
        while (count($holes)) {
            // pop a hole off out stack
            $hole = array_pop($holes);

            // loop over all outer rings and see if they contain our hole.
            $contained = false;
            for ($x = count($outerRings) - 1; $x >= 0; $x--) {
                $outerRing = $outerRings[$x][0];
                if (self::coordinatesContainCoordinates($outerRing, $hole)) {
                    // the hole is contained push it into our polygon
                    $outerRings[$x][] = $hole;
                    $contained = true;
                    break;
                }
            }

            // ring is not contained in any outer ring
            // sometimes this happens https://github.com/Esri/esri-leaflet/issues/320
            if (!$contained) {
                $unContainedHoles[] = $hole;
            }
        }

        // if we couldn't match any holes using contains we can try intersects...
        while (count($unContainedHoles)) {
            // pop a hole off out stack
            $hole = array_pop($unContainedHoles);

            // loop over all outer rings and see if any intersect our hole.
            $intersects = false;

            for ($x = count($outerRings) - 1; $x >= 0; $x--) {
                $outerRing = $outerRings[$x][0];
                if (self::arrayIntersectsArray($outerRing, $hole)) {
                    // the hole is contained push it into our polygon
                    $outerRings[$x][] = $hole;
                    $intersects = true;
                    break;
                }
            }

            if (!$intersects) {
                $outerRings[] = [array_reverse($hole)];
            }
        }

        if (count($outerRings) === 1) {
            return [
                'type' => 'Polygon',
                'coordinates' => $outerRings[0],
            ];
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => $outerRings,
        ];
    }

    /**
     * This function ensures that rings are oriented in the right directions
     * outer rings are clockwise, holes are counterclockwise
     * used for converting GeoJSON Polygons to ArcGIS Polygons
     *
     * @param array $polygon
     * @return array
     */
    public static function orientRings(array $polygon): array
    {
        $output = [];
        $outerRing = self::closeRing(array_shift($polygon));
        if (count($outerRing) >= 4) {
            if (!self::ringIsClockwise($outerRing)) {
                $outerRing = array_reverse($outerRing);
            }

            $output[] = $outerRing;

            foreach ($polygon as $iValue) {
                $hole = self::closeRing($iValue);

                if (count($hole) >= 4) {
                    if (self::ringIsClockwise($hole)) {
                        $hole = array_reverse($hole);
                    }

                    $output[] = $hole;
                }
            }
        }

        return $output;
    }

    /**
     * This function flattens holes in multipolygons to one array of polygons
     * used for converting GeoJSON Polygons to ArcGIS Polygons
     *
     * @param array $rings
     * @return array
     */
    public static function flattenMultiPolygonRings(array $rings): array
    {
        $output = [];
        foreach ($rings as $ring) {
            $polygon = self::orientRings($ring);
            for ($x = count($polygon) - 1; $x >= 0; $x--) {
                $output[] = $polygon[$x];
            }
        }
        return $output;
    }

    public static function getId(array $attributes, string $idAttribute = null): string|float|int
    {
        $keys = $idAttribute ? [$idAttribute, 'OBJECTID', 'FID'] : ['OBJECTID', 'FID'];
        foreach ($keys as $iValue) {
            $key = $iValue;
            if (isset($attributes[$key]) && (is_string($attributes[$key]) || is_numeric($attributes[$key]))) {
                return $attributes[$key];
            }
        }

        throw new RuntimeException('No valid id attribute found');
    }
}
