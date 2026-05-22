<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use DateTime;
use exception;
use geoPHP;
use GeoRSS;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class ToRssHandler extends AbstractHandler
{
    /**
     * @param $geoJsonGeometry
     * @return string
     * @throws exception
     */
    protected static function mapGeometry($geoJsonGeometry): string
    {
        $geometry = geoPHP::load($geoJsonGeometry, 'json');
        return (new GeoRSS())->write($geometry, 'georss');
    }

    /**
     * @param $feature
     * @return string
     * @throws exception
     */
    public static function defaultFeatureToItemMapper(array $feature): string
    {
        $dateTimeString = (new DateTime())->format(DateTime::RSS);
        $id = $feature['properties']['id'];
        $title = strip_tags((string) $feature['properties']['name']);
        $description = $feature['properties']['description'] ?? '';
        $geometryString = isset($feature['geometry']) ? self::mapGeometry(json_encode($feature['geometry'])) : '';

        return <<<XML
            		<item>
            			<guid isPermaLink="false">$id</guid>
            			<pubDate>$dateTimeString</pubDate>
            			<title>$title</title>
            			<description><![CDATA[ $description ]]></description>
            			<content:encoded><![CDATA[ $description ]]></content:encoded>
            			$geometryString
            		</item>
            XML;
    }

    public static function defaultChannelMapper($file, $items): string
    {
        $itemsString = implode("\n\r", $items);
        $dateTimeString = (new DateTime())->format(DateTime::RSS);

        return <<<XML
            	<channel>
            		<pubDate>$dateTimeString</pubDate>
            		<lastBuildDate>$dateTimeString</lastBuildDate>

            		$itemsString
            	</channel>
            XML;
    }

    protected function getConstructorParamDefs(): array
    {
        return ['featureToItemsMapper', 'channelMapper'];
    }

    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        /** @var array $data */
        $data = $file->content;

        $featureToItemMapper = $this->cp->featureToItemsMapper ?? self::defaultFeatureToItemMapper(...);
        $channelMapper = $this->cp->channelMapper ?? self::defaultChannelMapper(...);

        $items = [];
        foreach ($data['features'] as $feature) {
            $item = $featureToItemMapper($feature);

            if ($item !== false && $item !== null) {
                $items[] = $item;
            }
        }

        $channelString = $channelMapper($file, $items);

        $xml = /** @lang XML */
            <<<XML
                	<rss
                		version="2.0"
                		xmlns:content="http://purl.org/rss/1.0/modules/content/"
                		xmlns:atom="http://www.w3.org/2005/Atom"
                		xmlns:georss="http://www.georss.org/georss"
                		xmlns:gml="http://www.opengis.net/gml"
                	>
                		$channelString
                	</rss>
                XML;

        $file->content = '<?xml version="1.0" encoding="utf-8"?>' . "\n\r" . $xml;
        $this->pushFile($file);
    }
}
