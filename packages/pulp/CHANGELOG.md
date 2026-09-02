# Changelog

All notable changes to `mapsight/pulp` are documented here.

## Unreleased

## 1.2.0 - 2026-09-02

### Added

- Add `Pulp::ensureDirectory()` for the race-free mkdir used by dest and cache jobs.
- Add `srcHttp` disk sink (`sink => true` or a path) so large responses stay path-backed `File`s.
- Attach `httpStatus`, `httpLastModified`, `httpEtag`, and `httpType` on files from `srcHttp`.
- Add `srcHttp` options `client` (injectable Guzzle client) and `successStatuses`.

## 1.1.0 - 2026-06-18

### Added

- Add lazy path-backed `File` instances via `File::fromPath()`.
- Add `File::stream()` for streaming file content without eagerly loading large files into memory.
- Add `Pulp::split()` to fan out one input stream into multiple branch pipelines and merge their results.
- Expand README examples and cover them with tests.
