# Changelog

All notable changes to `mapsight/pulp-cache` are documented here.

## Unreleased

## 1.0.0 - 2026-06-18

### Added

- Add `PulpCache::remember()` for caching source pipeline results with TTL-based freshness checks.
- Add `PulpCache::cache()` for pass-through file caching inside existing pipelines.
- Add stale cache fallback support for source refresh failures.
- Add manifest-backed cache storage for raw string content and serialized structured content.
