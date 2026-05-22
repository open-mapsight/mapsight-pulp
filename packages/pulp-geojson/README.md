# Pulp GeoJSON

Extension for the Pulp library to handle GeoJSON data, providing tools for transformation, filtering, and conversion.

## Features

- **Format Conversion:**
  - Convert KML and KMZ files to GeoJSON.
  - Export GeoJSON data to CSV.
- **Geometry Manipulation:**
  - **Centroids:** Simplify complex geometries to single points.
  - **Splitting:** Break down multi-geometries (MultiPolygon, MultiLineString, etc.) into individual features.
- **Filtering:** Filter features based on property values or geometry types.
- **Coordinate Re-projection:** Change the coordinate system of your GeoJSON data.

## Requirements

* PHP `zip` extension (optional): needed for extracting KMZ files through the `PulpGeoJson::fromKml` processor.
