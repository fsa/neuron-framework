<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\SQL\{EntityInterface, KeyPairInterface};

class Scopes implements EntityInterface, KeyPairInterface
{
    public $name;
    public $description;

    public static function getTableName(): string
    {
        return 'user_scopes';
    }

    public static function getIndexRow(): string
    {
        return 'name';
    }

    public static function getNameRow(): string
    {
        return 'description';
    }

    public function getProperties(): array
    {
        return get_object_vars($this);
    }
}
