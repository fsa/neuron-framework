<?php

namespace FSA\Neuron;

use Redis;

class RevokeTokenStorageRedis implements RevokeTokenStorageInterface
{
    public function __construct(private Database\Redis $redis, protected string $prefix, private $lifetime)
    {
        $this->prefix = $prefix;
    }

    public function get($token): array
    {
        $tokens = json_decode($this->redis->get($this->prefix . $token), true);
        if (!is_array($tokens)) {
            $tokens = [];
        }
        return $tokens;
    }

    public function add($token, $new_token): void
    {
        $old = $this->get($token);
        $old[] = $new_token;
        $this->redis->setEx($this->prefix . $token, $this->lifetime, json_encode($old));
    }

    public function del($token): void
    {
        $this->redis->del($this->prefix . $token);
    }
}
