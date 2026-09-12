# Frontend integration guide

The backend accepts one Universal JSON request and returns one stable envelope.
A frontend report model is application-owned; translate it into an accepted API
action rather than sending the report model unchanged.

```text
report/widget configuration
  -> choose JSON Query Mode or SQL Resource Mode
  -> merge validated UI filter/sort/page state
  -> POST /api/index.php
  -> render response.data
  -> update pagination from response.meta
  -> map error.details paths to UI fields
```

## HTTP client

```js
async function callBackend(request) {
  const response = await fetch('/api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(request)
  });

  const payload = await response.json();
  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Backend request failed');
    error.status = response.status;
    error.code = payload.error?.code;
    error.details = payload.error?.details ?? [];
    throw error;
  }
  return payload;
}
```

Set the URL for the hosting layout: `/api/index.php` assumes the repository root
is served, while the bundled `-t api` launcher exposes the same entry script at
`/index.php`.

Do not assume a non-2xx response has `meta`. On success, `data` is always an
array. Use `meta.page`, `meta.pageSize`, and `meta.totalRows` for page controls;
`rowsReturned` is only the size of this response.

## JSON Query report

A frontend-owned definition might be:

```json
{
  "title": "Active customers",
  "queryDefinition": {
    "format": "json",
    "source": { "table": "Customers", "alias": "C" },
    "fields": ["C.CustomerCode", "C.Name", "C.Status"]
  }
}
```

`title`, `format`, labels, and presentation are not API properties. Translate the
definition and UI state to:

```json
{
  "action": "select",
  "source": { "table": "Customers", "alias": "C" },
  "fields": ["C.CustomerCode", "C.Name", "C.Status"],
  "filters": [
    { "field": "C.Status", "operator": "=", "value": "Active" },
    { "field": "C.Name", "operator": "LIKE", "value": "%john%" }
  ],
  "filterLogic": "AND",
  "sort": [{ "field": "C.Name", "direction": "ASC" }],
  "pagination": { "page": 1, "pageSize": 50 }
}
```

The backend validates the structure and metadata, generates prepared SQL, and
returns selected rows. Use this mode when frontend configuration legitimately
selects from the public JSON function/join/filter vocabulary.

## SQL Resource report

Frontend configuration can store the path-derived resource ID and reviewed
execution metadata. This is application configuration, not arbitrary user input:

```json
{
  "title": "Customer bill summary",
  "queryDefinition": {
    "format": "sql",
    "resource": "reports/customer",
    "execution": {
      "columns": ["Cust_Name", "TotalCustomers", "MinimumBill", "MaximumBill"],
      "filters": {
        "StDate": {
          "expression": "StDate",
          "placement": "source",
          "valueType": "integer-date"
        }
      },
      "defaultSort": [{ "field": "Cust_Name", "direction": "ASC" }]
    }
  }
}
```

Translate it and the UI date/name controls to:

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
    },
    "defaultSort": [{ "field": "Cust_Name", "direction": "ASC" }]
  },
  "filters": [
    { "field": "Cust_Name", "operator": "LIKE", "value": "A%" },
    { "field": "StDate", "operator": "BETWEEN", "value": ["2021-04-01", "2022-03-31"] }
  ],
  "sort": [{ "field": "MaximumBill", "direction": "DESC" }],
  "pagination": { "page": 1, "pageSize": 25 },
  "filterLogic": "AND"
}
```

The backend resolves `reports/customer`, validates the execution grammar and
logical fields, converts the integer date, inserts prepared filters at the
approved stage, and runs server-owned SQL. `queryDefinition` itself is not an API
property: the frontend translates its reviewed contents into the direct top-level
request shown above. A resource with no UI controls can omit `execution` entirely.

## Write form

For an edit form, submit one registered object:

```json
{
  "action": "update",
  "resource": "crud-test",
  "data": { "Email": "new@example.com", "Status": "Active" },
  "filters": [{ "field": "CustomerCode", "operator": "=", "value": "C001" }]
}
```

On success, use `meta.affectedRows` (or the operation summary's `affectedRows`)
to decide whether the target changed. INSERT and an inserting UPSERT may include
`data[0].generatedId`; code must tolerate its absence. Treat 409 duplicate/
constraint errors differently from 400 form/contract errors.

Never allow an UPDATE or DELETE UI to emit an empty filter list. The backend
rejects it, but preventing the invalid request gives a clearer user experience.

## Metadata-driven builders

Use `metadata.tables`, `metadata.columns`, `metadata.views`,
`metadata.procedures`, and `metadata.schema` only when exposing database catalog
choices is appropriate. A metadata row does not grant permission to query or
write that object. `metadata.columns` returns physical table column type/null
information, not the output schema of an arbitrary report.

## Ownership boundary

Frontend should control:

- report title, labels, formatting, chart/table choice, and layout;
- selected approved values and UI filter/operator controls;
- current page/page size and approved sort state;
- choosing among backend-published resource IDs and reviewed action definitions;
- validation display and retry/user feedback.

Backend controls:

- SQL Resource SQL, discovery root/exclusions, and legacy mappings;
- allowed actions, columns, filters, keys, identity fields, and filter mappings;
- SQL expressions, WHERE/HAVING/output placement, joins, grouping, and resource
  default ordering;
- database credentials, encryption keys, driver, connection, permissions, logs,
  validation, SQL generation, and execution.

Frontend must never send:

- database credentials, encryption keys, connection strings, or driver options;
- arbitrary SQL, SQL fragments, resource file paths, URLs, or filesystem paths;
- arbitrary SQL Resource filter expressions/locations or internal filter markers;
- private normalized keys such as `controller`, `table`, `columns`, `column`,
  `where`, `top`, `page`, `pageSize`, or `params`;
- client-selected schema/table names for Write actions;
- SQL expressions as Write values or arbitrary modifications to resource SQL.

## Error handling

- 400: do not retry unchanged. Map `details[].path` to controls. A missing backend
  resource/field usually needs deployment/configuration rather than user input.
- 409: refresh state and explain duplicate/constraint conflict.
- 500: show a generic failure and provide the server request correlation context;
  do not expect SQL Server details in the response.
- 504: the query timed out; offer a controlled retry and investigate server-side.

The endpoint has no built-in API authentication or authorization. Production
frontends must not mistake CORS for protection; deploy behind appropriate HTTPS,
network, identity, and authorization controls.

Next: [Action reference](Action-Reference.md),
[JSON request reference](JSON-Request-Reference.md), and
[Response reference](Response-Reference.md).
