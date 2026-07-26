<?php

interface DatabaseDriverInterface
{
    public function connect();

    public function disconnect();

    public function query($sql);

    public function execute($sql);

    public function fetch($result);

    public function beginTransaction();

    public function commit();

    public function rollback();
}