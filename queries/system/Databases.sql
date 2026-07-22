SELECT
    name AS DatabaseName
FROM sys.databases
WHERE database_id > 4
ORDER BY name;
