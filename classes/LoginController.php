<?php

namespace FSA\Neuron;

class Login extends Controller
{
    const ERR_ACCESS_DENIED = 'Неверное имя пользователя или пароль.';

    #[Route('/')]
    public function login(ResponseHtml $response, Session $session, User $user)
    {
        $login = filter_input(INPUT_POST, 'login');
        $password = filter_input(INPUT_POST, 'password');
        if (!$login or !$password) {
            if ($session->memberOf()) {
                if (filter_input(INPUT_GET, 'action') == 'logout') {
                    $session->logout();
                    $response->redirection('../');
                }
                $response->returnError(200, 'Вы уже в залогинены');
            }
            $response->showLoginForm('/');
            exit;
        }
        if (!$user->login($login, $password)) {
            $response->returnError(401, self::ERR_ACCESS_DENIED);
            exit;
        }
        $session->login($user);
        $url = filter_input(INPUT_POST, 'redirect_uri');
        $response->redirection(is_null($url) ? '/' : $url);
    }
}
