<?php

namespace FSA\Neuron;

use Redis;

class SessionStorageRedis implements SessionStorageInterface
{

    private $session_lifetime;
    private $token_lifetime;
    private $name;
    private $redis;

    public function __construct(string $name, Redis $redis)
    {
        $this->name = $name;
        $this->redis = $redis;
    }

    public function setSessionLifetime(int $session_lifetime)
    {
        $this->session_lifetime = $session_lifetime;
    }

    public function setTokenLifetime(int $token_lifetime)
    {
        $this->token_lifetime = $token_lifetime;
    }

    public function getSession(string $token)
    {
        $session = json_decode($this->redis->get($this->name . ':session:' . $token));
        if (!$session) {
            return null;
        }
        return $session;
    }

    public function setSession(string $token, $data, $ttl = null)
    {
        $this->redis->setEx($this->name . ':session:' . $token, $ttl ? $ttl : $this->session_lifetime, json_encode($data));
    }

    public function getSessionTtl(string $token)
    {
        return $this->redis->ttl($this->name . ':session:' . $token);
    }

    public function delSession($token)
    {
        $this->redis->del($this->name . ':session:' . $token);
    }

    public function getToken(string $token)
    {
        $session = json_decode($this->redis->get($this->name . ':session:token:' . $token));
        if (!$session) {
            return null;
        }
        return $session;
    }

    public function setToken(string $token, $data)
    {
        $this->redis->setEx($this->name . ':session:token:' . $token, $this->token_lifetime, json_encode($data));
    }

    public function delToken($token)
    {
        $this->redis->del($this->name . ':session:token:' . $token);
    }

    public function getRevokeTokens($token)
    {
        $tokens = json_decode($this->redis->get($this->name . ':session:token:' . $token . ':revoke'), true);
        if (!is_array($tokens)) {
            $tokens = [];
        }
        return $tokens;
    }

    public function addRevokeToken($token, $new_token)
    {
        $old = $this->getRevokeTokens($token);
        $old[] = $new_token;
        $this->redis->setEx($this->name . ':session:token:' . $token . ':revoke', $this->token_lifetime, json_encode($old));
    }

    public function delRevokeTokens($token)
    {
        $this->redis->del($this->name . ':session:token:' . $token . ':revoke');
    }
}
