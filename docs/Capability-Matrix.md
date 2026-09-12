# Capability matrix

This is the single authoritative cross-mode capability matrix. “Server SQL”
means syntax can be authored inside an approved read-only SQL Resource file; it
does not mean a client can send that syntax or that every connected SQL Server
version supports it.

| Capability | JSON Query Mode | SQL Resource Mode | Write API |
|---|---|---|---|
| SELECT | Public `select` | Server-owned SELECT/CTE | No |
| Runtime comparison/LIKE/list/range/null filters | Yes | Yes, declared execution or legacy logical fields | UPDATE/DELETE only |
| Filter subqueries / EXISTS | IN/NOT IN/EXISTS/NOT EXISTS only | Can be authored in SQL; runtime values are not subqueries | No |
| AND / OR | One top-level logic | One logic; OR cannot span locations | One top-level logic |
| Integer-date filter conversion | BETWEEN on integer metadata | Configured `integer-date` | Live database type validation |
| Sorting | Logical fields/selected aliases | Execution/legacy output aliases and defaultSort | No |
| Pagination | Count + OFFSET/FETCH or ROW_NUMBER | Same; TOP optimization; authored OFFSET/FETCH exclusive | No |
| TOP / limit | Public positive `limit` | May be authored; optimization supported | No |
| DISTINCT | Public boolean | May be authored | No |
| INNER/LEFT/RIGHT JOIN | One equality ON term | May be authored | No |
| FULL/CROSS JOIN | No | May be authored | No |
| CROSS/OUTER APPLY | No | May be authored | No |
| Multiple/non-equality join predicates | No | May be authored | No |
| GROUP BY | Identifier list | May be authored | No |
| HAVING | Aggregate comparisons, AND | May be authored; mapped runtime HAVING | No |
| Aggregate/string/date/math/null/conversion functions | Documented allowlist | SQL Server syntax may be authored | No expression values |
| CASE / one binary arithmetic expression | Yes | May be authored | No expression values |
| Window functions | Eight public functions, ORDER BY required | May be authored | No |
| Window PARTITION BY | No | May be authored | No |
| Standard CTE | One | May be authored | No |
| Recursive CTE | One anchor/recursive pair | May be authored | No |
| General derived table / SELECT expression subquery | No | May be authored | No |
| UNION / UNION ALL | Public top-level actions | May be authored | No |
| INTERSECT / EXCEPT | Internal builder only, not public | May be authored | No |
| JSON/XML/PIVOT/UNPIVOT and specialized SQL Server syntax | No public expression | May be authored in approved SQL | No |
| Runtime filter placement | Builder WHERE | Validated output/source/HAVING | Builder WHERE |
| INSERT | No | Read-only resources reject writes | Registered single object |
| UPDATE | No | Read-only resources reject writes | Registered, non-empty filters |
| DELETE | No | Read-only resources reject writes | Registered, non-empty filters |
| UPSERT | No | Read-only resources reject writes | MERGE/HOLDLOCK, configured unique keys |
| Bulk writes | No | No | No |
| Transactions | No public contract | No public contract | No public contract |
| Prepared values | WHERE/HAVING | Runtime filters | Data and filters |
| Client arbitrary SQL/path | Rejected/not a property | Rejected/not a property | Rejected/not a property |
| Metadata checks | Tables/columns/projections | Discovered file plus execution/legacy allowlists | Registry plus live write metadata |

## Other public execution surfaces

| Capability | Support |
|---|---|
| Stored procedure | `procedure`, positional prepared parameters |
| Scalar function | `function`, positional prepared parameters, `Result` column |
| Table-valued function | `tableFunction`, positional prepared parameters |
| Table metadata | `metadata.tables` |
| Column metadata | `metadata.columns` |
| View metadata | `metadata.views` |
| Procedure metadata | `metadata.procedures` |
| Whole schema rows | `metadata.schema` |
| API authentication/authorization | Not implemented |
| Database provider | SQL Server through ODBC only |

For exact shapes use [Action reference](Action-Reference.md); for unsupported
boundaries and workarounds use [Current limitations](Limitations.md).
