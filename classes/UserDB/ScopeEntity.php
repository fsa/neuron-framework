<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\Entity,
    PDO;

class ScopeEntity extends Entity {

    public $name;
    public $description;

    public static function getScopes(PDO $pdo) {
        $s=$pdo->query('SELECT name, description FROM user_scopes ORDER BY name');
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

