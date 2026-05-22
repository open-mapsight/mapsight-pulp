<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeocsv;

use Exception;
use GeoJSON;
use geoPHP;
use OpenMapsight\GeoJsonReproject;
use OpenMapsight\Pulp\AbstractHandler;
use OpenMapsight\Pulp\File;
use OpenMapsight\pulpgeocsv\Domain\ProjectionInfo;
use OpenMapsight\pulpgeocsv\Domain\ReprojectMissingColumnException;
use WKT;

class ReprojectHandler extends AbstractHandler
{
    public const ERROR_PROJECTION_RETURNED_NULL = 'Projektion fehlgeschlagen';
    public const ERROR_UNSUPPORTED_GEOMETRY_TYPE_FOR_OUTPUT_COLUMN_TYPE = 'Geometrie konnte nicht auf Zielspalten übertragen werden, weil sie kein Punkt ist (etwa Polygonzug oder -fläche).';
    public const KNOWN_NUMBER_SEPARATORS = [',', '.', '_'];
    public const COLUMN_NAME_ERRORS = 'errors';

    public function onFile(File $file): void
    {
        // read
        $hasErrors = false;
        $allErrors = [];
        $inputGeometries = [];
        foreach ($file->content['rows'] as $rowIndex => &$row) {
            $errors = [];
            $inputGeometries[$rowIndex] = $this->readInputGeometry($file->content['columns'], $row);
            $allErrors[$rowIndex] = array_merge($allErrors[$rowIndex] ?? [], $errors);
            if (count($errors) > 0) {
                $hasErrors = true;
            }
        }
        unset($row);

        // now we can add columns
        $this->addMissingColumnsIfNeeded($file->content['columns']);

        // reproject and write
        foreach ($file->content['rows'] as $rowIndex => &$row) {
            $errors = [];
            $reprojectedGeometry = $this->reproject($inputGeometries[$rowIndex], $errors);
            $this->writeReprojectedGeometry($reprojectedGeometry, $errors, $row, $file->content['columns']);
            $allErrors[$rowIndex] = array_merge($allErrors[$rowIndex] ?? [], $errors);
            if (count($errors) > 0) {
                $hasErrors = true;
            }
        }
        unset($row);

        if ($hasErrors) {
            $errorsColumnIndex = $this->getOrCreateColumn($file->content['columns'], self::COLUMN_NAME_ERRORS);
            foreach ($file->content['rows'] as $rowIndex => &$row) {
                $row[$errorsColumnIndex] = implode(',', $allErrors[$rowIndex]);
            }
            unset($row);
        }

        $this->pushFile($file);
    }

    protected function addMissingColumnsIfNeeded(array &$columns): void
    {
        /** @var ProjectionInfo $output */
        $output = $this->cp->output;

        switch ($output->getColumnType()) {
            case ProjectionInfo::TYPE_X_Y:
                $this->getOrCreateColumn($columns, $output->getColumnNameX());
                $this->getOrCreateColumn($columns, $output->getColumnNameY());
                break;
            case ProjectionInfo::TYPE_XY:
                $this->getOrCreateColumn($columns, $output->getColumnNameXY());
                break;
            case ProjectionInfo::TYPE_WKT:
                $this->getOrCreateColumn($columns, $output->getColumnNameWKT());
                break;
        }
    }

    protected function getOrCreateColumn(array &$columns, string $columnName): int
    {
        try {
            return $this->getColumnIndexOrThrow($columns, $columnName);
        } catch (ReprojectMissingColumnException) {
            $columns[] = $columnName;
            return count($columns) - 1;
        }
    }

    /**
     * @param array $columns
     * @param string $columName
     *
     * @return int
     * @throws Domain\ReprojectMissingColumnException
     */
    protected function getColumnIndexOrThrow(array $columns, string $columName): int
    {
        $xIndex = array_search($columName, $columns, true);
        if ($xIndex === false) {
            throw new ReprojectMissingColumnException('Missing column: "' . $columName . '"');
        }

        return $xIndex;
    }

    protected function readInputGeometry(array $columns, array $row)
    {
        /** @var ProjectionInfo $input */
        $input = $this->cp->input;

        $parseFloat = (fn($number): ?float => $this->parseFloat($number, $input->getDecimalSeparator()));

        switch ($input->getColumnType()) {
            case ProjectionInfo::TYPE_X_Y:
                $xIndex = $this->getColumnIndexOrThrow($columns, $input->getColumnNameX());
                $yIndex = $this->getColumnIndexOrThrow($columns, $input->getColumnNameY());
                $coords = array_map($parseFloat, [$row[$xIndex], $row[$yIndex]]);
                return ['type' => 'Point', 'coordinates' => $coords];

            case ProjectionInfo::TYPE_XY:
                $coordinateSeparator = $input->getCoordinateSeparator();
                $index = $this->getColumnIndexOrThrow($columns, $input->getColumnNameXY());
                $parts = explode($coordinateSeparator, (string) $row[$index]);
                [$x, $y] = array_chunk($parts, ceil(count($parts) / 2));
                $coords = array_map(static fn($arr): string => implode($coordinateSeparator, $arr), [$x, $y]);
                $coords = array_map($parseFloat, $coords);
                return ['type' => 'Point', 'coordinates' => $coords];

            case ProjectionInfo::TYPE_WKT:
                $index = $this->getColumnIndexOrThrow($columns, $input->getColumnNameWKT());
                $wktString = $row[$index];
                try {
                    $geometry = geoPHP::load($wktString, 'wkt');
                    if ($geometry !== null) {
                        return (new GeoJSON())->write($geometry, true);
                    }
                } catch (Exception) {
                }
                return null;

            default:
                return null;
        }
    }

