# Hosting & Deployment Guide

## Overview

The Generic SQL API Framework is a PHP-based backend framework designed to run between client applications and Microsoft SQL Server. It exposes HTTP API endpoints that allow applications to execute SQL operations securely using JSON requests.

The framework can be deployed on any web server capable of running PHP and communicating with Microsoft SQL Server.

---

# Deployment Architecture

A typical deployment consists of three layers.

```text
                Client Applications
        (React, Flutter, Mobile, Desktop)
                    │
             HTTP / HTTPS
                    │
                    ▼
      Generic SQL API Framework (PHP)
                    │
          Database Engine
                    │
                    ▼
          Microsoft SQL Server
```

The framework acts as the only component allowed to communicate directly with SQL Server.

---

# Development Environment

During development all components usually run on the same machine.

```text
Frontend
http://localhost:5173

        │

        ▼

Generic SQL API Framework
http://localhost/generic-sql-api-framework/backend/api/

        │

        ▼

Microsoft SQL Server
localhost\SQLEXPRESS
```

This setup simplifies development and debugging.

---

# Production Deployment

A production environment should separate each component.

```text
Internet
    │
    ▼
https://api.company.com
    │
    ▼
Apache / IIS / Nginx
    │
    ▼
Generic SQL API Framework
    │
    ▼
Private SQL Server
```

Only the web server should communicate with SQL Server.

The database server should never be exposed directly to the Internet.

---

# Supported Web Servers

The framework supports:

- Apache HTTP Server
- Microsoft IIS
- Nginx + PHP-FPM
- Docker
- XAMPP
- WAMP
- LAMP

---

# Deployment Directory

Example using Apache or XAMPP

```text
htdocs/
└── generic-sql-api-framework/
    ├── backend/
    ├── docs/
    ├── README.md
    └── LICENSE
```

Linux Example

```text
/var/www/html/generic-sql-api-framework/
```

---

# Deployment Checklist

Before deploying ensure that:

- PHP 8 or later is installed
- Microsoft SQL Server is available
- Microsoft ODBC Driver is installed
- Required PHP extensions are enabled
- Database configuration is completed
- Web server is configured
- Folder permissions are correct

---

# Configuring the Database

Database configuration is documented separately.

See:

```
Database-Configuration.md
```

---

# Frontend Integration

Any frontend capable of making HTTP requests can consume the framework.

Supported technologies include:

- React
- Angular
- Vue.js
- Flutter
- React Native
- Android
- iOS
- Electron
- PHP
- Python
- Java
- .NET
- Node.js
- Desktop Applications

No framework-specific SDK is required.

---

# Communication Flow

```text
Frontend

↓

HTTP POST Request

↓

Generic SQL API Framework

↓

SQL Server

↓

JSON Response

↓

Frontend
```

The frontend never communicates directly with the database.

---

# Security Recommendations

Production deployments should follow these recommendations:

- Enable HTTPS
- Keep SQL Server on a private network
- Never expose SQL Server publicly
- Protect configuration files
- Use Windows Authentication when appropriate
- Keep PHP and ODBC drivers updated
- Enable centralized logging
- Perform regular database backups

---

# Performance Recommendations

For better performance:

- Use the latest ODBC Driver
- Optimize SQL queries
- Use indexes appropriately
- Monitor execution time
- Enable query logging
- Use connection pooling if supported

---

# Updating the Framework

To upgrade the framework:

1. Back up the existing project.
2. Replace framework files.
3. Preserve `database.json`.
4. Review the CHANGELOG.
5. Test the application.

---

# Troubleshooting

## API Not Reachable

- Verify the web server is running.
- Confirm the project directory is correct.
- Check firewall rules.

---

## Database Connection Failed

Refer to:

```
Database-Configuration.md
```

---

## HTTP 500 Error

- Review PHP error logs.
- Check web server logs.
- Verify file permissions.

---

## ODBC Driver Missing

Install:

- Microsoft ODBC Driver 17
- Microsoft ODBC Driver 18

---

# Related Documentation

- Introduction.md
- Architecture.md
- API.md
- JSON-Request-Reference.md
- Database-Configuration.md

---

# Summary

The Generic SQL API Framework can be deployed on any standard PHP hosting environment. By acting as the middleware between client applications and Microsoft SQL Server, the framework provides a secure, scalable, and maintainable architecture suitable for modern business applications. Proper deployment, secure configuration, and regular maintenance ensure reliable operation in both development and production environments.