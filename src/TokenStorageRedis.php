<?php

namespace FSA\Neuron;

class TokenStorageRedis implements TokenStorageInterface
{
    public function __construct(private Database\Redis $redis, protected string $prefix, private $lifetime)
    {
        $this->prefix = $prefix;
    }

    public function get(string $token): object|string|null
    {
        $session = json_decode($this->redis->get($this->prefix . $token));
        if (!$session) {
            return null;
        }
        return $session;
    }

    public function set(string $token, object|array|string $data): void
    {
        $this->redis->setEx($this->prefix . $token, $this->lifetime, json_encode($data));
    }

    public function del($token): void
    {
        $this->redis->del($this->prefix . $token);
    }
}
