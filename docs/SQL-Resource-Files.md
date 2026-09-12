# SQL Resource files

A SQL Resource is a reviewed, server-owned `.sql` file beneath the configured
discovery root. The default root is `Backend/queries`. Files are discovered
recursively, so `queries/reports/customer.sql` has the public ID
`reports/customer`; clients never send the extension or a filesystem path.

## Add a resource

Create the file beneath the discovery root:

```sql
-- queries/reports/item-summary.sql
SELECT
    Item_Code,
    Description,
    SUM(Amount) AS TotalAmount
FROM dbo.ItemLedger
GROUP BY Item_Code, Description
```

It can immediately be executed without a per-file PHP entry:

```json
{ "action": "sql", "resource": "reports/item-summary" }
```

Use explicit output aliases when a frontend needs stable filter or sort names.
Discovery does not parse the projection and does not infer an output schema.

## Resource ID rules

- IDs contain slash-separated letters, digits, underscores, and hyphens.
- IDs are relative to the fixed server-owned root and omit `.sql`.
- Absolute paths, dot segments, repeated separators, backslashes, null bytes,
  URLs, and non-SQL extensions are rejected.
- Hidden and configured excluded directory segments are not discoverable.
- Case-insensitive duplicate discovered IDs fail closed.
- A unique basename such as `item-summary` may resolve a nested resource for
  compatibility. Use the full ID in new integrations; ambiguous basenames fail.

## Runtime metadata

Simple execution requires no metadata. Add the top-level `execution` object to
the request only when the UI needs runtime filtering, sorting, or a default
order:

```json
{
  "action": "sql",
  "resource": "reports/item-summary",
  "execution": {
    "columns": ["Item_Code", "Description", "TotalAmount"],
    "filters": {
      "MinimumTotal": {
        "expression": "SUM(Amount)",
        "placement": "having"
      }
    },
    "defaultSort": [
      { "field": "TotalAmount", "direction": "DESC" }
    ]
  },
  "filters": [
    { "field": "MinimumTotal", "operator": ">=", "value": 1000 }
  ],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

`execution.columns` is a control-field allowlist, not response projection or
redaction. The backend wraps the authored query when applying output filters,
sorting, and pagination. Pagination requires a runtime or default approved sort.

Filter mappings support three placements:

- `output`: an exact member of `execution.columns`, filtered on the wrapper;
- `source`: an identifier such as `StDate` or `L.StDate`, inserted in the
  top-level source query's WHERE stage;
- `having`: `COUNT`, `SUM`, `AVG`, `MIN`, or `MAX` over one identifier or `*`.

These expressions use a narrow public grammar. Arbitrary SQL fragments,
comments, clauses, function nesting, aliases, literals, and parameters are not
accepted. Filter operators are separately allowlisted and every value is bound
as a prepared parameter. `valueType: "integer-date"` is available for legacy
integer dates.

## Authored SQL rules

- The file must contain one read-only SELECT or CTE statement.
- Multiple statements and `SELECT INTO` are rejected.
- A trailing semicolon is accepted and removed before transformation.
- Complex joins, APPLY, subqueries, CTEs, aggregates, windows, SQL Server
  functions, JSON/XML expressions, and set operations may be authored because
  the file is trusted backend code.
- Authored OFFSET/FETCH cannot be combined with request filters, sorting, or
  pagination.
- Source/HAVING insertion on a top-level set operation is rejected as ambiguous;
  use output placement or a dedicated resource.
- OR cannot span multiple execution stages.

SQL Server remains responsible for validating objects, types, syntax, version
support, and permissions. Test every enabled runtime path against the deployment
database in addition to the database-independent suite.

## Legacy files and the marker

Existing entries in `config/sql-resources.php` continue to work. They may keep
trusted server-side `columns`, `filterColumns`, `filterValueTypes`, `filters`,
`filterPlacement`, and `defaultSort` mappings. The legacy
`/*__RUNTIME_FILTERS__*/` marker remains supported for entries configured with
source placement.

New discovered resources do not need the marker. Public source metadata is
inserted into the top-level WHERE stage by the repository. Retain a marker in an
existing file until its legacy ID and mapping have been migrated; removing it
prematurely can change compatibility behavior.

## Review checklist

- Put only public report SQL beneath discoverable directories.
- Exclude internal directories through the global settings.
- Prefer full path-based IDs and stable, unique aliases.
- Declare only control fields the UI actually needs.
- Never accept SQL text, resource paths, or expressions from arbitrary UI input.
- Use least-privilege database credentials and review query plans.

See [SQL Resource Mode](SQL-Resource-Mode.md) for the public contract and
[SQL Resource configuration](SQL-Resource-Configuration.md) for discovery,
legacy configuration, and migration details.
