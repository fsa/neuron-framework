<?php

namespace FSA\Neuron;

class Container
{

    protected $instances = [];

    public function __construct(protected array $singletons, protected array $dependencies)
    {
    }

    public function addSingletons(array $singletons): static
    {
        $this->singletons = array_merge($this->singletons, $singletons);
        return $this;
    }

    public function addDependencies(array $dependencies): static
    {
        $this->dependencies = array_merge($this->dependencies, $dependencies);
        return $this;
    }

    public function get(string $id)
    {
        if (isset($this->singletons[$id])) {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $this->singletons[$id]();
            }
            return $this->instances[$id];
        }
        return isset($this->dependencies[$id]) ? $this->dependencies[$id]() : $this->prepareObject($id);
    }

    public function has(string $id): bool
    {
        return isset($this->singletons[$id]) or isset($this->dependencies[$id]) or class_exists($id);
    }

    private function prepareObject(string $class): object
    {
        $classReflector = new \ReflectionClass($class);
        $constructReflector = $classReflector->getConstructor();
        if (empty($constructReflector)) {
            return new $class;
        }
        $constructArguments = $constructReflector->getParameters();
        if (empty($constructArguments)) {
            return new $class;
        }
        $args = [];
        foreach ($constructArguments as $argument) {
            $argumentType = $argument->getType()->getName();
            $args[$argument->getName()] = $this->get($argumentType);
        }
        return new $class(...$args);
    }
}
