# Database Configuration

The framework uses a JSON file to connect to the database.

**Location**

```
backend/database/config/database.json
```

---

## Sample Configuration

```json
{
    "provider": "sqlserver",
    "driver": "auto",

    "server": "localhost\\SQLEXPRESS",
    "database": "IBMS",

    "authentication": "sql",

    "username": "sa",
    "password": "123456789",

    "port": 1433,

    "options": {
        "encrypt": false,
        "trustServerCertificate": true
    }
}
```

---

## Configuration

| Key | Description |
|------|-------------|
| `provider` | Database provider (`sqlserver`) |
| `driver` | ODBC Driver name or `auto` |
| `server` | SQL Server name or IP |
| `database` | Database name |
| `authentication` | `sql` or `windows` |
| `username` | SQL username |
| `password` | SQL password |
| `port` | Database port |
| `encrypt` | Enable/Disable encryption |
| `trustServerCertificate` | Trust SQL Server certificate |

---

## Driver

Automatic detection:

```json
"driver": "auto"
```

Manual selection:

```json
"driver": "ODBC Driver 18 for SQL Server"
```

---

## Server Examples

Local SQL Express

```json
"server": "localhost\\SQLEXPRESS"
```

Remote SQL Server

```json
"server": "192.168.1.100"
```

---

## Authentication

### SQL Authentication

```json
"authentication": "sql"
```

### Windows Authentication

```json
"authentication": "windows"
```

---

## Common Errors

**Database not found**
- Check the database name.

**Login failed**
- Verify username and password.

**Driver not found**
- Install Microsoft ODBC Driver 17 or 18.

**Cannot connect**
- Verify SQL Server is running and accessible.