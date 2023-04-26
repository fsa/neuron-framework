<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\SQL\{EntityInterface, KeyPairInterface};

class Groups implements EntityInterface, KeyPairInterface
{
    public $name;
    public $scope;
    public $description;

    public static function getTableName(): string
    {
        return 'user_groups';
    }

    public static function getIndexRow(): string
    {
        return 'name';
    }

    public static function getNameRow(): string
    {
        return 'description';
    }

    public function __construct()
    {
        if (isset($this->scope)) {
            $this->scope = explode(',', trim($this->scope, '{}'));
        }
    }

    public function getProperties(): array
    {
        $properties = get_object_vars($this);
        if (!is_null($properties['scope'])) {
            $properties['scope'] = '{' . join(',', $properties['scope']) . '}';
        }
        return $properties;
    }
}
