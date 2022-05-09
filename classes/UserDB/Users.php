<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\SQL\EntityInterface;

class Users implements EntityInterface
{
    public $uuid;
    public $login;
    public $password_hash;
    public $name;
    public $email;
    public $scope;
    public $groups;
    public $disabled;

    private $new_password_hash;

    public static function getTableName(): string
    {
        return 'users';
    }

    public static function getIndexRow(): string
    {
        return 'uuid';
    }

    public function __construct()
    {
        if (isset($this->scope)) {
            $this->scope = explode(',', trim($this->scope, '{}'));
        }
        if (isset($this->groups)) {
            $this->groups = explode(',', trim($this->groups, '{}'));
        }
    }

    public function setPassword($password)
    {
        $this->new_password_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    public function getProperties(): array
    {
        $properties = get_object_vars($this);
        unset($properties['new_password_hash']);
        if ($this->new_password_hash) {
            $properties['password_hash'] = $this->new_password_hash;
        } else {
            unset($properties['password_hash']);
        }
        if (!is_null($properties['scope'])) {
            $properties['scope'] = '{' . join(',', $properties['scope']) . '}';
        }
        if (!is_null($properties['groups'])) {
            $properties['groups'] = '{' . join(',', $properties['groups']) . '}';
        }
        $properties['disabled'] = $properties['disabled'] ? 't' : 'f';
        return $properties;
    }

    public function memberOfScope($scope)
    {
        return array_search($scope, $this->scope) !== false;
    }

    public function memberOfGroup($group)
    {
        return array_search($group, $this->groups) !== false;
    }
}
