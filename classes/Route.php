<?php

namespace FSA\Neuron;

use Attribute;

#[Attribute]
class Route
{
    private $vars = [];

    public function __construct(private $path = '', private ?array $templates = null, private bool $strict = true)
    {
    }

    public function match(array $path)
    {
        if ($this->path == '' and empty($path)) {
            return true;
        }
        if ($this->path == '/' and $path == ['']) {
            return true;
        }
        $parts = explode('/', $this->path);
        if ($this->strict) {
            if (sizeof($path) != sizeof($parts)) {
                return false;
            }
        } else {
            if (sizeof($path) < sizeof($parts)) {
                return false;
            }
        }
        foreach ($parts as $i => $part) {
            if (substr($part, 0, 1) == '#') {
                $name = substr($part, 1);
                $this->vars[$name] = $path[$i];
            } else {
                if ($part != $path[$i]) {
                    return false;
                }
            }
        }
        return true;
    }

    public function getTemplates()
    {
        return $this->templates;
    }

    public function get(string $name): ?string
    {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }
        return null;
    }
}
