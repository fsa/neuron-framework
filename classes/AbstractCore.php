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
        return static::container()->get(PDO::class);
    }

    public static function sqlCallback(): callable
    {
        return static::sql(...);
    }

    public static function redis(): Redis
    {
        return static::container()->get(Redis::class);
    }

    public static function redisCallback(): callable
    {
        return static::redis(...);
    }

    public static function getSession(): Session
    {
        $name = getenv('SESSION_NAME') ?: static::SESSION_NAME;
        $domain = getenv('SESSION_DOMAIN') ?: '';
        $secure = !empty(getenv('SESSION_SECURE'));
        $samesite = getenv('SESSION_SAMESITE') ?: 'Lax';
        $session = new Session(
            new Cookie($name . '_access_token', static::SESSION_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
            new Cookie($name . '_refresh_token', static::REFRESH_TOKEN_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
            new TokenStorageRedis(static::redisCallback(), static::VAR_PREFIX . ':Session:Token:', static::SESSION_LIFETIME),
            new TokenStorageRedis(static::redisCallback(), static::VAR_PREFIX . ':Session:RefreshToken:', static::REFRESH_TOKEN_LIFETIME),
            new RevokeTokenStorageRedis(static::redisCallback(), static::VAR_PREFIX . ':Session:RevokeToken:', static::REFRESH_TOKEN_LIFETIME)
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
                PDO::class => fn () => static::getPDO(),
                Redis::class => fn () => static::getRedis(),
                Settings::class => fn () => static::getSettings(),
                VarStorageInterface::class => fn () => static::getVar(),
                Session::class => fn () => static::getSession(),
                ResponseHtml::class => fn () => static::getResponseHtml(),
                ResponseJson::class => fn () => static::getResponseJson()
            ],
            []
        );
    }

    public static function route(string $controller, string $url_path)
    {
        $path = explode('/', 'root' . $url_path);
        $view = new $controller($path, static::container());
        $view->route();
        static::container()->get(ResponseHtml::class)->returnError(404);
    }

    public static function getVar(): VarStorageInterface
    {
        return new RedisStorage(static::VAR_PREFIX . ':Vars:', static::redisCallback());
    }

    protected static function getSettings(): Settings
    {
        return new Settings(static::getWorkDir() . 'settings.php');
    }

    protected static function getPDO(): PDO
    {
        $url = getenv('DATABASE_URL');
        if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            throw new HtmlException('Database is not configured.', 500);
        }
        $db = parse_url($url);
        $pdo = new PDO(sprintf(
            "pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
            $db['host'],
            $db['port'] ?? 5432,
            $db['user'],
            $db['pass'],
            ltrim($db["path"], "/")
        ));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($tz = getenv('TZ')) {
            $pdo->query("SET TIMEZONE=\"$tz\"");
        }
        return $pdo;
    }

    protected static function getRedis(): Redis
    {
        $url = getenv('REDIS_URL');
        $db = (!$url) ? ['host' => '127.0.0.1'] : parse_url($url);
        try {
            $redis = new Redis();
            $scheme = (isset($db['scheme']) and $db['scheme'] == 'rediss') ? "tls://" : '';
            $redis->connect($scheme . $db["host"], $db["port"] ?? 6379);
        } catch (RedisException $ex) {
            throw new HtmlException('Redis connect failed: ' . $ex->getMessage(), 500);
        }
        if (!empty($db["pass"])) {
            if (empty($db["user"])) {
                $result = $redis->auth($db["pass"]);
            } else {
                $result = $redis->auth([$db["user"], $db["pass"]]);
            }
            if (!$result) {
                throw new HtmlException('Redis auth failed', 500);
            }
        }
        if (!empty($db["path"])) {
            $redis->select((int)ltrim($db['path'], '/'));
        }
        return $redis;
    }
}