    protected function reproject(array $inputGeometry, array &$errors): ?array
    {
        /** @var ProjectionInfo $input */
        $input = $this->cp->input;

        /** @var ProjectionInfo $output */
        $output = $this->cp->output;

        $reprojectedGeometry = null;
        try {
            $reprojectedGeometry = GeoJsonReproject::reproject(
                $inputGeometry,
                $output->getProjection(),
                ['srcProj' => $input->getProjection()]
            );
        } catch (Exception $exception) {
            $errorMessage = str_replace('geocent:', '', $exception->getMessage());
            $errorMessage = preg_replace('/lat\b/', 'Breitengrad', $errorMessage);
            $errorMessage = preg_replace('/lon\b/', 'Längengrad', (string) $errorMessage);
            $errorMessage = str_replace('out of range', 'außerhalb des erlaubten Wertebreichs', $errorMessage);
            $errors[] = 'Projizierungsfehler: ' . str_replace('geocent:', '', $errorMessage);
        }

        return $reprojectedGeometry;
    }

    protected function writeReprojectedGeometry(
        ?array $reprojectedGeometry,
        array &$errors,
        array &$row,
        array $columns
    ): void {
        /** @var ProjectionInfo $output */
        $output = $this->cp->output;

        switch ($output->getColumnType()) {
            case ProjectionInfo::TYPE_X_Y:
                $x = '';
                $y = '';

                if ($reprojectedGeometry === null) {
                    $errors[] = self::ERROR_PROJECTION_RETURNED_NULL;
                } elseif ($reprojectedGeometry['type'] !== 'Point') {
                    $errors[] = self::ERROR_UNSUPPORTED_GEOMETRY_TYPE_FOR_OUTPUT_COLUMN_TYPE;
                } else {
                    $coords = $reprojectedGeometry['coordinates'];
                    $x = str_replace('.', $output->getDecimalSeparator(), (string) $coords[0]);
                    $y = str_replace('.', $output->getDecimalSeparator(), (string) $coords[1]);
                }

                $outputXColumnIndex = $this->getColumnIndexOrThrow($columns, $output->getColumnNameX());
                $outputYColumnIndex = $this->getColumnIndexOrThrow($columns, $output->getColumnNameY());
                $row[$outputXColumnIndex] = $x;
                $row[$outputYColumnIndex] = $y;
                break;

            case ProjectionInfo::TYPE_XY:
                $xy = '';

                if ($reprojectedGeometry === null) {
                    $errors[] = self::ERROR_PROJECTION_RETURNED_NULL;
                } elseif ($reprojectedGeometry['type'] !== 'Point') {
                    $errors[] = self::ERROR_UNSUPPORTED_GEOMETRY_TYPE_FOR_OUTPUT_COLUMN_TYPE;
                } else {
                    $coords = $reprojectedGeometry['coordinates'];
                    $x = str_replace('.', $output->getDecimalSeparator(), (string) $coords[0]);
                    $y = str_replace('.', $output->getDecimalSeparator(), (string) $coords[1]);
                    $xy = implode($output->getCoordinateSeparator(), [$x, $y]);
                }

                $outputXYColumnIndex = $this->getColumnIndexOrThrow($columns, $output->getColumnNameXY());
                $row[$outputXYColumnIndex] = $xy;
                break;

            case ProjectionInfo::TYPE_WKT:
                $outputWKTColumnIndex = $this->getColumnIndexOrThrow($columns, $output->getColumnNameWKT());
                $wktString = '';
                try {
                    $adapter = new WKT();
                    $wktString = $adapter->write(geoPHP::load(json_encode($reprojectedGeometry), 'geojson'));
                } catch (Exception) {
                }
                $row[$outputWKTColumnIndex] = $wktString;
                break;
        }
    }

    protected function getConstructorParamDefs(): array
    {
        return ['input', 'output'];
    }

    protected function parseFloat($value, string $decimalSeparator = '.'): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        // remove other known separators
        $knownSeparators = self::KNOWN_NUMBER_SEPARATORS;
        foreach ($knownSeparators as $knownSeparator) {
            if ($knownSeparator !== $decimalSeparator) {
                $value = str_replace($knownSeparator, '', $value);
            }
        }

        if ($decimalSeparator !== '.') {
            // normalize last decimal separator
            $value = preg_replace('/' . preg_quote($decimalSeparator, '/') . '(\d*?)$/', '.$1', $value);
            // remove others
            $value = preg_replace('/' . preg_quote($decimalSeparator, '/') . '/', '', (string) $value);
        }

        return (float) $value;
    }
}
