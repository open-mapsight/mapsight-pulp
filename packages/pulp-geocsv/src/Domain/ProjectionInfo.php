<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeocsv\Domain;

class ProjectionInfo
{
    public const TYPE_X_Y = 'x,y';
    public const TYPE_XY = 'xy';
    public const TYPE_WKT = 'wkt';

    protected string $columnType;
    protected ?string $columnNameX = null;
    protected ?string $columnNameY = null;
    protected ?string $columnNameXY = null;
    protected ?string $columnNameWKT = null;
    protected ?string $coordinateSeparator = ',';
    protected string $projection;
    protected string $decimalSeparator;

    public static function build(
        string  $columnType,
        ?string $columnNameX,
        ?string $columnNameY,
        ?string $columnNameXY,
        ?string $columnNameWKT,
        string  $projection,
        string  $decimalSeparator,
        ?string $coordinateSeparator = null
    ): self {
        $instance = new self();
        $instance->setColumnType($columnType);
        $instance->setColumnNameX($columnNameX);
        $instance->setColumnNameY($columnNameY);
        $instance->setColumnNameXY($columnNameXY);
        $instance->setColumnNameWKT($columnNameWKT);
        $instance->setProjection($projection);
        $instance->setDecimalSeparator($decimalSeparator);
        $instance->setCoordinateSeparator($coordinateSeparator);

        return $instance;
    }

    public function getDecimalSeparator(): string
    {
        return $this->decimalSeparator;
    }

    public function setDecimalSeparator(string $decimalSeparator): void
    {
        $this->decimalSeparator = $decimalSeparator;
    }

    public function getColumnType(): string
    {
        return $this->columnType;
    }

    public function setColumnType(string $columnType): void
    {
        $this->columnType = $columnType;
    }

    public function getColumnNameX(): ?string
    {
        return $this->columnNameX;
    }

    public function setColumnNameX(?string $columnNameX): void
    {
        $this->columnNameX = $columnNameX;
    }

    public function getColumnNameY(): ?string
    {
        return $this->columnNameY;
    }

    public function setColumnNameY(?string $columnNameY): void
    {
        $this->columnNameY = $columnNameY;
    }

    public function getColumnNameXY(): ?string
    {
        return $this->columnNameXY;
    }

    public function setColumnNameXY(?string $columnNameXY): void
    {
        $this->columnNameXY = $columnNameXY;
    }

    public function getColumnNameWKT(): ?string
    {
        return $this->columnNameWKT;
    }

    public function setColumnNameWKT(?string $columnNameWKT): void
    {
        $this->columnNameWKT = $columnNameWKT;
    }

    public function getProjection(): string
    {
        return $this->projection;
    }

    public function setProjection(string $projection): void
    {
        $this->projection = $projection;
    }

    public function getCoordinateSeparator(): string
    {
        return $this->coordinateSeparator;
    }

    public function setCoordinateSeparator(string $coordinateSeparator): void
    {
        $this->coordinateSeparator = $coordinateSeparator;
    }
}
