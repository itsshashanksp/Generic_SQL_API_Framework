# Set operations

The public Universal JSON Contract exposes `union` and `unionAll`.

```json
{
  "action": "unionAll",
  "queries": [
    {
      "source": { "table": "CurrentItems" },
      "fields": ["ItemCode", "Description"]
    },
    {
      "source": { "table": "ArchivedItems" },
      "fields": ["ItemCode", "Description"]
    }
  ]
}
```

`union` emits SQL UNION and removes duplicate rows. `unionAll` emits UNION ALL
and retains them. Each `queries[]` member is a JSON Query SELECT body without an
`action` property.

The list must be non-empty. Explicit projections must have the same field count;
wildcards are resolved through live metadata before execution. SQL Server checks
corresponding expression type compatibility. Branches cannot contain `sort`,
`pagination`, or `with`, and the set request has no top-level sort or pagination.

Success uses `Data Loaded Successfully` and the standard query response. Invalid
shape/count is `INVALID_REQUEST`; metadata/build/execution failures are
`QUERY_ERROR`.

INTERSECT and EXCEPT have internal builder support but no public action, so they
are not available through JSON Query Mode. A reviewed SQL Resource can contain
server-owned UNION, UNION ALL, INTERSECT, or EXCEPT SQL, subject to the resource
executor rules. Mapped inner WHERE/HAVING runtime placement on a top-level set
operation is rejected as ambiguous; output wrapping or a dedicated resource is
required.
