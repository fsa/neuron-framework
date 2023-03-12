<?php

namespace FSA\Neuron;

class Settings
{
    private $settings;

    public function __construct(string $filename)
    {
        static::$settings = require $filename;
    }

    public function get(mixed $name, $default_value = null)
    {
        return $this->settings[$name] ?? $default_value;
    }
}
