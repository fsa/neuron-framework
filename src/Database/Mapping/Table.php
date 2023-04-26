<?php

namespace FSA\Neuron\Database\Mapping;

use Attribute;

#[Attribute]
class Table
{
    public function __construct(public string $name)
    { 
    }
}