<?php

namespace FSA\Neuron;

use Closure;

abstract class Controller
{
    protected $name;
    protected $path;

    public function __construct(array $path, protected Container $container)
    {
        $this->name = array_shift($path);
        $this->path = $path;
    }

    public function route()
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
                    $args[] = $this->container->get((string)$type);
                }
            }
            $this->{$method->getName()}(...$args);
            exit;
        }
    }

    public function next(string $class)
    {
        if (class_exists($class)) {
            $controller = new $class($this->path, $this->container);
            $controller->route();
        } else {
            throw new HtmlException("Class $class does not exists.", 500);
        }
    }
}
