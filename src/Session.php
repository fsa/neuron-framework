<?php

namespace FSA\Neuron;

class Session
{
    private $session;
    private $admins = [];

    public function __construct(
        private Cookie $session_cookie,
        private Cookie $refresh_token_cookie,
        private TokenStorageInterface $session_token,
        private TokenStorageInterface $refresh_token,
        private RevokeTokenStorageInterface $revoke_token,
        private Container $container
    ) {
        $session_cookie = $this->session_cookie->get();
        if ($session_cookie) {
            $this->session = $this->session_token->get($session_cookie);
            if (isset($this->session->revoke_token)) {
                $this->revokeToken($this->session->revoke_token);
                unset($this->session->revoke_token);
                $this->session_token->set($session_cookie, $this->session);
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
        $current_token = $this->refresh_token_cookie->get();
        foreach ($this->revoke_token->get($token) as $revoke_token) {
            if ($revoke_token != $current_token) {
                $this->revokeToken($revoke_token);
            }
        }
        $this->refresh_token->del($token);
        $this->revoke_token->del($token);
    }

    private function restoreSession()
    {
        $token = $this->refresh_token_cookie->get();
        if (!$token) {
            return false;
        }
        $session = $this->refresh_token->get($token);
        if (!$session) {
            $this->refresh_token_cookie->drop();
            return false;
        }
        if (isset($session->revoke_token)) {
            $this->revokeToken($session->revoke_token);
            unset($session->revoke_token);
            $this->refresh_token->set($token, $session);
        }
        if (!isset($session->class) or !isset($session->validate)) {
            $this->refresh_token_cookie->drop();
            $this->refresh_token->del($token);
            return false;
        }
        $class_name = $session->class;
        if (!class_exists($class_name)) {
            $this->refresh_token_cookie->drop();
            $this->refresh_token->del($token);
            return false;
        }
        $user = $this->container->get($class_name);
        if (!$user->validate((array)$session->validate)) {
            $this->refresh_token_cookie->drop();
            $this->refresh_token->del($token);
            return false;
        }
        $session_token = $this->generateRandomString();
        $new_token = $this->generateRandomString();
        $this->session = (object)['id' => $user->getId(), 'login' => $user->getLogin(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'scope' => $user->getScope(), 'revoke_token' => $token, 'refresh_token' => $new_token];
        $this->session_token->set($session_token, $this->session);
        $this->revoke_token->add($token, $new_token);
        $this->refresh_token->set($new_token, ['revoke_token' => $token, 'class' => get_class($user), 'validate' => $user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR')]);
        $this->session_cookie->set($session_token);
        $this->refresh_token_cookie->set($new_token);
        return true;
    }

    public function login(UserInterface $user)
    {
        $old_token = $this->session_cookie->get();
        if ($old_token) {
            $this->revokeToken($old_token);
        }
        $session_token = $this->generateRandomString();
        $token = $this->generateRandomString();
        $this->session = (object)['id' => $user->getId(), 'login' => $user->getLogin(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'scope' => $user->getScope(), 'refresh_token' => $token];
        $this->session_token->set($session_token, $this->session);
        $this->refresh_token->set($token, ['class' => get_class($user), 'validate' => $user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR')]);
        $this->session_cookie->set($session_token);
        $this->refresh_token_cookie->set($token);
    }

    public function logout()
    {
        $this->session = null;
        $session_cookie = $this->session_cookie->get();
        if ($session_cookie) {
            $this->session_token->del($session_cookie);
            $this->session_cookie->drop();
        }
        $token = $this->refresh_token_cookie->get();
        if ($token) {
            $this->revokeToken($token);
            $this->refresh_token->del($token);
            $this->refresh_token_cookie->drop();
        }
    }

    private function generateRandomString(int $length = 32): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(openssl_random_pseudo_bytes($length)));
    }
}
