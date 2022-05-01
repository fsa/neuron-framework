<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\SQLEntityInterface;

class Groups implements SQLEntityInterface
{
    const TABLE_NAME = 'user_groups';
    const UID = 'name';
    public $name;
    public $scope;
    public $description;

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
