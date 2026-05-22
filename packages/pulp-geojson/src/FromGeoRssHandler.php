<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use function end;

use Exception;
use GeoJSON;
use geoPHP;

use function htmlspecialchars;
use function is_callable;
use function is_string;
use function json_decode;

use LineString;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Point;
use Polygon;

use function preg_split;

use SimpleXMLElement;
use Throwable;

use function trim;

// workaround to get geophp classes loaded
geoPHP::version();

class FromGeoRssHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            'handler',
            'options',
        ];
    }

    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        $content = null;
        try {
            if (empty($file->content)) {
                $err = new Exception('GeoRss file "' . $file->fileName . '" is empty');

                if ($this->cp->options['skipEmptyInput'] ?? false === true) {
                    $log = $this->cp->options['logSkipEmptyInput'] ?? 'stderr';
                    if ($log === 'feature') {
                        $content = Utils::createErrorFileContent($err);
                    } else {
                        \OpenMapsight\pulp\Utils::log($log, $err);
                    }
                } else {
                    throw $err;
                }
            } else {
                $feed = new SimpleXMLElement($file->content);
                $features = $this->processFeedToFeatures($feed);
                $content = [
                    // the `crs` is against the GeoJson spec, but we need it for compat reasons
                    'crs' => [
                        'type' => 'name',
                        'properties' => [
                            'name' => 'EPSG:4326',
                        ],
                    ],
                    'type' => 'FeatureCollection',
                    'features' => $features,
                ];
            }
        } catch (Throwable $err) {
            $err = new Exception(
                'Processing GeoRss file "' . $file->fileName . '" failed',
                0,
                $err
            );

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                $log = $this->cp->options['logSkipExceptions'] ?? 'stderr';
                if ($log === 'feature') {
                    $content = Utils::createErrorFileContent($err);
                } else {
                    \OpenMapsight\pulp\Utils::log($log, $err);
                }
            } else {
                throw $err;
            }
        }

        if ($content !== null) {
            $file->content = $content;
            $this->pushFile($file);
        }
    }

    protected function processFeedToFeatures(SimpleXMLElement $feed): array
    {
        $features = [];

        foreach ($feed->children() as $channel) {
            if ($channel->getName() !== 'channel') {
                continue;
            }

            foreach ($channel->children() as $item) {
                if ($item->getName() !== 'item') {
                    continue;
                }

                try {
                    $features[] = $this->processItem($item);
                } catch (Throwable $err) {
                    if ($this->cp->options['skipItemExceptions'] ?? false === true) {
                        $log = $this->cp->options['logSkipItemExceptions'] ?? 'stderr';
                        if ($log === 'feature') {
                            $features[] = Utils::createErrorFeature($err);
                        } else {
                            \OpenMapsight\pulp\Utils::log($log, $err);
                        }
                    } else {
                        throw $err;
                    }
                }
            }
        }

        return $features;
    }

    protected function processItem(SimpleXMLElement $item): array
    {
        $props = [];
        foreach ($item->children() as $itemC) {
            switch ($itemC->getName()) {
                case 'guid':
                    {
                        $props['id'] = (string) $itemC;
                        break;
                    }

                case 'title':
                    {
                        $props['name'] = (string) $itemC;
                        break;
                    }

                case 'description':
                    {
                        // the rss description tag contains a plain text (no html, but
                        // possibly some html "magic chars") short description of the item,
                        // thus we need to escape all html chars to make it compatible with
                        // html.
                        $props['shortDescription'] = htmlspecialchars(
                            (string) $itemC,
                            ENT_QUOTES
                        );
                        break;
                    }

                case 'pubDate':
                    {
                        $props['pubDate'] = (string) $itemC;
                        break;
                    }

                case 'link':
                    {
                        $props['link'] = (string) $itemC;
                        break;
                    }

                case 'link-internal':
                    {
                        $props['linkInternal'] = (string) $itemC;
                        break;
                    }
            }
        }

        // get full content (html)
        foreach ($item->children('http://purl.org/rss/1.0/modules/content/') as $itemC) {
            if ($itemC->getName() === 'encoded') {
                $props['description'] = (string) $itemC;
            }
        }

        // fallback for GeoJsons "description" prop from "shortDescription"
        if (empty($props['description']) && (isset($props['shortDescription']) && ($props['shortDescription'] !== '' && $props['shortDescription'] !== '0'))) {
            $props['description'] = $props['shortDescription'];
        }

        $featGeom = null;
        $idStr = isset($props['id']) && ($props['id'] !== '' && $props['id'] !== '0') && is_string($props['id']) ? $props['id'] : 'no id';
        try {
            $featGeom = $this->processGeometry($item, $idStr);
        } catch (Throwable $err) {
            $err = new Exception(
                'Processing GeoRss geometry failed for id "' . $idStr . '"',
                0,
                $err
            );

            if ($this->cp->options['skipGeometryExceptions'] ?? false === true) {
                \OpenMapsight\pulp\Utils::log(
                    $this->cp->options['logSkipGeometryExceptions'] ?? 'stderr',
                    $err
                );
            } else {
                throw $err;
            }
        }

        $feature = [
            'type' => 'Feature',
            'geometry' => $featGeom,
            'id' => isset($props['id']) && ($props['id'] !== '' && $props['id'] !== '0') && is_string($props['id']) ? $props['id'] : null,
            'properties' => $props,
        ];

        if (is_callable($this->cp->handler)) {
            return $this->cp->handler($item, $feature);
        }

        return $feature;
    }

    protected function processGeometry(SimpleXMLElement $item, string $idStr): ?array
    {
        $featGeom = null;

        foreach ($item->children('http://www.georss.org/georss') as $geomItem) {
            if (
                $geomItem->getName() === 'line'
                || $geomItem->getName() === 'polygon'
            ) {
                $coordsStr = trim((string) $geomItem);

                // https://github.com/phayes/geoPHP/blob/685562416ec6d22b9b3927e02ca0ddacf84ca646/lib/adapters/GeoRSS.class.php#L86-L101
                $coords = [];
                if ($coordsStr === '' || $coordsStr === '0') {
                    break;
                }
                $latlon = preg_split('/\s+/', $coordsStr);
                foreach ($latlon as $key => $pointItem) {
                    if ($key % 2 === 0) {
                        // It's a latitude
                        $lat = $pointItem;
                    } else {
                        // It's a longitude
                        $lon = $pointItem;
                        $coords[] = new Point($lon, $lat);
                    }
                }

                // not checking for the minimal number of vertices (4 <=) and other invalid
                // cases here
                if (
                    $this->cp->options['workaroundUnclosed'] ?? false === true
                    && $coords !== []
                    && !$coords[0]->equals(end($coords))
                ) {
                    $coords[] = $coords[0];

                    \OpenMapsight\pulp\Utils::log(
                        $this->cp->options['logWorkaroundUnclosed'] ?? 'stderr',
                        'Workarounding unclosed linestring/polygon for "' . $idStr . '"' . "\n"
                    );
                }

                $featGeom = new LineString($coords);

                if ($geomItem->getName() === 'polygon') {
                    $featGeom = new Polygon([$featGeom]);
                }

                break;
            }

            if ($geomItem->getName() === 'point') {
                $pointStr = trim((string) $geomItem);

                if ($pointStr === '' || $pointStr === '0') {
                    break;
                }
                $latlon = preg_split('/\s+/', $pointStr);
                $featGeom = new Point($latlon[1], $latlon[0]);
                break;
            }
        }

        if ($featGeom !== null) {
            $geoJsonAdapter = new GeoJSON();
            $featGeom = json_decode(
                $geoJsonAdapter->write($featGeom),
                true
            );
        }

        return $featGeom;
    }
}
