<?php

namespace FSA\Neuron;

interface SessionStorageInterface
{
    public function setSessionLifetime(int $session_lifetime);
    public function setTokenLifetime(int $token_lifetime);
    public function getSession(string $token);
    public function setSession(string $token, $data, $ttl = null);
    public function delSession($token);
    public function getSessionTtl(string $token);
    public function getToken(string $token);
    public function setToken(string $token, $data);
    public function delToken($token);
    public function getRevokeTokens($token);
    public function addRevokeToken($token, $new_token);
    public function delRevokeTokens($token);
}
