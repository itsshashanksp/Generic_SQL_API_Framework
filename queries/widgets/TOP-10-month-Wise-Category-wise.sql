WITH SalesData AS
(
    SELECT
        SUBSTRING(CONVERT(VARCHAR, BILL_DATE), 5, 2) + '/' +
        SUBSTRING(CONVERT(VARCHAR, BILL_DATE), 1, 4) AS Month,
        CAT.Cat_Desc AS Category,

        ROUND(
            SUM(
                CASE
                    WHEN BIL.Status = 'S' THEN BIL.Item_Value
                    ELSE -1 * BIL.Item_Value
                END
            ) / 100000, 2
        ) AS Sales

    FROM BillDetTable BIL, CategoryTable CAT

    WHERE
        BIL.Cat_Code = CAT.Cat_Code
        AND Bill_Date BETWEEN
            CONVERT(NUMERIC,
                CONVERT(VARCHAR, YEAR(GETDATE()) - 5) + '04' + '01'
            )
            AND
            CONVERT(NUMERIC,
                CONVERT(VARCHAR, YEAR(GETDATE()) - 5 + 1) + '03' + '31'
            )

    GROUP BY
        SUBSTRING(CONVERT(VARCHAR, BILL_DATE), 5, 2) + '/' +
        SUBSTRING(CONVERT(VARCHAR, BILL_DATE), 1, 4),
        CAT.Cat_Desc
),
RankedData AS
(
    SELECT
        Month,
        Category,
        Sales,
        DENSE_RANK() OVER
        (
            PARTITION BY Month
            ORDER BY Sales DESC
        ) AS RowNo
    FROM SalesData
)

SELECT
    Month,
    Category,
    Sales
FROM RankedData
WHERE RowNo <= 10
ORDER BY
    Month,
    Sales DESC;