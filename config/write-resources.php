<?php

return [
    'crud-test' => [
        'schema' => 'dbo',
        'table' => 'ApiCrudTest',

        'actions' => [
            'insert',
            'update',
            'delete',
            'upsert',
        ],

        'columns' => [
            'CustomerCode',
            'Name',
            'Email',
            'Age',
            'Status',
        ],

        'filterColumns' => [
            'Id',
            'CustomerCode',
            'Name',
            'Email',
            'Age',
            'Status',
        ],

        'keys' => [
            'CustomerCode',
        ],

        'identityColumn' => 'Id',
    ],
];