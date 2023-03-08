<?php

namespace FSA\Neuron;

use PDO;
use App\Core;

class User implements UserInterface
{

    public $uuid;
    public $login;
    public $name;
    public $email;
    public $scope;
    private $pdo;

    function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login($login, $password): bool
    {
        $s = $this->pdo->prepare('SELECT uuid, password_hash FROM users WHERE (login=? OR (email IS NOT NULL AND email=?)) AND NOT COALESCE(disabled, false)');
        $s->execute([$login, $login]);
        $user = $s->fetchObject();
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
        $s = $this->pdo->prepare("WITH usr AS (SELECT * FROM users u WHERE uuid=? AND NOT COALESCE(disabled, false)), groups_set AS (SELECT uuid, unnest(groups) AS gid FROM usr), gscope_set AS (SELECT uuid, unnest(ug.scope) AS gscope FROM groups_set g LEFT JOIN user_groups ug ON g.gid=ug.name GROUP BY uuid, gscope), gscope AS (SELECT uuid, array_agg(gscope) AS group_scope FROM gscope_set GROUP BY uuid) SELECT json_build_object('uuid',uuid, 'login',login, 'name', name, 'email', email, 'scope', to_json(group_scope||scope)) FROM usr LEFT JOIN gscope USING (uuid)");
        $s->execute([$args['uuid']]);
        $entity = $s->fetchColumn();
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
        $user = new self(Core::sql());
        return $user->refresh($properties) ? $user : null;
    }
}
