<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use GeoJSON;
use geoPHP;
use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Throwable;

class FromCsvHandler extends AbstractHandler
{
    protected static function mapGeometry($wktString)
    {
        $result = null;

        try {
            $geometry = geoPHP::load($wktString, 'wkt');
            $adapter = new GeoJSON();
            $result = $adapter->write($geometry, true);
        } catch (Throwable) {

        }

        return $result;
    }

    public static function defaultRowToFeatureMapper($itemsRow, array $columns): ?array
    {
        $feature = ['type' => 'Feature'];
        $crs = null;

        $hasContent = false; // we check if the row has some meaningful content to fix empty rows creating invalid
        // features which are not easily fixable by the creator.
        //
        // but we allow for some columns to be empty, which may lead to invalid features anyways,
        // but those ARE under the control of the creator

        foreach ($itemsRow as $i => $value) {
            $key = $columns[$i];

            switch ($key) {
                case ToCsvHandler::$DEFAULT_COLUMN_NAME_CRS:
                    $crs = @json_decode((string) $value, true) ?: null;
                    break;
                case ToCsvHandler::$DEFAULT_COLUMN_NAME_GEOMETRY:
                    $geometry = self::mapGeometry($value);
                    if ($geometry !== null) {
                        $hasContent = true;
                        $feature['geometry'] = $geometry;
                    }
                    break;
                case ToCsvHandler::$DEFAULT_COLUMN_NAME_ID:
                    if (!empty($value)) {
                        $hasContent = true;
                        $feature['id'] = $value;
                    }
                    break;
                default:
                    if (!empty($value)) {
                        $hasContent = true;
                        $whenPrefixLength = strlen(ToCsvHandler::$DEFAULT_COLUMN_NAME_PREFIX_WHEN);
                        $whenPrefix = substr((string) $key, 0, $whenPrefixLength);
                        if ($whenPrefix === ToCsvHandler::$DEFAULT_COLUMN_NAME_PREFIX_WHEN) {
                            if (!isset($feature['when'])) {
                                $feature['when'] = [];
                            }

                            $feature['when'][substr((string) $key, $whenPrefixLength)] = $value;
                        } else {

                            if (!isset($feature['properties'])) {
                                $feature['properties'] = [];
                            }
                            $feature['properties'][$key] = $value;
                        }
                    }
            }
        }

        if ($crs !== null && isset($crs['name'])) {
            GeoJsonReproject::reproject($feature, 'EPSG:4326', ['srcProj' => $crs['name']]);
        }

        return $hasContent ? $feature : null;
    }


    protected function getConstructorParamDefs(): array
    {
        return ['fieldSeparator', 'lineSeparator', 'quoteChar', 'rowToFeatureMapper'];
    }

    public function onFile(File $file): void
    {
        $csv = $file->content;
        $csv = mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1'); // TODO: make conversion optional

        $rows = explode($this->cp->lineSeparator, $csv);
        $rows = array_map(function ($row): array {
            $items = str_getcsv($row, $this->cp->fieldSeparator, $this->cp->quoteChar);
            return array_map(stripslashes(...), $items);
        }, $rows);

        $columnCounter = 0;
        $columns = array_map(static function ($columnName) use ($columnCounter): string {
            $columnCounter++;
            return $columnName === '' || $columnName === '0' ? '_' . $columnCounter : $columnName;
        }, array_shift($rows));

        $rowToFeatureMapper = $this->cp->rowToFeatureMapper ?? self::defaultRowToFeatureMapper(...);

        $features = array_map(fn($row) => $rowToFeatureMapper($row, $columns), $rows, $columns);

        // using array_values to fix holes in array, see https://stackoverflow.com/a/2653022
        $features = array_values(array_filter($features, static fn($feature): bool => !empty($feature)));

        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        $file->content = $featureCollection;
        $this->pushFile($file);
    }
}
