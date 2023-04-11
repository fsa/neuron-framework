<?php

namespace FSA\Neuron\Database;

use RedisException;

class Redis
{
    private \Redis $redis;

    public function __construct(private string $url)
    {
    }

    public function getRedis(): \Redis
    {
        if (isset($this->redis)) {
            return $this->redis;
        }
        $db = (!$this->url) ? ['host' => '127.0.0.1'] : parse_url($this->url);
        try {
            $this->redis = new \Redis();
            $scheme = (isset($db['scheme']) and $db['scheme'] == 'rediss') ? "tls://" : '';
            $this->redis->connect($scheme . $db["host"], $db["port"] ?? 6379);
        } catch (RedisException $ex) {
            throw new ConnectionException('Redis connect failed: ' . $ex->getMessage());
        }
        if (!empty($db["pass"])) {
            if (empty($db["user"])) {
                $result = $this->redis->auth($db["pass"]);
            } else {
                $result = $this->redis->auth([$db["user"], $db["pass"]]);
            }
            if (!$result) {
                throw new AuthException('Redis auth failed');
            }
        }
        if (!empty($db["path"])) {
            $this->redis->select((int)ltrim($db['path'], '/'));
        }

        return $this->redis;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getRedis(), $name], $arguments);
    }
}
