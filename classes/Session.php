<?php

namespace FSA\Neuron;

class Session
{
    const SESSION_LIFETIME = 1800;
    const TOKEN_LIFETIME = 2592000;

    private $session_lifetime = self::SESSION_LIFETIME;
    private $refresh_token_lifetime = self::TOKEN_LIFETIME;
    private $cookie_options;
    private $session;
    private $admins = [];

    public function __construct(
        private Cookie $session_cookie,
        private Cookie $refresh_token_cookie,
        private SessionStorageInterface $storage
    ) {
        $session_cookie = $this->session_cookie->get();
        if ($session_cookie) {
            $this->session = $this->storage->getSession($session_cookie);
            if (isset($this->session->revoke_token)) {
                $this->revokeToken($this->session->revoke_token);
                unset($this->session->revoke_token);
                $this->storage->setSession($session_cookie, $this->session, $this->storage->getSessionTtl($session_cookie));
            }
            if (isset($this->session)) {
                return;
            }
            if ($this->restoreSession()) {
                return;
            }
            $this->session_cookie->drop();
            return;
        }
        if ($this->restoreSession()) {
            return;
        }
    }

    public function setSessionLifetime(int $session_lifetime)
    {
        $this->session_lifetime = $session_lifetime;
        $this->storage->setSessionLifetime($session_lifetime);
    }

    public function setTokenLifetime(int $token_lifetime)
    {
        $this->refresh_token_lifetime = $token_lifetime;
        $this->storage->setTokenLifetime($token_lifetime);
    }

    public function setAdmins(array $admins)
    {
        $this->admins = $admins;
    }

    public function grantAccess(array $scope = null): void
    {
        if ($this->memberOf($scope)) {
            return;
        }
        if (is_null($this->session)) {
            throw new HtmlException('', 401);
        }
        throw new HtmlException('', 403);
    }

    public function memberOf(?array $scope = null): bool
    {
        if (is_null($this->session)) {
            return false;
        }
        if (is_null($scope)) {
            return true;
        }
        if ($this->admins) {
            if (array_search($this->session->login, $this->admins) !== false) {
                return true;
            }
        }
        if (isset($this->session->scope)) {
            foreach ($scope as $item) {
                if (array_search($item, $this->session->scope) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getUserId()
    {
        if (isset($this->session)) {
            return $this->session->id;
        }
        return null;
    }

    public function getUserLogin()
    {
        if (isset($this->session)) {
            return $this->session->login;
        }
        return null;
    }

    public function getUserName()
    {
        if (isset($this->session)) {
            return $this->session->name;
        }
        return null;
    }

    public function getUserEmail()
    {
        if (isset($this->session)) {
            return $this->session->email;
        }
        return null;
    }

    private function revokeToken($token)
    {
        $current_token = $this->session_cookie->get();
        foreach ($this->storage->getRevokeTokens($token) as $revoke_token) {
            if ($revoke_token != $current_token) {
                $this->revokeToken($revoke_token);
            }
        }
        $this->storage->delToken($token);
        $this->storage->delRevokeTokens($token);
    }

    private function restoreSession()
    {
        $token = $this->refresh_token_cookie->get();
        if (!$token) {
            return false;
        }
        $session = $this->storage->getToken($token);
        if (!$session) {
            $this->refresh_token_cookie->drop();
            return false;
        }
        if (isset($session->session_lifetime)) {
            $this->setSessionLifetime($session->session_lifetime);
        }
        if (isset($session->token_lifetime)) {
            $this->setTokenLifetime($session->token_lifetime);
        }
        if (isset($session->revoke_token)) {
            $this->revokeToken($session->revoke_token);
            unset($session->revoke_token);
            $this->storage->setToken($token, $session);
        }
        if (!isset($session->class) or !isset($session->validate)) {
            $this->refresh_token_cookie->drop();
            $this->storage->delToken($token);
            return false;
        }
        $class_name = $session->class;
        if (!class_exists($class_name)) {
            $this->refresh_token_cookie->drop();
            $this->storage->delToken($token);
            return false;
        }
        $user = $class_name::validate((array)$session->validate);
        if (!$user) {
            $this->refresh_token_cookie->drop();
            $this->storage->delToken($token);
            return false;
        }
        $session_token = $this->generateRandomString();
        $new_token = $this->generateRandomString();
        $this->session = (object)['id' => $user->getId(), 'login' => $user->getLogin(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'scope' => $user->getScope(), 'revoke_token' => $token, 'refresh_token' => $new_token];
        $this->storage->setSession($session_token, $this->session);
        $this->storage->addRevokeToken($token, $new_token);
        $this->storage->setToken($new_token, ['revoke_token' => $token, 'class' => get_class($user), 'validate' => $user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR'), 'session_lifetime' => $this->session_lifetime, 'token_lifetime' => $this->refresh_token_lifetime]);
        $this->session_cookie->set($session_token, $this->session_lifetime);
        $this->refresh_token_cookie->set($new_token, $this->refresh_token_lifetime);
        return true;
    }

    public function login(UserInterface $user, $session_lifetime = self::SESSION_LIFETIME, $token_lifetime = self::TOKEN_LIFETIME)
    {
        $old_token = $this->session_cookie->get();
        if ($old_token) {
            $this->revokeToken($old_token);
        }
        $this->setSessionLifetime($session_lifetime);
        $this->setTokenLifetime($token_lifetime);
        $session_token = $this->generateRandomString();
        $token = $this->generateRandomString();
        $this->session = (object)['id' => $user->getId(), 'login' => $user->getLogin(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'scope' => $user->getScope(), 'refresh_token' => $token];
        $this->storage->setSession($session_token, $this->session);
        $this->storage->setToken($token, ['class' => get_class($user), 'validate' => $user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR'), 'session_lifetime' => $session_lifetime, 'token_lifetime' => $token_lifetime]);
        $this->session_cookie->set($session_token, $this->session_lifetime);
        $this->refresh_token_cookie->set($token, $this->refresh_token_lifetime);
    }

    public function logout()
    {
        $this->session = null;
        $session_cookie = $this->session_cookie->get();
        if ($session_cookie) {
            $this->storage->delSession($session_cookie);
            $this->session_cookie->drop();
        }
        $token = $this->refresh_token_cookie->get();
        if ($token) {
            $this->revokeToken($token);
            $this->storage->delToken($token);
            $this->refresh_token_cookie->drop();
        }
    }

    private function generateRandomString(int $length = 32): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(openssl_random_pseudo_bytes($length)));
    }
}
