<?php

namespace FSA\Neuron\Database;

use PDO;
use PDOStatement;

class PgsqlResult
{
    public function __construct(private PDOStatement $stmt, private bool $success)
    {
    }

    public function fetchObject(string $class = null): ?object
    {
        return $this->stmt->fetchObject($class) ?: null;
    }

    public function fetchAllObject(string $class = null): array
    {
        if (is_null($class)) {
            return $this->stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return $this->stmt->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function fetchAssociative(): ?array
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function fetchAllAssociative(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchNumeric(): ?array
    {
        return $this->stmt->fetch(PDO::FETCH_NUM) ?: null;
    }

    public function fetchAllNumeric(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_NUM);
    }

    public function fetchColumn(): mixed
    {
        return $this->stmt->fetchColumn();
    }

    public function fetchAllColumn(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function fetchJson(): array|object
    {
        return json_decode($this->stmt->fetchColumn());
    }

    public function fetchJsonAssociative(): array
    {
        return json_decode($this->stmt->fetchColumn(), true);
    }

    public function getStatement(): PDOStatement
    {
        return $this->stmt;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }
}