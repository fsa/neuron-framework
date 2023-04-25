<?php

namespace FSA\Neuron;

use App\Core;
use FSA\Neuron\Database\Pgsql;

class User implements UserInterface
{
    public $uuid;
    public $login;
    public $name;
    public $email;
    public $scope;

    function __construct(private Database\Pgsql $pgsql)
    {
    }

    public function login($login, $password): bool
    {
        $user = $this->pgsql->executeQuery('SELECT uuid, password_hash FROM users WHERE (login=? OR (email IS NOT NULL AND email=?)) AND NOT COALESCE(disabled, false)', [$login, $login])->fetchObject();
        if (!($user and password_verify($password, $user->password_hash))) {
            return false;
        }
        $this->refresh(['uuid' => $user->uuid]);
        return true;
    }

    function refresh(array $args): bool
    {
        if (!isset($args['uuid'])) {
            return false;
        }
        $entity = $this->pgsql->executeQuery("WITH usr AS (SELECT * FROM users u WHERE uuid=? AND NOT COALESCE(disabled, false)), groups_set AS (SELECT uuid, unnest(groups) AS gid FROM usr), gscope_set AS (SELECT uuid, unnest(ug.scope) AS gscope FROM groups_set g LEFT JOIN user_groups ug ON g.gid=ug.name GROUP BY uuid, gscope), gscope AS (SELECT uuid, array_agg(gscope) AS group_scope FROM gscope_set GROUP BY uuid) SELECT json_build_object('uuid',uuid, 'login',login, 'name', name, 'email', email, 'scope', to_json(group_scope||scope)) FROM usr LEFT JOIN gscope USING (uuid)", [$args['uuid']])->fetchColumn();
        if (!$entity) {
            return false;
        }
        foreach (json_decode($entity) as $key => $value) {
            $this->$key = $value;
        }
        return true;
    }

    function getProperties(): array
    {
        return ['uuid' => $this->uuid];
    }

    function getId()
    {
        return $this->uuid;
    }

    function getLogin(): string
    {
        return $this->login;
    }

    function getName(): string
    {
        return $this->name;
    }

    function getEmail(): string
    {
        return $this->email;
    }

    function getScope(): ?array
    {
        return $this->scope;
    }

    public static function validate(array $properties): self
    {
        $user = new self(Core::container()->get(Pgsql::class));
        return $user->refresh($properties) ? $user : null;
    }
}
