<?php

namespace FSA\Neuron;

class RedisStorage implements VarStorageInterface
{
    public function __construct(private Database\Redis $redis, private string $prefix)
    {
    }

    public function get(string $key): string|null
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value ?: null;
    }

    public function set(string $key, string $value, int $lifetime = null): void
    {
        if ($lifetime) {
            $this->redis->setEx($this->prefix . $key, $lifetime, $value);
        } else {
            $this->redis->set($this->prefix . $key, $value);
        }
    }

    public function del($key): void
    {
        $this->redis->del($this->prefix . $key);
    }

    public function getJson($key, $array = true)
    {
        $value = $this->get($this->prefix . $key);
        return json_decode($value, $array);
    }

    public function setJson($key, $object)
    {
        $this->set($this->prefix . $key, json_encode($object, JSON_UNESCAPED_UNICODE));
    }


    public function searchKeys($search_key)
    {
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $it = NULL;
        $result = [];
        while ($arr_keys = $this->redis->scan($it, $this->prefix . $search_key)) {
            foreach ($arr_keys as $str_key) {
                $result[] = $str_key;
            }
        }
        return $result;
    }

    public function deleteKeys($search_key)
    {
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $it = NULL;
        $count = 0;
        while ($arr_keys = $this->redis->scan($it, $this->prefix . $search_key)) {
            foreach ($arr_keys as $str_key) {
                $this->redis->del($str_key);
                $count++;
            }
        }
        return $count;
    }
}
