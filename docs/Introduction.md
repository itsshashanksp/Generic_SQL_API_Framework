# Introduction

## Overview

Generic SQL API Framework is a lightweight, modular, and configurable PHP framework designed to simplify the development of SQL-based APIs for Microsoft SQL Server.

Instead of writing custom SQL queries and API endpoints for every report or module, the framework provides a reusable backend engine that accepts structured JSON requests, validates them, dynamically generates SQL queries, executes them securely, and returns standardized JSON responses.

---

# Why This Framework?

Business applications often contain a large number of reports and database operations.

A traditional approach usually requires developers to:

- Write a SQL query.
- Create an API endpoint.
- Validate request parameters.
- Add database logic.
- Maintain duplicate code.
- Modify the API whenever the report changes.

As the number of reports grows, maintaining these APIs becomes increasingly difficult.

Generic SQL API Framework addresses this problem by providing a configurable query engine.

Instead of creating a separate API for every report, applications can send structured JSON requests to the same framework.

---

# Objectives

The main objectives are:

- Reduce repetitive backend development.
- Provide a reusable SQL API engine.
- Standardize API requests and responses.
- Centralize database access.
- Provide secure parameterized SQL execution.
- Separate application configuration from framework code.
- Support multiple frontend technologies.
- Provide an extensible database architecture.

---

# Design Principles

## Modularity

Framework components are separated according to responsibility.

## Reusability

The framework can be reused across different applications and projects.

## Security

Requests are validated before SQL execution and database operations use parameterized queries.

## Flexibility

Applications communicate using structured JSON rather than requiring hardcoded API endpoints for every report.

## Extensibility

The database layer uses provider and driver abstractions so additional database systems can be introduced later.

---

# Current Database Scope

The current implementation focuses on:

Microsoft SQL Server

SQL Server communication is handled through ODBC.

The framework supports automatic SQL Server ODBC driver detection and multiple supported driver generations.

---

# Windows Distribution

The project provides a prebuilt PHP runtime for Windows.

This allows a Windows user to run the framework without manually installing PHP.

The bundled runtime is started using:

start-windows.bat

Users who already have a PHP environment can continue using their existing hosting setup.

Detailed deployment instructions are documented separately in:

Hosting.md

---

# Use Cases

The framework can be used for:

- Business Management Systems
- ERP Systems
- POS Systems
- Inventory Management
- CRM Systems
- Courier and Logistics Systems
- Reporting Systems
- MIS Applications
- Dashboard Applications
- Data Analytics Applications
- Internal Business Applications
- Administrative Portals

---

# Benefits

The framework provides:

- Faster API development
- Reduced duplicate code
- Centralized database access
- Consistent API responses
- Secure database execution
- Configurable database connections
- Frontend independence
- Easier maintenance
- Extensible architecture

---

# Project Vision

The long-term goal is to provide a reusable backend framework for database-driven applications.

The framework is designed to grow from a SQL reporting/query engine into a broader platform supporting additional database operations, authentication, reporting, exports, dashboards, and other enterprise capabilities.

---

# Continue Documentation

After understanding the purpose of the framework, continue with:

Architecture.md

Architecture explains how the framework components work together.

For the HTTP interface, continue to:

API.md