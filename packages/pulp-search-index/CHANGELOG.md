# Changelog

All notable changes to `mapsight/pulp-search-index` are documented here.

## Unreleased

## 1.0.0 - 2026-10-27

### Added

- Add `PulpSearchIndex::index()` to turn GeoJSON sources into one search index.
- Add `PulpSearchIndex::toDatabase()` to store it in two tables (MySQL/MariaDB, SQLite), rewriting only changed sources.
- Add `SearchIndexDatabase::search()` to find places in the stored index, ranked like `SearchIndex::search()`.
