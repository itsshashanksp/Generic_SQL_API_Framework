# SQL Resource Files

## Overview

A SQL resource file contains a predefined query owned and reviewed by the backend. The client sends a resource ID; `config/sql-resources.php` maps that ID to the file, and `SqlResourceRegistry` verifies that the resolved file stays under `Backend/queries`.

`SqlRepository` reads the complete file, trims whitespace and trailing semicolons, accepts one read-only `SELECT` query with optional leading comments or a top-level `WITH` clause, applies the resource's configured runtime behavior, and calls `QueryEngine::executePrepared`. There is no general templating engine or client-defined placeholder syntax.

Registration fields and the end-to-end class flow are covered in [SQL Resource Configuration](SQL-Resource-Configuration.md). This document focuses on authoring the `.sql` file.

## File Location

The query root is defined by `QUERY_PATH` and currently resolves to:

```text
Backend/queries/
```

Existing report resources are stored at:

```text
Backend/queries/reports/customer.sql
Backend/queries/reports/item.sql
```

Widget resources use `Backend/queries/widgets/`. Subdirectories are an organization convention; the enforced rule is that the registered file must exist and its canonical path must be beneath `Backend/queries`.

## Basic SQL Resource

`queries/reports/item.sql` is a basic output-filter resource:

```sql
SELECT
    Item_Code,
    Item_Desc,
    Item_MRP
FROM ItemMasterTable
```

The corresponding registry entry declares those output names and a deterministic default order:

```php
'item' => [
    'file' => QUERY_PATH . '/reports/item.sql',
    'columns' => ['Item_Code', 'Item_Desc', 'Item_MRP'],
    'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']],
],
```

Because `filterPlacement` is omitted, runtime filters are applied to a derived table around this SQL. Do not put the runtime-filter marker in this file.

## File Parsing and Execution Rules

The current implementation is intentionally small:

1. `SqlResourceRegistry` reads the file only to validate marker count and placement.
2. `SqlRepository` reads it again through `QueryEngine::getQuery()`.
3. Leading/trailing whitespace is trimmed, and any semicolons plus whitespace at the end are removed.
4. Statement analysis accepts a main `SELECT` or a `WITH` clause whose main statement is `SELECT`; it separates the CTE scope from the body used by wrappers.
5. Runtime filters are inserted either outside the query or at the one controlled source marker.
6. Runtime/default sorting and optional pagination are applied.
7. `QueryEngine` prepares and executes the resulting SQL through ODBC.

Practical consequences:

- Author one read query. Leading comments and top-level standard or recursive CTEs are accepted. Multiple statements, a non-SELECT main statement, and top-level `SELECT ... INTO` are rejected.
- The statement analyzer is deliberately not a complete SQL Server parser. Resource review and a least-privilege connection remain necessary.
- A trailing semicolon is allowed and removed. Do not rely on client or registry parameters embedded in the file; none are implemented.
- SQL Server, not SQL Resource Mode, parses expressions, joins, aggregation, `CASE`, and window syntax.
- Most resource queries are wrapped as a derived table. Stable, unique output names and SQL that is valid inside `FROM (...)` are important.

## Output Columns and Aliases

Give every calculated output a stable identifier alias and register the same spelling in `columns`:

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
GROUP BY Cust_Name
```

```php
'columns' => [
    'Cust_Name',
    'TotalCustomers',
    'MinimumBill',
    'MaximumBill',
],
```

Runtime sorting uses `columns`. Output-mode filtering also uses it unless a separate `filterColumns` list is configured. The runtime match is case-insensitive, but consistent spelling prevents surprises in response consumers.

The backend does not validate the SQL result schema against `columns` and does not strip undeclared result fields. A missing/mistyped alias generally becomes a SQL Server error only when runtime SQL references it. The SQL `SELECT` list is the actual output boundary, so review it for sensitive data.

## Complex SQL

SQL Resource Mode does not maintain a function or expression allowlist inside registered SQL. Server-owned files may use SQL Server-supported CTEs, recursive CTEs, subqueries, derived tables, EXISTS/NOT EXISTS, CASE, aggregates, nested/scalar/table-valued functions, JSON/XML functionality, windows and PARTITION BY, complex joins/APPLY, UNION/UNION ALL/INTERSECT/EXCEPT, GROUP BY, HAVING, TOP, and OFFSET/FETCH when valid for the execution shape. Prefer testing the resource both without runtime state and with every enabled filter, sort, and pagination path.

### Aggregates and source filtering

The current customer resource filters source rows before grouping:

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
/*__RUNTIME_FILTERS__*/
GROUP BY Cust_Name
```

With a `source` placement request, the marker becomes a complete generated clause such as:

```sql
WHERE [Cust_Name] LIKE ? AND [StDate] BETWEEN ? AND ?
```

The values are held separately in the positional parameter array.

### CASE example

`CASE` can be ordinary backend-authored SQL; it has no special resource configuration:

