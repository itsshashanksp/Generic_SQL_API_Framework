# Response reference

Every completed action returns JSON. Query, SQL Resource, routine, and metadata
results use the same success envelope; writes add write-specific content. Errors
have a separate stable envelope.

## Query success

```json
{
  "success": true,
  "message": "Data Loaded Successfully",
  "data": [
    { "ItemCode": "A001", "ItemName": "Blue Pen" }
  ],
  "meta": {
    "page": 1,
    "pageSize": 25,
    "totalRows": 37,
    "rowsReturned": 1,
    "executionTime": 2.41
  }
}
```

| Field | Type | Meaning |
|---|---|---|
| `success` | boolean | Always `true` in this envelope. |
| `message` | string | Controller-specific success message. |
| `data` | array | Result rows. It is always an array. |
| `meta.page` | integer/null | Requested `pagination.page`, otherwise `null`. |
| `meta.pageSize` | integer/null | Requested `pagination.pageSize`, otherwise `null`. |
| `meta.totalRows` | integer | Count supplied by pagination, otherwise returned-row count. |
| `meta.rowsReturned` | integer | Number of result rows collected. |
| `meta.executionTime` | number/null | Database execution time in milliseconds when supplied by the executor. |

Paginated SELECT and SQL Resource actions normally execute a count query.
SQL Resource Mode can infer a complete first-page `TOP` total in the documented
optimization. The envelope does not contain result-column descriptions; request
`metadata.columns` separately when needed.

Success messages are exact:

| Action | Message |
|---|---|
| `select`, `union`, `unionAll`, `sql` | `Data Loaded Successfully` |
| `procedure` | `Procedure Executed Successfully` |
| `function` | `Function Executed Successfully` |
| `tableFunction` | `Table Function Executed Successfully` |
| `metadata.tables` | `Tables Loaded Successfully` |
| `metadata.columns` | `Columns Loaded Successfully` |
| `metadata.views` | `Views Loaded Successfully` |
| `metadata.procedures` | `Stored Procedures Loaded Successfully` |
| `metadata.schema` | `Schema Loaded Successfully` |
| `insert` | `Data Inserted Successfully` |
| `update` | `Data Updated Successfully` |
| `delete` | `Data Deleted Successfully` |
| `upsert` | `Data Upserted Successfully` |

## Write success

```json
{
  "success": true,
  "message": "Data Inserted Successfully",
  "data": [
    {
      "operation": "insert",
      "affectedRows": 1,
      "generatedId": 42
    }
  ],
  "meta": {
    "page": null,
    "pageSize": null,
    "totalRows": 0,
    "rowsReturned": 0,
    "executionTime": 1.27,
    "affectedRows": 1
  }
}
```

`operation` is `insert`, `update`, `delete`, or `upsert`. `generatedId` appears
only for an INSERT, or the insert branch of an UPSERT, when the resource declares
an identity column and the database returns its value. It is not guaranteed to
be present. Write data has one operation summary even though `rowsReturned` is 0.

## Error

```json
{
  "success": false,
  "message": "Invalid request.",
  "error": {
    "code": "INVALID_REQUEST",
    "details": [
      { "path": "pagination.page", "message": "Must be a positive integer." }
    ]
  },
  "data": []
}
```

Errors never include `meta`. `data` is always an empty array. `details` is an
array and may be empty. Database exception text and stack traces are not returned
to the client. See [Validation, errors, and security](Validation-and-Errors.md)
for status codes and handling guidance.
