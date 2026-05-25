<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpgeojson\BuildInfoHandler;
use OpenMapsight\pulpgeojson\CalculateBBoxesHandler;
use OpenMapsight\pulpgeojson\CalculateCentroidsHandler;
use OpenMapsight\pulpgeojson\FilterDuplicateIdsHandler;
use OpenMapsight\pulpgeojson\FilterGeometryHandler;
use OpenMapsight\pulpgeojson\FilterHandler;
use OpenMapsight\pulpgeojson\FromArcGisJsonHandler;
use OpenMapsight\pulpgeojson\FromCsvHandler;
use OpenMapsight\pulpgeojson\FromGeoRssHandler;
use OpenMapsight\pulpgeojson\FromKmlHandler;
use OpenMapsight\pulpgeojson\MapHandler;
use OpenMapsight\pulpgeojson\MergeHandler;
use OpenMapsight\pulpgeojson\ReprojectHandler;
use OpenMapsight\pulpgeojson\SetIdsHandler;
use OpenMapsight\pulpgeojson\SimplifyGeometriesToSinglePointHandler;
use OpenMapsight\pulpgeojson\SliceHandler;
use OpenMapsight\pulpgeojson\SortHandler;
use OpenMapsight\pulpgeojson\SplitHandler;
use OpenMapsight\pulpgeojson\SpreadPointClustersHandler;
use OpenMapsight\pulpgeojson\StatisticsHandler;
use OpenMapsight\pulpgeojson\ToArcGisJsonHandler;
use OpenMapsight\pulpgeojson\ToCsvHandler;
use OpenMapsight\pulpgeojson\ToRssHandler;
use OpenMapsight\pulpgeojson\ValidateGeometries;

class PulpGeoJSON
{
    public static function reproject(string $destProjection = 'EPSG:4326', ?array $options = null): ReprojectHandler
    {
        return new ReprojectHandler($destProjection, $options);
    }

    public static function merge(string $fileName, array $options = []): MergeHandler
    {
        return new MergeHandler($fileName, $options);
    }

    public static function addBuildInfo(): BuildInfoHandler
    {
        return new BuildInfoHandler();
    }

    public static function addStatistics(string|array|null $name = null): StatisticsHandler
    {
        return new StatisticsHandler($name);
    }

    public static function calculateBBoxes(): CalculateBBoxesHandler
    {
        return new CalculateBBoxesHandler();
    }

    public static function calculateCentroids(): CalculateCentroidsHandler
    {
        return new CalculateCentroidsHandler();
    }

    /**
     * ## Options
     * * `skipExceptions`: If an exception is thrown in the handler, the corresponding feature is
     *   replaced with an "error feature". (default: `false`)
     *
     * @param {Fn($feature: Feature) -> boolean} [$handler]
     * @param {{
     *   skipExceptions?: boolean,
     * }} [$options]
     */
    public static function filter(?callable $handler = null, array $options = []): FilterHandler
    {
        return new FilterHandler($handler, $options);
    }

    /**
     * ## Options
     * * `skipExceptions`: If an exception is thrown in the handler, the corresponding geometry is
     *   removed and the exception is printed to stdout. (default: `false`)
     *
     * @param {Fn($feature: Feature) -> bool} [$handler]
     * @param {{
     *   skipExceptions?: boolean,
     * }} [$options]
     */
    public static function filterGeometry(
        ?callable $handler = null,
        array $options = []
    ): FilterGeometryHandler {
        return new FilterGeometryHandler($handler, $options);
    }

    public static function simplifyGeometriesToSinglePoint(): SimplifyGeometriesToSinglePointHandler
    {
        return new SimplifyGeometriesToSinglePointHandler();
    }

    /**
     * ## Options
     * * `skipExceptions`: If an exception is thrown in the handler, the corresponding feature is
     *   replaced with an "error feature". (default: `false`)
     *
     * @param {Fn($feature: Feature) -> Feature} [$handler]
     * @param {{
     *   skipExceptions?: boolean,
     * }} [$options]
     */
    public static function map(?callable $handler = null, array $options = []): MapHandler
    {
        return new MapHandler($handler, $options);
    }

    public static function sort(callable $callback): SortHandler
    {
        return new SortHandler($callback);
    }

    public static function slice($start = null, $length = null): SliceHandler
    {
        return new SliceHandler($start, $length);
    }

