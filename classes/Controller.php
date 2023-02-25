<?php

namespace FSA\Neuron;

use Closure;

abstract class Controller
{
    protected $name;
    protected $path;

    public function __construct(array $path)
    {
        $this->name = array_shift($path);
        $this->path = $path;
    }

    public function route(Closure $container)
    {
        $reflection = new \ReflectionClass(static::class);
        foreach ($reflection->getMethods() as $method) {
            $attr = $method->getAttributes(Route::class);
            if (!$attr) {
                continue;
            }
            $route = $attr[0]->newInstance();
            if (!$route->match($this->path)) {
                continue;
            }
            $args = [];
            foreach ($method->getParameters() as $arg) {
                $type = $arg->getType();
                if (is_null($type)) {
                    $args[] = $route->get($arg->getName());
                } else {
                    $args[] = $container((string)$type);
                }
            }
            $this->{$method->getName()}(...$args);
            exit;
        }
    }

    // Устарело, оставлено для совместимости
    protected function isFileName()
    {
        return empty($this->path);
    }

    // Устарело, оставлено для совместимости
    protected function isDirName()
    {
        return $this->path == [''];
    }

    // Устарело, оставлено для совместимости
    protected function isPath(string $path): bool
    {
        $parts = explode('/', $path);
        if (sizeof($this->path) != sizeof($parts)) {
            return false;
        }
        foreach ($parts as $i => $part) {
            if (substr($part, 0, 1) == '#') {
                $name = substr($part, 1);
                $this->$name = $this->path[$i];
            } else {
                if ($part != $this->path[$i]) {
                    return false;
                }
            }
        }
        return true;
    }
}
