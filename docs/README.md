# Backend documentation

This is the documentation entry point for frontend developers, backend
maintainers, and operators. The implementation is a backend-only SQL Server API:
clients send one allowlisted JSON action to the `api/index.php` entry script and
receive one standard JSON envelope. Its URL is `/api/index.php` from a repository
web root or `/index.php` when `api/` itself is the document root.

## Start here

1. [Introduction](Introduction.md) — scope and core concepts.
2. [HTTP API](API.md) — transport and the Universal JSON Contract.
3. [Action reference](Action-Reference.md) — all 16 public actions.
4. [JSON request reference](JSON-Request-Reference.md) — exact accepted fields and shapes.
5. [Response reference](Response-Reference.md) — success, write, and error envelopes.
6. [Frontend integration](Frontend-Integration.md) — turning UI state into requests.

## Querying

- [JSON Query Mode](Query-Mode.md)
- [Query examples](Query-Examples.md)
- [Query function reference](Query-Functions.md)
- [Filtering, sorting, and pagination](Filtering-Sorting-Pagination.md)
- [Set operations](Set-Operations.md)
- [Metadata and routines](Metadata-and-Routines.md)

## Server-owned SQL

- [SQL Resource Mode](SQL-Resource-Mode.md)
- [SQL Resource configuration](SQL-Resource-Configuration.md)
- [SQL Resource files and examples](SQL-Resource-Files.md)

## Writes

- [CRUD / Write API](CRUD.md)
- [Write resource configuration](Write-Resource-Configuration.md)

## Boundaries and operations

- [Validation, errors, and security](Validation-and-Errors.md)
- [Capability matrix](Capability-Matrix.md) — the authoritative quick comparison.
- [Current limitations](Limitations.md)
- [Architecture](Architecture.md)
- [Database configuration](Database-Configuration.md)
- [Hosting](Hosting.md)
- [Roadmap](Roadmap.md)
- [Changelog](../CHANGELOG.md)

## Terminology

- **JSON Query Mode** builds a validated SELECT from client-supplied structure.
- **SQL Resource Mode** discovers and executes server-owned, read-only SQL files;
  the client supplies a safe resource ID and optional validated execution metadata.
- **Write API** performs registered single-object INSERT, UPDATE, DELETE, or UPSERT.
- **Universal JSON Contract** is the shared request dispatch and response envelope.
- **Execution metadata** declares approved SQL Resource output controls and
  narrowly constrained source/HAVING mappings; it never contains arbitrary SQL.

Documentation describes the current implementation. Planned work belongs only in
[Roadmap](Roadmap.md); it is not part of the public contract.
