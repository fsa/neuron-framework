<?php

namespace FSA\Neuron;

interface SQLEntityInterface
{
    const TABLE_NAME = 'table_name';
    const UID = 'id';

    function getProperties(): array;
}
