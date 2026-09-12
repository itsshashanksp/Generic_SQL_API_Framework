# Current limitations

These are current public-contract boundaries, not hidden supported features.
Planned work is tracked separately in [Roadmap](Roadmap.md).

## Universal API and security

- There is no API authentication, authorization, role/tenant policy, rate limit,
  or transaction/session contract.
- CORS permits two hard-coded Vite development origins; it is not access control.
- The endpoint recommends POST but does not reject body-bearing GET requests.
- JSON Query Mode accepts metadata-valid client-selected tables; it has no table
  resource registry. Routine identifiers are not resource-allowlisted.
- Metadata actions expose catalog object names to callers.
- SQL Server over ODBC is the only provider.

## JSON Query Mode

- Public joins are INNER/LEFT/RIGHT only, with one equality predicate. FULL,
  CROSS, APPLY, multiple ON predicates, and non-equality joins are unavailable.
- Filter subqueries exist only for IN, NOT IN, EXISTS, and NOT EXISTS. There are
  no general FROM/derived-table or SELECT-expression subqueries.
- Filter logic is one top-level AND/OR value; nested Boolean groups are absent.
- HAVING entries use aggregate comparisons and are always joined with AND.
- One standard or recursive CTE is supported. Multiple/nested CTE definitions
  and CTEs inside nested query/set branches are rejected.
- Window functions require ORDER BY and do not expose PARTITION BY. Numeric
  positional ORDER BY is rejected.
- Arithmetic exposes one binary expression level, not an arbitrary expression tree.
- `TIMEFROMPARTS` exists in the internal function/builder list but is unusable
  publicly because the required `fractions` property is rejected.
- The documented function list is exhaustive; arbitrary SQL Server functions,
  JSON/XML functions, PIVOT, and free-text expressions are not public JSON.
- Public set operations are UNION and UNION ALL only. Their branches cannot sort,
  paginate, or define CTEs, and the combined result has no top-level sort/page.
  INTERSECT/EXCEPT are internal builder capabilities only.

Use SQL Resource Mode for approved complex read-only SQL beyond these boundaries.

## SQL Resource Mode

- Clients cannot register resources or send SQL, paths, expressions, or placement.
- A resource is one read-only SELECT/CTE statement; SELECT INTO and multiple
  statements are rejected.
- Runtime fields must be preconfigured. Mapped WHERE/HAVING insertion on a
  top-level set operation is rejected as ambiguous; use output placement or a
  dedicated resource.
- OR cannot combine filters assigned to different output/WHERE/HAVING locations.
- Only the `integer-date` custom runtime value type is implemented.
- An authored OFFSET/FETCH resource rejects all request filters, sorting, and
  pagination. Resource authors must choose fixed pagination or runtime controls.
- Server-owned specialized SQL is still constrained by actual SQL Server version,
  compatibility, permissions, object schema, and resource transformation rules.

## Write API

- One data object is accepted per request; there is no bulk insert/update/delete.
- No public begin/commit/rollback or multi-action atomic transaction exists.
- UPDATE/DELETE filters can match multiple rows; there is no single-row guarantee.
- Values cannot contain SQL expressions or request database defaults explicitly.
- There is no identity-insert override, returned-column selection, soft delete,
  optimistic-concurrency token, or generic patch-test operation.
- UPSERT uses SQL Server MERGE with HOLDLOCK and requires an exact unfiltered
  UNIQUE/PRIMARY KEY index. The API does not provide broader transaction guarantees
  or eliminate SQL Server MERGE operational caveats.
- Unsupported SQL Server data types are rejected rather than guessed.

## Routines, metadata, and responses

- Routine parameters are positional. Named/output parameters, signature
  discovery, result-set choice, runtime filter/sort/page, and per-routine
  allowlisting are absent.
- `source.alias` passes the shared validator for routines and metadata.columns but
  is discarded by normalization and has no effect.
- Query responses do not contain result-column type/schema metadata. Use
  `metadata.columns` for a physical table, noting that derived result schemas are
  not described.
- Public database errors are intentionally generic. Diagnose through protected
  server logs rather than response text.

## Operations

- The PHP built-in server and Windows launcher are development conveniences and
  single-process; they are not production multi-worker hosting.
- No live SQL Server integration workflow ships with CI. The automated suite uses
  fakes and validates generated SQL/contracts without database credentials.
