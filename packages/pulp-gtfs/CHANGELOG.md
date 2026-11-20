# Changelog

All notable changes to `mapsight/pulp-gtfs` are documented here.

## Unreleased

## 1.1.1 - 2026-06-26

### Changed

- License the package under MIT.

## 1.1.0 - 2026-06-20

### Added

- Add the `fallbackLineStringsFromStops` option: derive simple line strings from ordered stops for routes without usable shapes. `shapes.txt` is then optional for `lines` and `combined` output, and features record their `geometrySource` (`shapes` or `stops`).

## 1.0.1 - 2026-06-20

### Fixed

- Pass the CSV escape argument to `fgetcsv()` explicitly, avoiding the PHP 8.4 deprecation.

## 1.0.0 - 2026-06-18

### Added

- Add `PulpGTFS::geoJson()` to generate stops, lines, or combined GeoJSON from GTFS text files in a pipeline.
- Add `GtfsGeoJsonBuilder` for directory-based and reader-based GTFS conversion.
- Add bounding box filtering for stop features and representative line geometry output.
- Add route, mode, source, departure URL, and wheelchair accessibility metadata to generated features.
