# Changelog

All notable changes to `mapsight/pulp-zip` are documented here.

## Unreleased

## 1.0.0 - 2026-06-18

### Added

- Add `PulpZip::unzip()` to expand matching ZIP archive entries into Pulp files.
- Add `PulpZip::zip()` to collect incoming Pulp files into one ZIP archive.
- Validate ZIP entry paths to reject absolute paths and path traversal segments.
- Stream ZIP input and extracted entries through temporary files to avoid eager archive payload reads.
