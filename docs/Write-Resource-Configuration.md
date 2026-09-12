# Write Resource Configuration

CRUD is controlled by `config/write-resources.php`. The shipped file returns an
empty array, so all write requests fail closed until a deployment explicitly
approves a resource. This registry is separate from `sql-resources.php`: report
queries and calculated aliases are not automatically safe mutation targets.

## Resource shape

```php
return [
    'customers' => [
        'schema' => 'dbo',
        'table' => 'Customers',
        'actions' => ['insert', 'update', 'delete', 'upsert'],
        'columns' => ['CustomerCode', 'Name', 'Email', 'Status'],
        'filterColumns' => ['Id', 'CustomerCode', 'Email'],
        'keys' => ['CustomerCode'],
        'identityColumn' => 'Id',
    ],
];
```

| Setting | Required | Meaning |
|---|---:|---|
| `table` | yes | Fixed physical SQL Server base table |
| `schema` | no | Fixed schema; defaults to `dbo` |
| `actions` | yes | Non-empty subset of `insert`, `update`, `delete`, `upsert` enabled for this resource |
| `columns` | yes | Non-empty list of columns clients may assign |
| `filterColumns` | no | Columns usable by UPDATE/DELETE filters; defaults to `columns` |
| `keys` | no | Exact UPSERT key set; empty disables UPSERT for this resource |
| `identityColumn` | no | Identity that INSERT or an inserting UPSERT may return |

All configured names must be simple SQL identifiers. Lists are checked
case-insensitively for duplicates. UPSERT keys must also appear in `columns`; an
identity must appear in either `columns` or `filterColumns`. At request time, the
registry is compared with live SQL Server metadata. A missing table/column or an
`identityColumn` that is not actually an identity is treated as server
misconfiguration and returned only as a generic query failure.

It is normal to include an identity in `filterColumns` but omit it from
`columns`. Even if a generated column is accidentally assignable in the registry,
live metadata prevents clients from writing identity, computed, timestamp, or
rowversion values.

## UPSERT keys

Create a PRIMARY KEY or unfiltered UNIQUE index that exactly protects the
configured logical key. The API verifies that matching live index metadata
exists, but cannot infer whether the selected columns are the intended business
key. A request's `keys` list must match the configured set exactly and every key
value must be non-null.

UPSERT is a single `MERGE ... WITH (HOLDLOCK)` statement. This avoids the unlocked
gap between separate UPDATE and INSERT statements without introducing a public
multi-action transaction contract. SQL Server MERGE behavior still warrants live
concurrency testing for each target, particularly when triggers, replication, or
other database-side automation is involved.

SQL Server behavior is described in Microsoft's [MERGE](https://learn.microsoft.com/en-us/sql/t-sql/statements/merge-transact-sql)
and [OUTPUT](https://learn.microsoft.com/en-us/sql/t-sql/queries/output-clause-transact-sql)
documentation. The builders capture OUTPUT into a table variable because a bare
OUTPUT result is not permitted on a target with an enabled trigger for that DML
action.

## Deployment checklist

1. Grant the API database account only the required INSERT/UPDATE/DELETE rights.
2. Add the smallest practical `columns` and `filterColumns` lists.
3. Confirm every required, non-generated column is assignable or has a database
   default; otherwise INSERT/UPSERT will fail configuration validation.
4. Configure `identityColumn` only when returning that identifier is safe.
5. For UPSERT, add and verify the matching database uniqueness constraint.
6. Run `php tests/run.php` and perform live SQL Server checks for DML, constraints,
   triggers, affected-row output, and concurrent UPSERT behavior.

There is no resource cache, bulk-write format, authentication/authorization, or
application-managed transaction contract. Each CRUD request executes as an
independent statement through the existing ODBC connection abstraction.

See [CRUD / Write API](CRUD.md) for every request, response, operator, validation
rule, and public error code.
