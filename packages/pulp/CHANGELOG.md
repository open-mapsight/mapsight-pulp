# Changelog

All notable changes to `mapsight/pulp` are documented here.

## Unreleased

## 1.1.0 - 2026-06-18

### Added

- Add lazy path-backed `File` instances via `File::fromPath()`.
- Add `File::stream()` for streaming file content without eagerly loading large files into memory.
- Add `Pulp::split()` to fan out one input stream into multiple branch pipelines and merge their results.
- Expand README examples and cover them with tests.
