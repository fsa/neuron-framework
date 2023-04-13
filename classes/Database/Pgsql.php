<?php

namespace FSA\Neuron\Database;

use PDO;

class Pgsql extends PDO
{
    private $pdo;

    public function __construct(private string $url, private ?string $timezone = null)
    {
    }

    public function getPDO(): PDO
    {
        if (isset($this->pdo)) {
            return $this->pdo;
        }
        if (!filter_var($this->url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            throw new ConnectionException('Database is not configured');
        }
        $db = parse_url($this->url);
        $this->pdo = new PDO(sprintf(
            "pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
            $db['host'],
            $db['port'] ?? 5432,
            $db['user'],
            $db['pass'],
            ltrim($db["path"], "/")
        ));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->timezone) {
            $this->pdo->query("SET TIMEZONE=\"{$this->timezone}\"");
        }

        return $this->pdo;
    }

    public function executeQuery(string $query, null|string|array $param = null): PgsqlResult
    {
        if (is_null($param)) {
            $stmt = $this->getPDO()->query($query);
            $success = true;
        } else {
            if (is_string($param)) {
                $param = [$param];
            }
            $stmt = $this->getPDO()->prepare($query);
            $success = $stmt->execute($param);
        }

        return new PgsqlResult($stmt, $success);
    }

    public function insert(string $table, array $values, string $index = 'id')
    {
        $keys = array_keys($values);
        $stmt = $this->getPDO()->prepare('INSERT INTO ' . $table . ' (' . join(',', $keys) . ') VALUES (:' . join(',:', $keys) . ') RETURNING ' . $index);
        $stmt->execute($values);
        return $stmt->fetchColumn();
    }

    public function update(string $table, array $values, string $index = 'id', string $old_index = null)
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
        $stmt = $this->getPDO()->prepare('UPDATE ' . $table . ' SET ' . join(',', $keys) . ' WHERE ' . $index . '=:' . $old_index);
        return $stmt->execute($values);
    }

    public function fetchEntity(string $class, string|int $id_value): ?object
    {
        $id = $this->getEntityId($class);

        return $this->executeQuery('SELECT * FROM ' . $this->getEntityTable($class) . ' WHERE ' . $id . '=?', [$id_value])->fetchObject($class);
    }

    public function insertEntity(object &$object): string|int
    {
        $class_name = get_class($object);
        $index = $this->getEntityId($class_name);
        $properties = $this->getEntityProperties($object);
        if (is_null($properties[$index])) {
            unset($properties[$index]);
        }
        $id = $this->insert($this->getEntityTable($class_name), $properties, $index);
        $object->$index = $id;

        return $id;
    }

    public function updateEntity(object $object, int|string $old_index_value = null)
    {
        $properties = $this->getEntityProperties($object);
        $id = $this->getEntityId(get_class($object));
        if ($old_index_value) {
            $old_index = 'old_' . $id;
            $properties[$old_index] = $old_index_value;
            return $this->update($this->getEntityTable(get_class($object)), $properties, $id, $old_index);
        }
        return $this->update($this->getEntityTable(get_class($object)), $properties, $id);
    }

    private function getEntityTable(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $attr = $reflection->getAttributes(Mapping\Table::class);
        if (count($attr) == 0) {
            throw new EntityException('Table name not defined');
        }

        return $attr[0]->newInstance()->name;
    }

    private function getEntityId(string $class): string
    {
        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getProperties() as $property) {
            $attr = $property->getAttributes(Mapping\Id::class);
            if ($attr) {
                return $property->getName();
            }
        }
        throw new EntityException('Id column not defined');
    }

    private function getEntityProperties(object $object): array
    {
        $properties = [];
        $reflection = new \ReflectionClass(get_class($object));
        foreach ($reflection->getProperties() as $property) {
            $attr = $property->getAttributes(Mapping\Column::class);
            if ($attr) {
                $name = $property->getName();
                switch ($property->getType()) {
                    case 'bool':
                        $properties[$name] = $object->$name ? 't' : 'f';
                        break;
                    default:
                        $properties[$name] = $object->$name;
                }
            }
        }

        return $properties;
    }
}
