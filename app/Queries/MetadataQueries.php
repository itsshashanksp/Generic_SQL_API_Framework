<?php

class MetadataQueries
{
    public static function tables()
    {
        return "
            SELECT
                TABLE_NAME
            FROM
                INFORMATION_SCHEMA.TABLES
            WHERE
                TABLE_TYPE='BASE TABLE'
            ORDER BY
                TABLE_NAME
        ";
    }
}