<?php

namespace FSA\Neuron;

class ResponseHtml extends Response
{

    private $template;
    private $main_template_name;
    private $login_template_name;
    private $message_template_name;
    private $context;
    private $last_modified;
    private $etag;
    private $header_shown;

    public function __construct($main, $login, $message)
    {
        $this->main_template_name = $main;
        $this->login_template_name = $login;
        $this->message_template_name = $message;
        $this->header_shown = false;
    }

    public function getTemplate()
    {
        if (is_null($this->template)) {
            $tpl = $this->main_template_name;
            $this->template = new $tpl;
        }
        return $this->template;
    }

    public function setTemplate(object $template)
    {
        $this->template = $template;
    }

    public function setContext(array $context)
    {
        $this->context = $context;
    }

    public function setLastModified(int $timestamp)
    {
        $this->last_modified = $timestamp;
    }

    public function setETag(string $etag)
    {
        $this->etag = $etag;
    }

    public function addHeader(string $header)
    {
        $template = $this->getTemplate();
        $template->header .= $header . PHP_EOL;
    }

    public function addDescription($description)
    {
        $this->addHeader("<meta name=\"description\" content=\"$description\">");
    }

    public function getTitle()
    {
        $template = $this->getTemplate();
        return $template->title;
    }

    public function showHeader($title = null)
    {
        $template = $this->getTemplate();
        $template->title = $title;
        $template->context = $this->context;
        if (isset($this->last_modified)) {
            header("Last-Modified: " . gmdate('D, d M Y H:i:s', $this->last_modified) . ' GMT');
        } else {
            $this->disableBrowserCache();
        }
        if (isset($this->etag)) {
            header("ETag: " . $this->etag);
        }
        $notification = filter_input(INPUT_COOKIE, 'notification');
        if ($notification) {
            $template->notify = $notification;
            setcookie('notification', '', time() - 3600, '/');
        }
        $template->showHeader();
        $this->header_shown = true;
    }

    public function showFooter()
    {
        $template = $this->getTemplate();
        $template->showFooter();
    }

    public function storeNotification(string $message)
    {
        setcookie('notification', $message, 0, '/');
    }

    public function showLoginForm(string $redirect_url = null)
    {
        $tpl = $this->login_template_name;
        $template = new $tpl;
        $template->title = 'Вход в систему';
        $template->context = $this->context;
        if ($redirect_url) {
            $template->redirect_uri = $redirect_url;
            $template->url = '/login/';
        }
        $this->disableBrowserCache();
        $template->show();
    }

    public function disableBrowserCache()
    {
        header("Cache-Control: no-store, no-cache, must-revalidate");
    }

    public function __call($name, $args)
    {
        return $this->getTemplate()->$name(...$args);
    }

    # Информация для пользователя

    public function showPopup(string $message, string $title, string $style = null)
    {
        $this->getTemplate()->showPopup($message, $title, $style);
    }

    public function showMessagePage(string $message, string $title, string $style = null)
    {
        $tpl = $this->message_template_name;
        $template = new $tpl;
        $template->style = $style;
        $template->title = $title;
        $template->context = $this->context;
        $template->message = $message;
        $this->disableBrowserCache();
        $template->show();
    }

    public function showInformation(string $message)
    {
        if ($this->header_shown) {
            $this->showPopup($message, 'Информация', 'info');
        } else {
            $this->showMessagePage($message, 'Информация', 'info');
        }
    }

    public function returnError($http_response_code, $message = null)
    {
        $description = parent::HTTP_STATUS_CODES[$http_response_code] ?? 'Unknown';
        if (empty($message)) {
            $message = $description;
        }
        if ($this->header_shown) {
            if ($http_response_code==200) {
                self::showPopup($message, 'Ошибка', 'danger');
            } else {
                self::showPopup($message, $http_response_code . ' ' . $description, 'danger');
            }
            self::showFooter();
            exit;
        }
        switch ($http_response_code) {
            case 401:
                http_response_code($http_response_code);
                $this->showLoginForm(getenv('REQUEST_METHOD') == 'GET' ? getenv('REQUEST_URI') : '/');
                exit;
            case 403:
                http_response_code($http_response_code);
                $this->showMessagePage('У вас отсутствуют необходимые права доступа.', $http_response_code . ' ' . $description, 'warning');
                exit;
            case 400:
            case 402:
            case 404:
            case 405:
            case 406:
            case 407:
            case 408:
            case 409:
            case 410:
            case 411:
            case 412:
            case 413:
            case 414:
            case 415:
            case 429:
            case 500:
            case 501:
                http_response_code($http_response_code);
                $this->showMessagePage($message, $http_response_code . ' ' . $description, 'warning');
                break;
            default:
                $this->showMessagePage($message, 'Ошибка', 'danger');
        }
        exit;
    }
}
