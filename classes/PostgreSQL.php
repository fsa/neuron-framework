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

    public function getEntity(string $class_name, array $where)
    {
        if (sizeof($where) == 0) {
            return null;
        }
        $keys = array_keys($where);
        foreach ($keys as &$key) {
            $key = $key . '=:' . $key;
        }
        $s = $this->prepare('SELECT * FROM ' . $class_name::TABLE_NAME . ' WHERE ' . join(' AND ', $keys));
        $s->execute($where);
        return $s->fetchObject($class_name);
    }

    public function setEntity(SQLEntityInterface $object, $id=null)
    {
        $properties = $object->getProperties();
        if (is_null($id)) {
            $id = $object::UID;
        }
        $keys = array_keys($properties);
        $i = array_search($id, $keys);
        if ($i !== false) {
            unset($keys[$i]);
        }
        foreach ($keys as &$key) {
            $key = $key . '=:' . $key;
        }
        $stmt = $this->prepare('UPDATE ' . $object::TABLE_NAME . ' SET ' . join(',', $keys) . ' WHERE ' . $id . '=:' . $id);
        return $stmt->execute($properties);
    }
}
