<?php

return [
    'item' => [
        'file' => QUERY_PATH . '/reports/item.sql',
        'columns' => ['Item_Code', 'Item_Desc', 'Item_MRP'],
        'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']],
    ],
    'customer' => [
        'file' => QUERY_PATH . '/reports/customer.sql',
        'columns' => ['Cust_Name', 'TotalCustomers', 'MinimumBill', 'MaximumBill',],
        'defaultSort' => [['field' => 'Cust_Name', 'direction' => 'ASC']],
    ],
    'item-dashboard-stats' => [
        'file' => QUERY_PATH . '/widgets/item-dashboard-stats.sql',
        'columns' => ['TotalItems', 'MinimumSP', 'MaximumSP', 'TotalValue'],
        'filterColumns' => ['Item_Desc', 'Std_Vat'],
        'filterPlacement' => 'source',
        'defaultSort' => [['field' => 'TotalItems', 'direction' => 'ASC']],
    ],
    'item-dashboard-table' => [
        'file' => QUERY_PATH . '/widgets/item-dashboard-table.sql',
        'columns' => ['Item_Code', 'Item_Desc', 'Sale_Rate', 'Item_MRP', 'Std_Vat'],
        'filterColumns' => ['Item_Desc', 'Std_Vat'],
        'defaultSort' => [['field' => 'Item_Code', 'direction' => 'ASC']],
    ],
    'bill-total-sales' => [
        'file' => QUERY_PATH . '/widgets/bill-total-sales.sql',
        'columns' => ['TotalSales'],
        'defaultSort' => [['field' => 'TotalSales', 'direction' => 'ASC']],
    ],
    'bill-total-purchases' => [
        'file' => QUERY_PATH . '/widgets/bill-total-purchases.sql',
        'columns' => ['TotalPurchases'],
        'defaultSort' => [['field' => 'TotalPurchases', 'direction' => 'ASC']],
    ],
    'bill-sales-month-wise' => [
        'file' => QUERY_PATH . '/widgets/bill-sales-month-wise.sql',
        'columns' => ['Month', 'Sales'],
        'defaultSort' => [['field' => 'Month', 'direction' => 'DESC']],
    ],
    'bill-purchases-month-wise' => [
        'file' => QUERY_PATH . '/widgets/bill-purchases-month-wise.sql',
        'columns' => ['Month', 'Purchases'],
        'defaultSort' => [['field' => 'Month', 'direction' => 'DESC']],
    ],
    'bill-top-10-categories' => [
        'file' => QUERY_PATH . '/widgets/bill-top-10-categories.sql',
        'columns' => ['Category', 'Sales'],
        'defaultSort' => [['field' => 'Sales', 'direction' => 'DESC']],
    ],
    'bill-category-sales-month-wise' => [
        'file' => QUERY_PATH . '/widgets/bill-category-sales-month-wise.sql',
        'columns' => ['Month', 'Category', 'Sales'],
        'defaultSort' => [
            ['field' => 'Month', 'direction' => 'ASC'],
            ['field' => 'Category', 'direction' => 'ASC'],
        ],
    ],
];
