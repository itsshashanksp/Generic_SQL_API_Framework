# Filtering, sorting, and pagination

Runtime controls have similar JSON shapes across modes, but their allowlists and
operators differ. Do not assume a filter valid for one action is valid for all.

## Operator matrix

| Operator | JSON Query | SQL Resource | Write UPDATE/DELETE | Value |
|---|---:|---:|---:|---|
| `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=` | yes | yes | yes | one scalar/null at request-shape stage; write null is later rejected |
| `LIKE`, `NOT LIKE` | yes | yes | yes | one value |
| `IN`, `NOT IN` | values or subquery | non-empty values | non-empty values | list or documented subquery |
| `BETWEEN`, `NOT BETWEEN` | yes | yes | yes | exactly two values |
| `IS NULL`, `IS NOT NULL` | yes | yes | yes | omit `value` |
| `EXISTS`, `NOT EXISTS` | subquery only | no | no | omit `field`; provide `query` |

Operators are normalized to uppercase after validation. Unknown operators and
unknown filter properties are rejected.

## JSON Query filters

```json
{
  "action": "select",
  "source": { "table": "Customers" },
  "fields": ["CustomerCode", "Name", "Age", "Status"],
  "filters": [
    { "field": "Name", "operator": "LIKE", "value": "%John%" },
    { "field": "Age", "operator": ">=", "value": 18 },
    { "field": "Status", "operator": "NOT IN", "value": ["Deleted", "Blocked"] }
  ],
  "filterLogic": "AND"
}
```

`filterLogic` is `AND` by default and accepts exactly uppercase `AND` or `OR` in
the public SELECT contract. It applies to all top-level filters; nested filter
groups are not supported.

Examples of exact shapes:

```json
{"field":"Age","operator":"BETWEEN","value":[18,65]}
```

```json
{"field":"Email","operator":"IS NOT NULL"}
```

```json
{
  "operator": "EXISTS",
  "query": {
    "source": { "table": "Bills" },
    "fields": ["BillNo"],
    "filters": [{ "field": "Amount", "operator": ">", "value": 1000 }]
  }
}
```

IN/NOT IN subqueries must select exactly one explicit field. Nested bodies reject
`action`, `sort`, `pagination`, and `with`.

When live metadata identifies a JSON Query BETWEEN field as an integer-family
column, validated `YYYY-MM-DD` bounds are converted to `YYYYMMDD` integers.

## SQL Resource filters

The frontend shape stays simple:

```json
{
  "action": "sql",
  "resource": "reports/customer",
  "execution": {
    "columns": ["Cust_Name", "TotalCustomers", "MinimumBill", "MaximumBill"],
    "filters": {
      "StDate": {
        "expression": "StDate",
        "placement": "source",
        "valueType": "integer-date"
      }
    }
  },
  "filters": [
    { "field": "Cust_Name", "operator": "LIKE", "value": "%John%" },
    { "field": "StDate", "operator": "BETWEEN", "value": ["2026-01-01", "2026-12-31"] }
  ],
  "filterLogic": "AND"
}
```

The logical field must be declared by the SQL action's validated `execution`
metadata or by a legacy resource definition. Execution columns provide output
fields automatically; explicit mappings select `output`, `source`, or `having`.
Source mappings accept only an optionally qualified identifier, and HAVING accepts
only the documented single aggregate form. Values always remain prepared.

`integer-date` accepts a real calendar date as `YYYY-MM-DD`, an eight-digit
string, or an eight-digit JSON integer and binds it as a `YYYYMMDD` integer.
Invalid dates use `INVALID_SQL_RUNTIME_VALUE`.

OR is rejected with `INVALID_SQL_RUNTIME_FILTER` when requested filters span more
than one SQL location, because distributing OR across WHERE/HAVING/output would
change its meaning.

## Write filters

UPDATE and DELETE require a non-empty list. Fields are unqualified identifiers
in the Write resource's `filterColumns`. Values are validated against live SQL
Server column types; null values must use `IS NULL` or `IS NOT NULL`.

```json
{
  "action": "update",
  "resource": "crud-test",
  "data": { "Status": "Active" },
  "filters": [
    { "field": "CustomerCode", "operator": "=", "value": "C001" },
    { "field": "Age", "operator": "BETWEEN", "value": [18, 65] }
  ],
  "filterLogic": "AND"
}
```

Write filters cannot contain subqueries or EXISTS. UPSERT does not accept filters.

## Parameterization and type handling

Ordinary JSON Query WHERE/HAVING values, SQL Resource runtime values, Write data,
Write filters, and routine parameters are passed as prepared parameters. Lists
produce one placeholder per member. NULL operators produce no value placeholder.
Identifiers and SQL expressions cannot be supplied through value fields.

Write values receive the strongest type validation: integer ranges, numeric,
bit, string length, ISO date/time, UUID, binary-string, nullability, and supported
database type checks. SQL Resource mappings provide only optional `integer-date`
conversion; otherwise SQL Server handles parameter conversion.

## Sorting

```json
{
  "action": "select",
  "source": { "table": "Customers" },
  "fields": ["CustomerCode", "Name"],
  "sort": [
    { "field": "Name", "direction": "ASC" },
    { "field": "CustomerCode", "direction": "DESC" }
  ]
}
```

Direction defaults to ASC and may be ASC or DESC. Numeric positions such as
`"field": "1"` are rejected. JSON Query sorting accepts a validated source field
or selected top-level alias. SQL Resource sorting accepts only output aliases in
`execution.columns` or a legacy columns allowlist and uses the applicable
`defaultSort` when the request omits it.
Writes, routines, metadata, and public set operations have no sort property.

Window functions carry their own required `sort`; no positional ordering or
PARTITION BY is public. A JSON Query without top-level sorting gets a builder
default based on a usable projection, or metadata when necessary. A grouped
query defaults to its first group field.

## Pagination and limit

```json
{
  "action": "select",
  "source": { "table": "Customers" },
  "fields": ["CustomerCode", "Name"],
  "pagination": { "page": 1, "pageSize": 50 }
}
```

Both members are required positive JSON integers; there are no implicit page
defaults. SQL Server compatibility level 110+ uses OFFSET/FETCH. Older levels use
a ROW_NUMBER wrapper. The backend normally runs a count query for `totalRows`.

JSON Query `limit` is a separate positive integer producing TOP and is available
only on SELECT bodies. SQL Resource files may own TOP and ORDER BY; a complete
first page can avoid a separate count and infer total rows. A resource with
authored OFFSET/FETCH rejects any request filter, sort, or pagination via
`INVALID_SQL_PAGINATION`.
