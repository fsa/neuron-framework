<?php

namespace FSA\Neuron;

interface VarStorageInterface
{
    public function get(string $key): string|null;
    public function set(string $key, string $value, int $lifetime = null): void;
    public function del($key): void;
    public function getJson($key, $array = true);
    public function setJson($key, $object);
    public function searchKeys($search_key);
    public function deleteKeys($search_key);
}
