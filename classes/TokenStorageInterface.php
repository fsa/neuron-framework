<?php

namespace FSA\Neuron;

interface TokenStorageInterface
{

    function set(string $token, object|array|string $data): void;
    function get(string $token): object|string|null;
    function del(string $token): void;
}
