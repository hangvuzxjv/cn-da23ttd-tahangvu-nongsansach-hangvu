<?php

declare(strict_types=1);

abstract class Model
{
    protected function database(): PDO
    {
        return Database::connection();
    }
}
