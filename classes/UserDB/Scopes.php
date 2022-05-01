<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\SQLEntityInterface;

class Scopes implements SQLEntityInterface
{
    const TABLE_NAME = 'user_scopes';
    const UID = 'name';
    public $name;
    public $description;

    public function getProperties(): array
    {
        return get_object_vars($this);
    }
}
