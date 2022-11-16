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

    protected function isFileName() {
        return empty($this->path);
    }

    protected function isDirName() {
        return $this->path==[''];
    }

    protected function isPath(string $path): bool
    {
        $parts = explode('/', $path);
        if (sizeof($this->path) != sizeof($parts)) {
            return false;
        }
        foreach ($parts as $i=>$part) {
            if(substr($part, 0, 1)=='#') {
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
