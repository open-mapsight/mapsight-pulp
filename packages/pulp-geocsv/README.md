# Pulp GeoCSV

Extension for the Pulp library to handle CSV files with geographic data.

## Features

- **CSV to GeoJSON:** Convert standard CSV files with longitude/latitude columns into GeoJSON FeatureCollections.
- **Customizable Mapping:** Configure which CSV columns correspond to longitude and latitude.
- **Header Support:** Properly handles CSV files with or without header rows.
- **Stream Integration:** Works seamlessly with other Pulp handlers for further processing.

## How to publish

* Note changes in `CHANGELOG.md`
* Git publish:
```
git commit
git tag -a vX.X.X
git push
git push --tags
```
* done (update projects to use it)
