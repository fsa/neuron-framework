<?php

namespace FSA\Neuron;

abstract class View
{
    protected $name;
    protected $path;

    public function __construct(array $path)
    {
        $this->name = array_shift($path);
        $this->path = $path;
    }

    abstract function route();
}