# JSON Query function reference

Functions in this document are the complete public function allowlist for JSON
Query Mode. Function names are case-insensitive during validation and normalized
for SQL generation. Each entry is a `fields[]` object; add an identifier `alias`
for a stable frontend column name.

`F` below means a column identifier string. Literal options are validated by
function-specific rules; referenced fields are checked against metadata.

## Aggregates

| Function | Public JSON field object | SQL concept / restriction |
|---|---|---|
| COUNT | `{"function":"COUNT","field":"*","alias":"Rows"}` | `COUNT(*)`; a field is also accepted. |
| SUM | `{"function":"SUM","field":"Amount","alias":"Total"}` | `SUM([Amount])` |
| AVG | `{"function":"AVG","field":"Amount","alias":"Average"}` | `AVG([Amount])` |
| MIN | `{"function":"MIN","field":"Amount","alias":"Minimum"}` | `MIN([Amount])` |
| MAX | `{"function":"MAX","field":"Amount","alias":"Maximum"}` | `MAX([Amount])` |
| STRING_AGG | `{"function":"STRING_AGG","field":"Name","separator":", ","sort":[{"field":"Name","direction":"ASC"}],"alias":"Names"}` | Separator required; sort optional. |

## String functions

| Function(s) | Required JSON beyond `function` | Example |
|---|---|---|
| UPPER, LOWER, LTRIM, RTRIM, TRIM, LEN | `field` | `{"function":"UPPER","field":"Name","alias":"UpperName"}` |
| CONCAT | `fields` with at least two identifiers | `{"function":"CONCAT","fields":["FirstName","LastName"],"alias":"FullName"}` |
| LEFT, RIGHT | `field`, positive integer `length` | `{"function":"LEFT","field":"Code","length":3,"alias":"Prefix"}` |
| SUBSTRING | `field`, positive `start`, non-negative `length` | `{"function":"SUBSTRING","field":"Code","start":2,"length":4,"alias":"Part"}` |
| REPLACE | `field`, `search`, `replace` | `{"function":"REPLACE","field":"Name","search":"-","replace":" ","alias":"CleanName"}` |
| CHARINDEX | `field`, `search` | `{"function":"CHARINDEX","field":"Email","search":"@","alias":"AtPosition"}` |
| PATINDEX | `field`, `pattern` | `{"function":"PATINDEX","field":"Code","pattern":"%[0-9]%","alias":"DigitPosition"}` |
| FORMAT | `field`, `format`; optional integer `style` | `{"function":"FORMAT","field":"Amount","format":"N2","alias":"DisplayAmount"}` |

`CONCAT.fields` and COALESCE fields are identifiers, not arbitrary nested
expressions. String/search/format/default values are controlled values rendered
by the builder; they are not raw SQL fragments.

## Null functions

| Function | Public JSON field object |
|---|---|
| COALESCE | `{"function":"COALESCE","fields":["PreferredName","Name"],"default":"Unknown","alias":"DisplayName"}` |
| ISNULL | `{"function":"ISNULL","field":"Name","default":"Unknown","alias":"DisplayName"}` |
| NULLIF | `{"function":"NULLIF","field":"Status","value":"Unknown","alias":"KnownStatus"}` |

## Conversion

| Function | Public JSON field object | Restriction |
|---|---|---|
| CAST | `{"function":"CAST","field":"Amount","datatype":"decimal(10,2)","alias":"DecimalAmount"}` | Simple allowlisted type syntax only. |
| CONVERT | `{"function":"CONVERT","field":"BillDate","datatype":"date","style":112,"alias":"DateValue"}` | Optional style is an integer. |

`datatype` is a simple type name optionally followed by numeric size/precision,
such as `date`, `varchar(50)`, or `decimal(10,2)`. It is not free-form SQL.

## Date and time

| Function | Public JSON field object / required properties |
|---|---|
| YEAR, MONTH, DAY | `{"function":"YEAR","field":"BillDate","alias":"BillYear"}` |
| DATEPART | `{"function":"DATEPART","part":"MONTH","field":"BillDate","alias":"BillMonth"}` |
| DATENAME | `{"function":"DATENAME","part":"MONTH","field":"BillDate","alias":"MonthName"}` |
| GETDATE | `{"function":"GETDATE","alias":"Now"}` |
| SYSDATETIME | `{"function":"SYSDATETIME","alias":"NowPrecise"}` |
| CURRENT_TIMESTAMP | `{"function":"CURRENT_TIMESTAMP","alias":"Now"}` |
| DATEADD | `{"function":"DATEADD","datepart":"DAY","number":7,"field":"BillDate","alias":"DueDate"}` |
| DATEDIFF | `{"function":"DATEDIFF","datepart":"DAY","start":{"field":"BillDate","style":112},"end":{"function":"GETDATE"},"alias":"AgeDays"}` |
| EOMONTH | `{"function":"EOMONTH","start":{"field":"BillDate"},"month":1,"alias":"NextMonthEnd"}` |
| ISDATE | `{"function":"ISDATE","field":"DateText","style":112,"alias":"IsValidDate"}` |
| DATEFROMPARTS | `{"function":"DATEFROMPARTS","year":2026,"month":9,"day":12,"alias":"DateValue"}` |
| DATETIMEFROMPARTS | `{"function":"DATETIMEFROMPARTS","year":2026,"month":9,"day":12,"hour":10,"minute":30,"second":0,"millisecond":0,"alias":"DateTimeValue"}` |

