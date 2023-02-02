<?php

namespace FSA\Neuron;

use Redis;

class RevokeTokenStorageRedis implements RevokeTokenStorageInterface
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

    public function get($token): array
    {
        $tokens = json_decode($this->redis()->get($this->prefix . $token), true);
        if (!is_array($tokens)) {
            $tokens = [];
        }
        return $tokens;
    }

    public function add($token, $new_token): void
    {
        $old = $this->get($token);
        $old[] = $new_token;
        $this->redis()->setEx($this->prefix . $token, $this->lifetime, json_encode($old));
    }

    public function del($token): void
    {
        $this->redis()->del($this->prefix . $token);
    }
}
