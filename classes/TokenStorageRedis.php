<?php

namespace FSA\Neuron;

use Redis;

class TokenStorageRedis implements TokenStorageInterface
{

    private Redis $redis;
    private $redis_callback;

    public function __construct(Redis|callable $redis, protected string $prefix, private $lifetime)
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

    public function get(string $token): object|string|null
    {
        $session = json_decode($this->redis()->get($this->prefix . $token));
        if (!$session) {
            return null;
        }
        return $session;
    }

    public function set(string $token, object|array|string $data): void
    {
        $this->redis()->setEx($this->prefix . $token, $this->lifetime, json_encode($data));
    }

    public function del($token): void
    {
        $this->redis()->del($this->prefix . $token);
    }
}
