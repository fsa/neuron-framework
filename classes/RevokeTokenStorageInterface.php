<?php

namespace FSA\Neuron;

interface RevokeTokenStorageInterface
{

    function add(string $token, string $new_token): void;
    function get(string $token): array;
    function del(string $token): void;
}
