<?php

namespace FSA\Neuron;

use PDO;

class PostgreSQL extends PDO
{
    public function __construct($url)
    {
        if (empty($url)) {
            throw new HtmlException('Database is not configured.', 500);
        }
        $db = parse_url($url);
        parent::__construct(sprintf(
            "pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
            $db['host'],
            $db['port'] ?? 5432,
            $db['user'],
            $db['pass'],
            ltrim($db["path"], "/")
        ));
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function insert($table, $values, $index = 'id')
    {
        $keys = array_keys($values);
        $stmt = $this->prepare('INSERT INTO ' . $table . ' (' . join(',', $keys) . ') VALUES (:' . join(',:', $keys) . ') RETURNING ' . $index);
        $stmt->execute($values);
        return $stmt->fetchColumn();
    }

    public function update($table, $values, $index = 'id', $old_index = null)
    {
        if (is_null($old_index)) {
            $old_index = $index;
        }
        $keys = array_keys($values);
        $i = array_search($old_index, $keys);
        if ($i !== false) {
            unset($keys[$i]);
        }
        foreach ($keys as &$key) {
            $key = $key . '=:' . $key;
        }
        $stmt = $this->prepare('UPDATE ' . $table . ' SET ' . join(',', $keys) . ' WHERE ' . $index . '=:' . $old_index);
        return $stmt->execute($values);
    }

    public function fetchEntity(string $class_name, string|array $where)
    {
        if (is_string($where)) {
            $where = [$class_name::getIndexRow() => $where];
        } else {
            if (sizeof($where) == 0) {
                return null;
            }
        }
        $keys = array_keys($where);
        foreach ($keys as &$key) {
            $key = $key . '=:' . $key;
        }
        $s = $this->prepare('SELECT * FROM ' . $class_name::getTableName() . ' WHERE ' . join(' AND ', $keys));
        $s->execute($where);
        return $s->fetchObject($class_name);
    }

    public function insertEntity(SQL\EntityInterface $object)
    {
        $index = $object::getIndexRow();
        $properties = $object->getProperties();
        if (is_null($properties[$index])) {
            unset($properties[$index]);
        }
        $keys = array_keys($properties);
        $stmt = $this->prepare('INSERT INTO ' . $object::getTableName() . ' (' . join(',', $keys) . ') VALUES (:' . join(',:', $keys) . ') RETURNING ' . $index);
        $stmt->execute($properties);
        return $stmt->fetchColumn();
    }

    public function updateEntity(SQL\EntityInterface $object, $id = null)
    {
        $properties = $object->getProperties();
        if (is_null($id)) {
            $id = $object::getIndexRow();
        }
        $keys = array_keys($properties);
        $i = array_search($id, $keys);
        if ($i !== false) {
            unset($keys[$i]);
        }
        foreach ($keys as &$key) {
            $key = $key . '=:' . $key;
        }
        $stmt = $this->prepare('UPDATE ' . $object::getTableName() . ' SET ' . join(',', $keys) . ' WHERE ' . $object::getIndexRow() . '=:' . $id);
        return $stmt->execute($properties);
    }

    public function fetchKeyPair(string $class_name)
    {
        if (!is_subclass_of($class_name, SQL\EntityInterface::class)) {
            throw new HtmlException('$class_name not instance of EntityInterface.', 500);
        }
        if (!is_subclass_of($class_name, SQL\KeyPairInterface::class)) {
            throw new HtmlException('$class_name not instance of KeyPairInterface.', 500);
        }
        $s = $this->query('SELECT ' . $class_name::getIndexRow() . ', ' . $class_name::getNameRow() . ' FROM ' . $class_name::getTableName() . ' ORDER BY ' . $class_name::getNameRow());
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function queryEntity(string $class_name, string $order = null)
    {
        if (!is_subclass_of($class_name, SQL\EntityInterface::class)) {
            throw new HtmlException('$class_name not instance of EntityInterface.', 500);
        }
        if (is_null($order)) {
            $order = $class_name::getIndexRow();
        }
        return $this->query('SELECT * FROM ' . $class_name::getTableName() . ' ORDER BY ' . $order);
    }
}
