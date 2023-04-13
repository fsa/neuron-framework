<?php

namespace FSA\Neuron;

use Exception;

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
                switch ($type) {
                    case null:
                    case 'string':
                        $args[] = $route->get($arg->getName());
                        break;
                    default:
                        $args[] = $this->container->get((string)$type);
                }
            }
            try {
                $this->{$method->getName()}(...$args);
            } catch (Exception $ex) {
                foreach ($method->getAttributes(ThrowException::class) as $throw_exception) {
                    $ex_args = $throw_exception->getArguments();
                    if ($ex instanceof $ex_args[0]) {
                        $this->{$ex_args[1]}($ex);
                        die;
                    }
                }
                throw $ex;
            }
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
