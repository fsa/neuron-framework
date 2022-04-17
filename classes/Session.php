<?php

namespace FSA\Neuron;

class Session
{
    const SESSION_LIFETIME = 1800;
    const TOKEN_LIFETIME = 2592000;

    private $cookie_session;
    private $cookie_session_lifetime = self::SESSION_LIFETIME;
    private $cookie_token;
    private $cookie_token_lifetime = self::TOKEN_LIFETIME;
    private $cookie_options;
    private $session;
    private $admins = [];
    private $storage;

    public function __construct(string $cookie_name, SessionStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->cookie_session = $cookie_name . '_access_token';
        $this->cookie_token = $cookie_name . '_refresh_token';
        $this->cookie_options = [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        $session_cookie = filter_input(INPUT_COOKIE, $this->cookie_session);
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
            $this->dropSessionCookie();
            return;
        }
        if ($this->restoreSession()) {
            return;
        }
    }

    public function setCookieOptions(array $options)
    {
        $this->cookie_options = [
            'path' => empty($options['path'])? '/' : $options['path'],
            'domain' => empty($options['domain']) ? '' : $options['domain'],
            'secure' => empty($options['secure']) ? false : boolval($options['secure']),
            'httponly' => empty($options['httponly']) ? true : boolval($options['httponly']),
            'samesite' => empty($options['samesite']) ? 'Strict' : $options['samesite']
        ];
    }

    public function setSessionLifetime(int $session_lifetime)
    {
        $this->cookie_session_lifetime = $session_lifetime;
        $this->storage->setSessionLifetime($session_lifetime);
    }

    public function setTokenLifetime(int $token_lifetime)
    {
        $this->cookie_token_lifetime = $token_lifetime;
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

    public function memberOf(?array $scope=null): bool
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

    private function revokeToken($token)
    {
        $current_token = filter_input(INPUT_COOKIE, $this->cookie_token);
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
        $token = filter_input(INPUT_COOKIE, $this->cookie_token);
        if (!$token) {
            return false;
        }
        $session = $this->storage->getToken($token);
        if (!$session) {
            $this->dropTokenCookie();
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
            $this->dropTokenCookie();
            $this->storage->delToken($token);
            return false;
        }
        $class_name = $session->class;
        if (!class_exists($class_name)) {
            $this->dropTokenCookie();
            $this->storage->delToken($token);
            return false;
        }
        $user = $class_name::validate((array)$session->validate);
        if (!$user) {
            $this->dropTokenCookie();
            $this->storage->delToken($token);
            return false;
        }
        $session_token = $this->generateRandomString();
        $new_token = $this->generateRandomString();
        $this->session = $user;
        $user->revoke_token = $token;
        $user->refresh_token = $new_token;
        $this->storage->setSession($session_token, $user);
        $this->storage->addRevokeToken($token, $new_token);
        $this->storage->setToken($new_token, ['revoke_token' => $token, 'class' => get_class($user), 'validate'=>$user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR'), 'session_lifetime' => $this->cookie_session_lifetime, 'token_lifetime' => $this->cookie_token_lifetime]);
        $this->setSessionCookie($session_token);
        $this->setTokenCookie($new_token);
        return true;
    }

    public function login(UserInterface $user, $session_lifetime = self::SESSION_LIFETIME, $token_lifetime = self::TOKEN_LIFETIME)
    {
        $old_token = filter_input(INPUT_COOKIE, $this->cookie_token);
        if ($old_token) {
            $this->revokeToken($old_token);
        }
        $this->setSessionLifetime($session_lifetime);
        $this->setTokenLifetime($token_lifetime);
        $session_token = $this->generateRandomString();
        $token = $this->generateRandomString();
        $this->session = ['id' => $user->getId(), 'login' => $user->getLogin(), 'name' => $user->getName(), 'email' => $user->getEmail(), 'scope' => $user->getScope(), 'refresh_token' => $token];
        $this->storage->setSession($session_token, $user);
        $this->storage->setToken($token, ['class' => get_class($user), 'validate'=>$user->getProperties(), 'browser' => getenv('HTTP_USER_AGENT'), 'ip' => getenv('REMOTE_ADDR'), 'session_lifetime' => $session_lifetime, 'token_lifetime' => $token_lifetime]);
        $this->setSessionCookie($session_token);
        $this->setTokenCookie($token);
    }

    public function logout()
    {
        $this->user = null;
        $session_cookie = filter_input(INPUT_COOKIE, $this->cookie_session);
        if ($session_cookie) {
            $this->storage->delSession($session_cookie);
            $this->dropSessionCookie();
        }
        $token = filter_input(INPUT_COOKIE, $this->cookie_token);
        if ($token) {
            $this->revokeToken($token);
            $this->storage->delToken($token);
            $this->dropTokenCookie();
        }
    }

    private function generateRandomString(int $length = 32): string
    {
        $symbols = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890';
        $max_index = strlen($symbols) - 1;
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $symbols[rand(0, $max_index)];
        }
        return $string;
    }

    private function setSessionCookie(string $token): void
    {
        $options = $this->cookie_options;
        $options['expires'] = time() + $this->cookie_session_lifetime;
        setcookie($this->cookie_session, $token, $options);
    }

    private function dropSessionCookie(): void
    {
        $options = $this->cookie_options;
        $options['expires'] = 1;
        setcookie($this->cookie_session, '', $options);
    }

    private function setTokenCookie(string $token): void
    {
        $options = $this->cookie_options;
        $options['expires'] = time() + $this->cookie_token_lifetime;
        setcookie($this->cookie_token, $token, $options);
    }

    private function dropTokenCookie(): void
    {
        $options = $this->cookie_options;
        $options['expires'] = 1;
        setcookie($this->cookie_token, '', $options);
    }
}
