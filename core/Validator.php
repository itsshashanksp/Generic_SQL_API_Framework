<?php

require_once __DIR__ . '/Response.php';

class Validator
{
    /**
     * Validate Required Fields
     */
    public static function required(array $data, array $fields): void
    {
        foreach ($fields as $field) {

            if (!isset($data[$field])) {

                Response::error(
                    "{$field} is required",
                    400
                );

            }

            if (is_string($data[$field]) && trim($data[$field]) === '') {

                Response::error(
                    "{$field} cannot be empty",
                    400
                );

            }
        }
    }
}