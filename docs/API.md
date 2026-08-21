# API Reference

## Overview

Generic SQL API Framework provides a JSON-based HTTP API for interacting with the database layer.

A client sends a JSON request to the API, the backend validates and processes the request, executes the required database operation, and returns a JSON response.

The API is independent of the frontend application.

---

## API Entry Point

The main API entry point is:

```text
api/index.php
```

When running the bundled Windows server, the URL will normally be:

```text
http://localhost:8000/api/index.php
```

The actual port is selected by `start-windows.bat` and printed when the API starts.

See [Hosting](Hosting.md) for more information.

---

## HTTP Method

Query requests use:

```http
POST
```

---

## Request Headers

JSON requests should include:

```http
Content-Type: application/json
```

For authenticated deployments, the appropriate authorization header can also be supplied.

---

## Basic Request

A simple request can look like:

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ]
}
```

The request tells the API to:

- Use the `Query` controller.
- Execute the `select` action.
- Query `CustomerTable`.
- Return `Cust_Name` and `Phone`.

---

## Request Processing

The API processes the request roughly as follows:

```text
HTTP POST
    |
    v
api/index.php
    |
    v
Read JSON Body
    |
    v
Resolve Controller
    |
    v
Resolve Action
    |
    v
Validate Request
    |
    v
Build SQL
    |
    v
Execute Query
    |
    v
Create Response
    |
    v
Return JSON
```

---

## Successful Response

A successful request returns a JSON response containing the query result.

Example:

```json
{
  "success": true,
  "message": "Data Loaded Successfully",
  "data": [
    {
      "Cust_Name": "ABC Traders",
      "Phone": "9876543210"
    }
  ]
}
```

The exact response structure depends on the current controller and response implementation.

---

## Error Response

When the request cannot be processed, the API returns an error response.

Example:

```json
{
  "success": false,
  "message": "Invalid JSON Request"
}
```

Common request-level errors include:

```text
Controller Missing
Action Missing
Controller Not Found
Action Not Found
Invalid JSON Request
```

Database and query errors are handled by the backend error-handling layer.

---

## Invalid JSON

If the request body is not valid JSON, the API should reject the request instead of attempting to build a query from invalid data.

Example:

```text
{ invalid json
```

Possible response:

```json
{
  "success": false,
  "message": "Invalid JSON Request"
}
```

---

## Controller and Action

The request identifies the operation through:

```json
{
  "controller": "Query",
  "action": "select"
}
```

The controller determines which backend component handles the request.

The action determines what operation should be performed.

This keeps the API entry point separate from the actual query implementation.

---

## Query Request

A query request can contain the database information required to build the SQL operation.

Example:

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ],
  "where": [
    {
      "left": {
        "column": "Status"
      },
      "operator": "=",
      "right": "Active"
    }
  ]
}
```

The complete request structure is documented in:

[JSON Request Reference](JSON-Request-Reference.md)

---

## Query Operations

The query layer supports request components such as:

- Column selection
- Column aliases
- Filtering
- JOINs
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- SQL functions
- Expressions

Examples are available in:

[Query Examples](Query-Examples.md)

---

## CORS

CORS is configured by the API entry point.

The allowed origins are maintained by the application configuration.

Example:

```php
$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:3000"
];
```

For a production deployment, only trusted frontend origins should be allowed.

Avoid using a wildcard origin unless the deployment specifically requires it.

---

## OPTIONS Requests

Browsers can send an `OPTIONS` request before a cross-origin request.

The API handles this as a CORS preflight request.

Conceptually:

```text
Browser
   |
   | OPTIONS
   v
API
   |
   | CORS response
   v
Browser
   |
   | POST JSON
   v
API
```

---

## Testing With curl

### Windows

```bat
curl -X POST http://localhost:8000/api/index.php ^
  -H "Content-Type: application/json" ^
  -d "{\"controller\":\"Query\",\"action\":\"select\",\"table\":\"CustomerTable\",\"columns\":[\"Cust_Name\"]}"
```

### Linux / macOS

```bash
curl -X POST http://localhost:8000/api/index.php \
  -H "Content-Type: application/json" \
  -d '{"controller":"Query","action":"select","table":"CustomerTable","columns":["Cust_Name"]}'
```

---

## Testing With PowerShell

The API can also be tested from PowerShell:

```powershell
$body = @{
    controller = "Query"
    action     = "select"
    table      = "CustomerTable"
    columns    = @("Cust_Name")
} | ConvertTo-Json

Invoke-RestMethod `
    -Uri "http://localhost:8000/api/index.php" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body
```

---

## Database Connection

Database credentials are not part of normal query requests.

The API reads database configuration from:

```text
database/config/database.json
```

The database connection is handled by the database layer.

See [Database Configuration](Database-Configuration.md).

---

## Authentication

Authentication is not currently described as part of the core query request format.

Authentication and authorization are planned for a future version.

See [Roadmap](Roadmap.md).

---

## API Port

When using the Windows startup script, the API starts from port `8000`.

If the port is already in use, the script checks the next available port up to `8100`.

For example:

```text
Port 8000 -> busy
Port 8001 -> busy
Port 8002 -> available

API:
http://localhost:8002
```

---

## API Directory

The API entry point is located under:

```text
api/
└── index.php
```

The PHP built-in server serves this directory when started by the Windows launcher.

---

## Production Notes

For production deployments:

- Use HTTPS.
- Restrict CORS origins.
- Protect database credentials.
- Do not expose SQL Server directly to the Internet.
- Use appropriate authentication before exposing the API publicly.
- Keep PHP and ODBC components updated.
- Monitor application logs.
- Maintain database backups.

---

## Related Documentation

- [Introduction](Introduction.md)
- [Architecture](Architecture.md)
- [JSON Request Reference](JSON-Request-Reference.md)
- [Query Examples](Query-Examples.md)
- [Database Configuration](Database-Configuration.md)
- [Hosting](Hosting.md)