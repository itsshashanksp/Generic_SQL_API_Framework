SELECT
    GETDATE() AS CurrentDateTime,
    DB_NAME() AS DatabaseName,
    @@SERVERNAME AS ServerName;
