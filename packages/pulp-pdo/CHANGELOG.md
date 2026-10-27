# Changelog

All notable changes to `mapsight/pulp-pdo` are documented here.

## Unreleased

### Added

- Add `PulpPdo::write()` to insert each file's rows into a table, one transaction per file, optionally replacing all or matching rows first.
- Add `PulpPdo::transaction()` to run custom writes per file in a transaction.
- Add `PulpPdo::query()` to emit the rows of a query as a file.
- Add the `setUp` option to prepare a connection, for example create tables, before the first write.