YEAR/MONTH/DAY and DATEPART/DATENAME treat the field as an integer `YYYYMMDD`
value and convert using SQL Server style 112. DATEADD accepts YEAR, MONTH, DAY,
HOUR, MINUTE, or SECOND and an optional integer `style`. DATEPART, DATENAME, and
DATEDIFF accept YEAR, QUARTER, MONTH, DAYOFYEAR, DAY, WEEK, WEEKDAY, HOUR,
MINUTE, SECOND, or MILLISECOND. A DATEDIFF/EOMONTH endpoint is either
`{"field":"DateField"}` (optional integer style) or `{"function":"GETDATE"}`.

`TIMEFROMPARTS` is in the internal function/builder list, but its required
`fractions` property is rejected by the public field-property validator. It is
therefore **internal support only and not currently available through the public
JSON contract**.

## Conditional

| Function | Public JSON field object |
|---|---|
| IIF | `{"function":"IIF","condition":{"left":"Amount","operator":">","right":0},"true":"Credit","false":"Zero","alias":"AmountType"}` |
| CHOOSE | `{"function":"CHOOSE","index":2,"values":["Low","Medium","High"],"alias":"Band"}` |

IIF condition operands are numbers or identifiers and its operator is a supported
comparison. CHOOSE index is positive and `values` contains at least two values.
The non-function CASE field shape is documented in [JSON Query Mode](Query-Mode.md).

## Mathematics

| Function(s) | Required JSON beyond `function` | Example |
|---|---|---|
| ABS, CEILING, FLOOR, SQRT, EXP, LOG | `field` | `{"function":"ABS","field":"Variance","alias":"AbsoluteVariance"}` |
| ROUND | `field`; optional integer `precision` default 0 | `{"function":"ROUND","field":"Amount","precision":2,"alias":"Rounded"}` |
| POWER | `field`, numeric `power` | `{"function":"POWER","field":"Value","power":2,"alias":"Squared"}` |

## Window functions

All window functions require a non-empty `sort` array using logical fields and
ASC/DESC. Numeric ordering and `partitionBy` are not public.

| Function | Public JSON field object |
|---|---|
| ROW_NUMBER | `{"function":"ROW_NUMBER","sort":[{"field":"BillDate","direction":"DESC"}],"alias":"RowNo"}` |
| RANK | `{"function":"RANK","sort":[{"field":"Amount","direction":"DESC"}],"alias":"AmountRank"}` |
| DENSE_RANK | `{"function":"DENSE_RANK","sort":[{"field":"Amount","direction":"DESC"}],"alias":"DenseAmountRank"}` |
| NTILE | `{"function":"NTILE","buckets":4,"sort":[{"field":"Amount"}],"alias":"Quartile"}` |
| LAG | `{"function":"LAG","field":"Amount","offset":1,"default":0,"sort":[{"field":"BillDate"}],"alias":"Previous"}` |
| LEAD | `{"function":"LEAD","field":"Amount","offset":1,"default":0,"sort":[{"field":"BillDate"}],"alias":"Next"}` |
| FIRST_VALUE | `{"function":"FIRST_VALUE","field":"Amount","sort":[{"field":"BillDate"}],"alias":"FirstAmount"}` |
| LAST_VALUE | `{"function":"LAST_VALUE","field":"Amount","sort":[{"field":"BillDate"}],"alias":"LastAmount"}` |

NTILE buckets and LAG/LEAD offsets are positive integers. Offset defaults to 1;
the default result value is optional. LAST_VALUE uses a full window frame.

## Complete boundary

The public list is: COUNT, SUM, AVG, MIN, MAX, STRING_AGG, UPPER, LOWER, LTRIM,
RTRIM, TRIM, LEN, CONCAT, LEFT, RIGHT, SUBSTRING, REPLACE, CHARINDEX, PATINDEX,
FORMAT, COALESCE, ISNULL, NULLIF, CAST, CONVERT, YEAR, MONTH, DAY, DATEPART,
DATENAME, GETDATE, DATEADD, DATEDIFF, EOMONTH, ISDATE, DATEFROMPARTS,
DATETIMEFROMPARTS, SYSDATETIME, CURRENT_TIMESTAMP, IIF, CHOOSE, ABS, ROUND,
CEILING, FLOOR, POWER, SQRT, EXP, LOG, ROW_NUMBER, RANK, DENSE_RANK, NTILE, LAG,
LEAD, FIRST_VALUE, and LAST_VALUE. TIMEFROMPARTS is the unusable internal/public
mismatch described above. Other SQL Server functions require SQL Resource Mode.
