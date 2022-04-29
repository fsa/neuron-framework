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
}
