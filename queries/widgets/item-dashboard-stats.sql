SELECT
    COUNT(Item_Code) AS TotalItems,
    MIN(Sale_Rate) AS MinimumSP,
    MAX(Sale_Rate) AS MaximumSP,
    SUM(Sale_Rate) AS TotalValue
FROM ItemMasterTable
/*__RUNTIME_FILTERS__*/
