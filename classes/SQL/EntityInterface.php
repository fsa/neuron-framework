<?php

namespace FSA\Neuron\SQL;

interface EntityInterface
{
    static function getTableName(): string;
    static function getIndexRow(): string;
    function getProperties(): array;
}