    public static function split(callable $callback): SplitHandler
    {
        return new SplitHandler($callback);
    }

    public static function spreadPointClusters(array $options = []): SpreadPointClustersHandler
    {
        return new SpreadPointClustersHandler($options);
    }

    /**
     * ## Options
     * * `skipEmptyInput`: Ignores and logs on empty input file.
     *   (default: `false`)
     * * `logSkipEmptyInput`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *   * `"feature"`: emit a virtual file containing one error feature.
     * * `skipExceptions`: Skips and logs Exceptions when processing geo rss files. No virtual
     *   file is emitted. (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *   * `"feature"`: emit a virtual file containing one error feature.
     * * `skipItemExceptions`: Skips Exceptions when processing rss items. Skips the whole item
     *   and prints the exception to stdout. (default: `false`)
     * * `logSkipItemExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *   * `"feature"`: emit a virtual file containing one error feature.
     * * `skipGeometryExceptions`: Skips Exceptions when processing geometries. Sets geometry to
     *   `null` and prints the exception to stdout. (default: `false`)
     * * `logSkipGeometryExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     * * `workaroundUnclosed`: Closes unclosed polygons and linestrings by copying the first
     *    coordinate to the end of the coordinate list. (default: `false`)
     * * `logWorkaroundUnclosed`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *
     * @param {Fn($item: \SimpleXMLElement, $feature: Feature) -> Feature} [$handler]
     * @param {{
     *   skipGeometryExceptions?: boolean,
     *   logSkipGeometryExceptions?: false | "stdout" | "stderr",
     *   skipItemExceptions?: boolean,
     *   logSkipItemExceptions?: false | "stdout" | "stderr" | "feature",
     *   workaroundUnclosed?: boolean,
     *   logWorkaroundUnclosed?: false | "stdout" | "stderr",
     * }} [$options]
     */
    public static function fromGeoRss(
        ?callable $handler = null,
        array $options = []
    ): FromGeoRssHandler {
        return new FromGeoRssHandler($handler, $options);
    }

    public static function toRss(
        string $featureToItemsMapper = ToRssHandler::class . '::defaultFeatureToItemMapper',
        string $channelMapper = ToRssHandler::class . '::defaultChannelMapper'
    ): ToRssHandler {
        return new ToRssHandler($featureToItemsMapper, $channelMapper);
    }

    public static function fromCsv(
        string $fieldSeparator = ';',
        string $lineSeparator = "\n",
        string $quoteChar = '"',
        string $rowToFeatureMapper = FromCsvHandler::class . '::defaultRowToFeatureMapper'
    ): FromCsvHandler {
        return new FromCsvHandler($fieldSeparator, $lineSeparator, $quoteChar, $rowToFeatureMapper);
    }

    public static function toCsv(
        string $fieldSeparator = ';',
        string $lineSeparator = "\n",
        string $quoteChar = '"',
        string $featureToRowMapper = ToCsvHandler::class . '::defaultFeatureToRowMapper'
    ): ToCsvHandler {
        return new ToCsvHandler($fieldSeparator, $lineSeparator, $quoteChar, $featureToRowMapper);
    }

    public static function fromKml(): FromKmlHandler
    {
        return new FromKmlHandler();
    }

    public static function setIds(): SetIdsHandler
    {
        return new SetIdsHandler();
    }

    public static function fromArcGisJson(
        string $idAttribute = null
    ): FromArcGisJsonHandler {
        return new FromArcGisJsonHandler($idAttribute);
    }

    public static function toArcGisJson(
        string $idAttribute = 'OBJECTID'
    ): ToArcGisJsonHandler {
        return new ToArcGisJsonHandler($idAttribute);
    }

    /**
     * ## Options
     * * `skipExceptions`: Skips Exceptions when processing geometries. Sets the geometry to
     *   `null`. (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *   * `"feature"`: replaces feature with an error feature
     *
     * @param {{
     *   skipExceptions?: boolean,
     *   logSkipExceptions?: false | "stdout" | "stderr" | "feature",
     * }} [$options]
     */
    public static function validateGeometries(
        array $options = []
    ): ValidateGeometries {
        return new ValidateGeometries($options);
    }

    public static function filterDuplicateIds(): FilterDuplicateIdsHandler
    {
        return new FilterDuplicateIdsHandler();
    }
}
