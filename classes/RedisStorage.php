<?php

namespace FSA\Neuron;

use Redis;

class RedisStorage implements VarStorageInterface
{
    private Redis $redis;
    private $redis_callback;
    private $prefix;

    public function __construct(string $prefix, Redis|callable $redis)
    {
        if ($redis instanceof Redis) {
            $this->redis = $redis;
        } else {
            $this->redis_callback = $redis;
        }
        $this->prefix = $prefix;
    }

    protected function redis()
    {
        if (!isset($this->redis)) {
            $this->redis = ($this->redis_callback)();
        }
        return $this->redis;
    }

    public function get(string $key): string|null
    {
        $value = $this->redis()->get($this->prefix . $key);
        return $value ?: null;
    }

    public function set(string $key, string $value, int $lifetime = null): void
    {
        if ($lifetime) {
            $this->redis()->setEx($this->prefix . $key, $lifetime, $value);
        } else {
            $this->redis()->set($this->prefix . $key, $value);
        }
    }

    public function del($key): void
    {
        $this->redis()->del($this->prefix . $key);
    }

    public function getJson($key, $array = true)
    {
        $value = $this->get($key);
        return json_decode($value, $array);
    }

    public function setJson($key, $object)
    {
        $this->set($key, json_encode($object, JSON_UNESCAPED_UNICODE));
    }


    public function searchKeys($search_key)
    {
        $redis = $this->redis();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $it = NULL;
        $result = [];
        while ($arr_keys = $redis->scan($it, $search_key)) {
            foreach ($arr_keys as $str_key) {
                $result[] = $str_key;
            }
        }
        return $result;
    }

    public function deleteKeys($search_key)
    {
        $redis = $this->redis();
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $it = NULL;
        $count = 0;
        while ($arr_keys = $redis->scan($it, $search_key)) {
            foreach ($arr_keys as $str_key) {
                $this->redis->del($str_key);
                $count++;
            }
        }
        return $count;
    }
}
