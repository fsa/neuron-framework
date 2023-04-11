<?php

namespace FSA\Neuron\Database;

use PDO;
use PDOStatement;

class PgsqlResult
{
    public function __construct(private PDOStatement $stmt)
    {
    }

    public function fetchObject(string $class = null)
    {
        return $this->stmt->fetchObject($class);
    }

    public function fetchAllObject(string $class = null)
    {
        if (is_null($class)) {
            return $this->stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return $this->stmt->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function fetchAssociative()
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAllAssociative()
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchNumeric()
    {
        return $this->stmt->fetch(PDO::FETCH_NUM);
    }

    public function fetchAllNumeric()
    {
        return $this->stmt->fetchAll(PDO::FETCH_NUM);
    }

    public function fetchColumn()
    {
        return $this->stmt->fetchColumn();
    }

    public function getStatement(): PDOStatement
    {
        return $this->stmt;
    }
}