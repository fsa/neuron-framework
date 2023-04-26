<?php

namespace FSA\Neuron;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ThrowException
{
    public function __construct(private string $class, private string $method_name)
    {
    }
}