```sql
SELECT
    Cust_Name,
    CASE
        WHEN Bill_Amt >= 10000 THEN 'HighValue'
        ELSE 'Standard'
    END AS CustomerBand,
    Bill_Amt
FROM CustomerTable
```

Register `Cust_Name`, `CustomerBand`, and `Bill_Amt` in `columns` if clients need to sort by them. SQL Server must accept the expression and resulting wrapped query.

### Window-function example

A window expression is likewise authored directly and exposed through a stable alias:

```sql
SELECT
    Cust_Name,
    Bill_Amt,
    ROW_NUMBER() OVER (
        PARTITION BY Cust_Name
        ORDER BY Bill_Amt DESC
    ) AS BillRank
FROM CustomerTable
```

Register `BillRank` if it may be used for runtime sorting. This example relies on SQL Server's window-function support; the SQL Resource pipeline does not use `WindowFunctionBuilder` to build or validate file contents.

## CTE files

Standard and recursive CTE files are supported:

```sql
WITH CustomerTotals AS (
    SELECT CustomerCode, SUM(Amount) AS TotalAmount
    FROM dbo.Sales
    GROUP BY CustomerCode
)
SELECT CustomerCode, TotalAmount
FROM CustomerTotals
```

The repository keeps the `WITH` prefix ahead of output-filter, count, modern OFFSET/FETCH, and legacy ROW_NUMBER wrappers so CTE names remain in scope. A trailing `OPTION (...)` query hint, including `MAXRECURSION`, is kept after the generated count/data statement. SQL Server owns CTE syntax and recursion rules; the resource path does not rebuild the CTE through JSON Query Mode.

## Runtime Filters

The public shape is:

```json
"filters": [
  { "field": "Cust_Name", "operator": "LIKE", "value": "A%" },
  { "field": "StDate", "operator": "BETWEEN", "value": ["2021-04-01", "2022-03-31"] }
],
"filterLogic": "AND"
```

Supported operators are `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, and `IS NOT NULL`. All filters in one request are joined by the single `filterLogic`, which defaults to `AND`; nested filter groups are not part of this contract.

Field names must be unqualified identifiers and must match the resource's `filterColumns` (or `columns` when no separate filter list exists). The repository inserts only the canonical allowlisted identifier, bracket-quoted, and an allowlisted operator. Values are never interpolated: it creates `?` placeholders and passes values to `QueryEngine::executePrepared`.

For a mapped complex resource, the request field instead matches a logical key
in the registry's `filters` map. The registry owns the corresponding SQL
expression and its `output`, `where`, or `having` location. The frontend never
knows or submits names such as `BIL.Bill_Date` or `SUM(BIL.Item_Rate)`.

### Output placement

This is the default. For the basic item query, a runtime filter produces the equivalent structure:

```sql
SELECT * FROM (
    SELECT Item_Code, Item_Desc, Item_MRP
    FROM ItemMasterTable
) AS SqlResource
WHERE SqlResource.[Item_Desc] LIKE ?
```

Output filtering can reference only fields produced by the inner query. It is appropriate for ordinary row-producing resources. On an aggregate resource it filters after aggregation, which is often not the intended report semantics.

### Source placement

Set `filterPlacement` to `source`, declare the source fields in `filterColumns`, and put exactly one marker in the file:

```sql
FROM CustomerTable
/*__RUNTIME_FILTERS__*/
GROUP BY Cust_Name
```

The repository replaces the marker with `WHERE ...` before execution. With no runtime filters it replaces the marker with an empty string. The field need not be returned, as demonstrated by `StDate` in the customer report.

The marker is not arbitrary templating: no other placeholder is recognized, and clients cannot control the replacement text. Because the generated fragment starts with `WHERE`, do not place it after an existing `WHERE` or where only an `AND` predicate would be valid.

The marker is retained for existing source-filter resources such as Customer.
It is optional architecture, not a requirement for new filterable SQL. Simple
resources use safe outer filtering automatically. Complex single-query
resources can use explicit server-side WHERE/HAVING mappings, which insert at
depth-aware top-level clause boundaries. With no requested filters, mapped SQL
receives no filter clause or parameters. Set-operation branch placement is not
guessed; use output filtering or separate resources.

For an integer-backed date field configured as `integer-date`, valid ISO UI input is converted before binding:

```text
"2021-04-01" -> 20210401
```

There is no general per-field type declaration or custom conversion system.

## Runtime Sorting

Clients send:

```json
"sort": [
  { "field": "MaximumBill", "direction": "DESC" },
  { "field": "Cust_Name", "direction": "ASC" }
]
```

Sort fields must match `columns`; source-only `filterColumns` cannot be used for sorting. The request validator restricts fields to simple unqualified identifiers and directions to `ASC` or `DESC`. The repository resolves fields case-insensitively to the configured spelling and bracket-quotes them, preventing a client from supplying an arbitrary sort expression.

A non-empty runtime list replaces `defaultSort`; an empty or omitted list normally selects the configured default. Multiple sort entries preserve their request/configuration order.

Authored top-level `ORDER BY` has special handling. With no runtime filters or non-empty runtime sort, the repository can preserve and execute it directly, including supported pagination. When wrapping is required, it removes a top-level authored order from the count source and normally applies the runtime/default order outside. A `SELECT TOP ... ORDER BY ...` resource has additional preservation logic so the `TOP` set retains its authored order. Test authored `TOP` and top-level ordering carefully with all intended runtime combinations.

## Pagination

Clients opt in explicitly:

```json
"pagination": { "page": 2, "pageSize": 25 }
```

Both values must be positive integers. Developers should not manually add `OFFSET/FETCH` or `ROW_NUMBER()` for normal resource pagination.

For a paginated resource, `PaginationBuilder` normally:

1. executes a prepared `COUNT(*)` over the resource/count source with the same filter parameters;
2. checks the SQL Server database compatibility level;
3. uses `OFFSET ... FETCH NEXT ...` at compatibility level 110 or newer, or a `ROW_NUMBER()` wrapper for older levels;
4. executes the page query and returns the count as `meta.totalRows`.

The pagination offset and size come from validated positive integers and are rendered by the backend. A deterministic registered/default or authored order is required for stable pages.

There is one implemented optimization: a top-level `SELECT TOP N ... ORDER BY ...` with no runtime filters or non-empty runtime sort, requested as page 1 with `pageSize >= N`, executes directly without a separate count; `totalRows` is inferred from `rowsReturned`. Other partial `TOP` pages use the normal count/pagination path.

A resource may own an `ORDER BY ... OFFSET/FETCH` clause. It is executed directly when the request supplies no runtime filters, non-empty runtime sort, or pagination. Combining those runtime controls with authored OFFSET/FETCH is rejected with `INVALID_SQL_PAGINATION`, because wrapping or appending a second page clause could change semantics or generate invalid SQL. Use either authored pagination or framework pagination for a resource, not both.

Without `pagination`, no count query is requested. The response has `meta.page` and `meta.pageSize` as `null`, and `meta.totalRows` defaults to the number of returned rows.

## Complete Example

### 1. SQL file

`queries/reports/customer.sql`:

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
/*__RUNTIME_FILTERS__*/
GROUP BY Cust_Name
```

