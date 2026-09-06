<?php

class WindowFunctionBuilder
{
    private OrderByBuilder $orderByBuilder;
    private $valueBuilder;

    public function __construct(OrderByBuilder $orderByBuilder, callable $valueBuilder)
    {
        $this->orderByBuilder = $orderByBuilder;
        $this->valueBuilder = $valueBuilder;
    }

    public function build(
        string $function,
        array $column,
        array $request,
        string $alias,
        ?string $resolvedColumn
    ): ?string {
        $supported = [
            'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE',
            'LAG', 'LEAD', 'FIRST_VALUE', 'LAST_VALUE'
        ];
        if (!in_array($function, $supported, true)) {
            return null;
        }

        $orders = $this->orderByBuilder->buildItems(
            $column['orderBy'],
            $request,
            false,
            false,
            true
        );
        $orderSql = implode(', ', $orders);

        if (in_array($function, ['ROW_NUMBER', 'RANK', 'DENSE_RANK'], true)) {
            return "{$function}() OVER (ORDER BY {$orderSql}) AS [{$alias}]";
        }
        if ($function === 'NTILE') {
            return 'NTILE(' . (int)$column['buckets']
                . ") OVER (ORDER BY {$orderSql}) AS [{$alias}]";
        }
        if ($function === 'FIRST_VALUE') {
            return "FIRST_VALUE({$resolvedColumn}) OVER (ORDER BY {$orderSql}) AS [{$alias}]";
        }
        if ($function === 'LAST_VALUE') {
            return "LAST_VALUE({$resolvedColumn}) OVER (ORDER BY {$orderSql} ROWS BETWEEN "
                . "UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS [{$alias}]";
        }

        $sql = $function . '(' . $resolvedColumn . ', ' . (int)($column['offset'] ?? 1);
        if (array_key_exists('default', $column)) {
            $sql .= ', ' . ($this->valueBuilder)($column['default']);
        }
        return $sql . ") OVER (ORDER BY {$orderSql}) AS [{$alias}]";
    }
}
