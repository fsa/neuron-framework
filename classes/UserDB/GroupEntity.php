<?php

namespace FSA\Neuron\UserDB;

use FSA\Neuron\Entity,
    FSA\Neuron\DB,
    PDO;

class GroupEntity extends Entity {

    const TABLENAME='user_groups';

    public $name;
    public $description;

    public static function getGroups() {
        $s=DB::query('SELECT g.name, g.description FROM user_groups g ORDER BY g.name');
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