### 2. Resource registration

`config/sql-resources.php`:

```php
'customer' => [
    'file' => QUERY_PATH . '/reports/customer.sql',
    'columns' => ['Cust_Name', 'TotalCustomers', 'MinimumBill', 'MaximumBill'],
    'filterColumns' => ['Cust_Name', 'StDate'],
    'filterValueTypes' => ['StDate' => 'integer-date'],
    'filterPlacement' => 'source',
    'defaultSort' => [['field' => 'Cust_Name', 'direction' => 'ASC']],
],
```

### 3. API request

```json
{
  "action": "sql",
  "resource": "customer",
  "filters": [
    { "field": "Cust_Name", "operator": "LIKE", "value": "A%" },
    { "field": "StDate", "operator": "BETWEEN", "value": ["2021-04-01", "2022-03-31"] }
  ],
  "filterLogic": "AND",
  "sort": [{ "field": "MaximumBill", "direction": "DESC" }],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

### 4. Response shape

Values depend on the configured database; the response shape is:

```json
{
  "success": true,
  "message": "Data Loaded Successfully",
  "data": [
    {
      "Cust_Name": "Acme Stores",
      "TotalCustomers": 12,
      "MinimumBill": 125.5,
      "MaximumBill": 9200
    }
  ],
  "meta": {
    "page": 1,
    "pageSize": 25,
    "totalRows": 1,
    "rowsReturned": 1,
    "executionTime": 2.41
  }
}
```

The row and timing values above are illustrative. The property names match the SQL aliases and standard `Response` envelope.

## SQL Resource Rules

- Use one backend-owned read-only `SELECT`, optionally preceded by comments or a standard/recursive `WITH` clause.
- Select only fields safe to return. `columns` controls runtime sorting, not response redaction.
- Use stable, unique identifier aliases and keep the registry synchronized with them.
- Use the exact source-filter marker only when the registration declares `filterPlacement: source`.
- Let the backend bind runtime values and apply pagination; do not invent file placeholders or accept dynamic SQL fragments from a client.
- Keep credentials and connection strings in database configuration, never in a resource file or request.
- Avoid dynamic SQL inside resources. Treat every file as trusted application code and review it accordingly.
- Keep a resource focused on one report purpose and test its unfiltered, filtered, sorted, and paginated forms.

## SQL Resource vs JSON Query

Choose **JSON Query Mode** when the public request should select from the implemented structural options—fields, supported functions, validated joins, grouping, CTEs within that builder's limits, and similar query composition.

Choose **SQL Resource Mode** when the backend should freeze the complete projection and business logic in a named file, while the client supplies only approved filters, sorting, and pagination. It supports reviewed complex and SQL Server-specific query text without requiring functions or expressions to appear in JSON Query Mode's client-facing allowlists.

Neither mode accepts raw SQL, SQL paths, credentials, connection information, or arbitrary runtime SQL expressions from the frontend.
