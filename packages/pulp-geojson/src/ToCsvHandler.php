<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use exception;
use geoPHP;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use WKT;

class ToCsvHandler extends AbstractHandler
{
    public static string $DEFAULT_COLUMN_NAME_GEOMETRY = '_geometry';
    public static string $DEFAULT_COLUMN_NAME_ID = '_id';
    public static string $DEFAULT_COLUMN_NAME_CRS = '_crs';
    public static string $DEFAULT_COLUMN_NAME_PREFIX_WHEN = '_when_';

    /**
     * @param $geoJsonGeometry
     * @return string
     * @throws exception
     */
    protected static function mapGeometry($geoJsonGeometry): string
    {
        $geometry = geoPHP::load($geoJsonGeometry, 'json');
        return (new WKT())->write($geometry);
    }

    /**
     * @param $feature
     * @param $featureCollection
     * @return array
     * @throws exception
     */
    public static function defaultFeatureToRowMapper(array $feature, array $featureCollection): array
    {
        $featureCollectionCrs = null;
        if ($featureCollection['crs']) {
            $items[self::$DEFAULT_COLUMN_NAME_CRS] = json_encode($featureCollection['crs']);
        }

        $items = [];

        if (isset($feature['id'])) {
            $items[self::$DEFAULT_COLUMN_NAME_ID] = $feature['id'];
        }

        if (isset($feature['geometry'])) {
            $items[self::$DEFAULT_COLUMN_NAME_GEOMETRY] = self::mapGeometry(json_encode($feature['geometry']));
        }

        if (isset($feature['properties'])) {
            foreach ($feature['properties'] as $key => $property) {
                $items[$key] = (is_string($property) || is_numeric($property)) ? $property : json_encode($property);
            }
        }

        if (isset($feature['when'])) {
            foreach ($feature['when'] as $key => $property) {
                $items[self::$DEFAULT_COLUMN_NAME_PREFIX_WHEN . $key] = json_encode($property);
            }
        }

        return $items;
    }

    public static function itemRowsToCsv($itemRows, $fieldSeparator = ';', string $lineSeparator = "\n", $quoteChar = '"', $ansiCoded = true): string
    {
        // Collect columns
        $columns = [];
        foreach ($itemRows as $itemRow) {
            foreach (array_keys($itemRow) as $key) {
                if (!in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
        }
        usort($columns, static function ($a, $b): int {
            $aIsUnderscore = str_starts_with($a, '_');
            $bIsUnderscore = str_starts_with($b, '_');

            if ($aIsUnderscore && !$bIsUnderscore) {
                return 1;
            }

            if ($bIsUnderscore) {
                return -1;
            }

            return strnatcmp($a, $b);
        });

        $firstRow = $columns;
        $rows = [$firstRow];
        foreach ($itemRows as $itemRow) {
            $row = [];

            foreach ($columns as $key) {
                $row[] = $itemRow[$key] ?? '';
            }

            $rows[] = $row;
        }

        $resultString = '';
        foreach ($rows as $row) {
            $resultString .= implode($fieldSeparator, array_map(static fn($field): string => $quoteChar . addcslashes(addcslashes((string) $field, (string) $quoteChar), (string) $fieldSeparator) . $quoteChar, $row)) . $lineSeparator;
        }

        if ($ansiCoded) {
            return mb_convert_encoding($resultString, 'ISO-8859-1');
        }

        return $resultString;
    }

    protected function getConstructorParamDefs(): array
    {
        return ['fieldSeparator', 'lineSeparator', 'quoteChar', 'featureToRowMapper'];
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

        $featureToRowMapper = $this->cp->featureToRowMapper ?? self::defaultFeatureToRowMapper(...);

        $itemRows = [];
        $featureCollection = $data['features'];
        foreach ($featureCollection as $feature) {
            $row = $featureToRowMapper($feature, $featureCollection);

            if ($row !== false && $row !== null) {
                $itemRows[] = $row;
            }
        }

        $file->content = self::itemRowsToCsv($itemRows, $this->cp->fieldSeparator, $this->cp->lineSeparator, $this->cp->quoteChar);
        $this->pushFile($file);
    }
}
