<?php

abstract class Middleware
{
    abstract public function handle(array $request): void;
}
