<?php

namespace FSA\Neuron;

use PDO;
use Redis;
use RedisException;

abstract class AbstractCore
{
    const SESSION_LIFETIME = 1800;
    const REFRESH_TOKEN_LIFETIME = 2592000;

    const VAR_PREFIX = 'neuron';
    const SESSION_NAME = 'neuron';

    protected static $response;
    protected static $container;
    protected static $work_dir;

    public static function getResponseHtml(): ResponseHtml
    {
        return static::$response = new ResponseHtml(\Templates\Main::class, \Templates\Login::class, \Templates\Message::class);
    }

    public static function getResponseJson(): ResponseJson
    {
        return static::$response = new ResponseJson;
    }

    public static function getWorkDir(): string
    {
        if (!isset(static::$work_dir)) {
            $r = new \ReflectionClass(static::class);
            if (!is_file($dir = $r->getFileName())) {
                throw new HtmlException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name), 500);
            }
            static::$work_dir = \dirname($dir, 2) . '/';
        }
        return static::$work_dir;
    }

    public static function sql(): PDO
    {
        return static::container()->get(Database\Pgsql::class)->getPDO();
    }

    public static function redis(): Redis
    {
        return static::container()->get(Database\Redis::class)->getRedis();
    }

    public static function getSession(): Session
    {
        $redis = static::container()->get(Database\Redis::class);
        $name = getenv('SESSION_NAME') ?: static::SESSION_NAME;
        $domain = getenv('SESSION_DOMAIN') ?: '';
        $secure = !empty(getenv('SESSION_SECURE'));
        $samesite = getenv('SESSION_SAMESITE') ?: 'Lax';
        $session = new Session(
            new Cookie($name . '_access_token', static::SESSION_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
            new Cookie($name . '_refresh_token', static::REFRESH_TOKEN_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
            new TokenStorageRedis($redis, static::VAR_PREFIX . ':Session:Token:', static::SESSION_LIFETIME),
            new TokenStorageRedis($redis, static::VAR_PREFIX . ':Session:RefreshToken:', static::REFRESH_TOKEN_LIFETIME),
            new RevokeTokenStorageRedis($redis, static::VAR_PREFIX . ':Session:RevokeToken:', static::REFRESH_TOKEN_LIFETIME)
        );
        if ($admins = getenv('APP_ADMINS')) {
            $session->setAdmins(explode(',', $admins));
        }
        return $session;
    }

    public static function filterInput(object &$object, int $type = INPUT_POST): FilterInput
    {
        return new FilterInput($object, $type);
    }

    final public static function container(): Container
    {
        if (is_null(static::$container)) {
            static::$container = static::getContainer();
        }
        return static::$container;
    }

    public static function getContainer(): Container
    {
        return new Container(
            [
                Database\Pgsql::class => fn () => new Database\Pgsql(getenv('DATABASE_URL'), getenv('TZ')),
                Database\Redis::class => fn () => new Database\Redis(getenv('REDIS_URL')),
                VarStorageInterface::class => fn () => new RedisStorage(static::container()->get(Database\Redis::class), static::VAR_PREFIX . ':Vars:'),
                Session::class => fn () => static::getSession(),
                ResponseHtml::class => fn () => static::getResponseHtml(),
                ResponseJson::class => fn () => static::getResponseJson()
            ],
            [],
            require static::getWorkDir() . 'settings.php'
        );
    }

    public static function route(string $controller, string $url_path)
    {
        $path = explode('/', 'root' . $url_path);
        $view = new $controller($path, static::container());
        $view->route();
        static::container()->get(ResponseHtml::class)->returnError(404);
    }
}
