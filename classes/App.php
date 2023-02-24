<?php

namespace FSA\Neuron;

use Redis;
use RedisException;

abstract class App
{
    const ERR_ACCESS_DENIED = 'Неверное имя пользователя или пароль.';
    const ERR_INTERNAL_SERVER_ERROR = 'Внутренняя ошибка сервера';
    const SESSION_LIFETIME = 1800;
    const REFRESH_TOKEN_LIFETIME = 2592000;

    protected static $db;
    protected static $redis;
    protected static $response;
    protected static $settings;
    protected static $session;
    protected static $var;
    protected static $work_dir;

    /** Префикс для переменных */
    abstract protected static function constVarPrefix(): string;
    /** Префикс для Cookie с данными сессии */
    abstract protected static function constSessionName(): string;
    /** Путь до рабочего каталога */
    abstract protected static function constWorkDir(): string;

    /** Массив с данными для шаблонов */
    protected static function getContext(): ?array
    {
        return null;
    }

    /** Классы с шаблонами страниц */
    protected static function getTemplates(): array
    {
        return [
            \Templates\Main::class,
            \Templates\Login::class,
            \Templates\Message::class
        ];
    }

    /** Инициализация приложения. Может быть расширено. */
    public static function init()
    {
        if ($tz = getenv('TZ')) {
            date_default_timezone_set($tz);
        }
    }

    public static function initHtml(...$templates): ResponseHtml
    {
        static::init();
        static::$response = new ResponseHtml(...array_replace(static::getTemplates(), array_slice($templates, 0, 3)));
        if ($context = static::getContext()) {
            static::$response->setContext($context);
        }
        set_exception_handler([static::class, 'exceptionHandler']);
        return static::$response;
    }

    public static function initJson(): ResponseJson
    {
        static::init();
        static::$response = new ResponseJson;
        set_exception_handler([static::class, 'exceptionHandler']);
        return static::$response;
    }

    public static function response()
    {
        return static::$response;
    }

    public static function getWorkDir()
    {
        if (!static::$work_dir) {
            static::$work_dir = static::constWorkDir() . '/';
        }
        return static::$work_dir;
    }

    public static function getSettings(string $name, $default_value = null)
    {
        if (is_null(static::$settings)) {
            static::$settings = require static::getWorkDir() . 'settings.php';
        }
        return static::$settings[$name] ?? $default_value;
    }

    public static function sql(): PostgreSQL
    {
        if (is_null(static::$db)) {
            static::$db = new PostgreSQL(getenv('DATABASE_URL'));
            if ($tz = getenv('TZ')) {
                static::$db->query("SET TIMEZONE=\"$tz\"");
            }
        }
        return static::$db;
    }

    public static function sqlCallback(): callable
    {
        return [static::class, 'sql'];
    }

    public static function redis(): Redis
    {
        if (is_null(static::$redis)) {
            $url = getenv('REDIS_URL');
            $db = (!$url) ? ['host' => '127.0.0.1'] : parse_url($url);
            try {
                $redis = new Redis;
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
            static::$redis = $redis;
        }
        return static::$redis;
    }

    public static function redisCallback(): callable
    {
        return [static::class, 'redis'];
    }

    public static function session(): Session
    {
        if (is_null(static::$session)) {
            $name = getenv('SESSION_NAME') ?: static::constSessionName();
            $domain = getenv('SESSION_DOMAIN') ?: '';
            $secure = !empty(getenv('SESSION_SECURE'));
            $samesite = getenv('SESSION_SAMESITE') ?: 'Lax';
            static::$session = new Session(
                new Cookie($name . '_access_token', static::SESSION_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
                new Cookie($name . '_refresh_token', static::REFRESH_TOKEN_LIFETIME, domain: $domain, secure: $secure, samesite: $samesite),
                new TokenStorageRedis(static::redisCallback(), static::constVarPrefix() . ':Session:Token:', static::SESSION_LIFETIME),
                new TokenStorageRedis(static::redisCallback(), static::constVarPrefix() . ':Session:RefreshToken:', static::REFRESH_TOKEN_LIFETIME),
                new RevokeTokenStorageRedis(static::redisCallback(), static::constVarPrefix() . ':Session:RevokeToken:', static::REFRESH_TOKEN_LIFETIME)
            );
            if ($admins = getenv('APP_ADMINS')) {
                static::$session->setAdmins(explode(',', $admins));
            }
        }
        return static::$session;
    }

    public static function login($login, $password)
    {
        $user = new User(self::sql());
        if (!$user->login($login, $password)) {
            self::response()->returnError(401, self::ERR_ACCESS_DENIED);
            exit;
        }
        self::session()->login($user);
    }

    public static function logout()
    {
        self::session()->logout();
    }

    public static function filterInput(object &$object, int $type = INPUT_POST): FilterInput
    {
        return new FilterInput($object, $type);
    }

    public static function getEntityClass(string $name): string
    {
        return match ($name) {
            'users' => UserDB\Users::class,
            'groups' => UserDB\Groups::class,
            'scopes' => UserDB\Scopes::class
        };
    }

    public static function getObject($type): ?object
    {
        return match ($type) {
            ResponseHtml::class => static::initHtml(),
            ResponseJson::class => static::initJson(),
            Session::class => static::session(),
            VarsStorageInterface::class => static::var(),
            default => null
        };
    }

    public static function newEntity(string $name)
    {
        $class = static::getEntityClass($name);
        return new $class;
    }

    public static function fetchEntity(string $name, string|array $where)
    {
        return static::sql()->fetchEntity(static::getEntityClass($name), $where);
    }

    public static function fetchKeyPair(string $name)
    {
        return static::sql()->fetchKeyPair(static::getEntityClass($name));
    }

    public static function var(): VarsStorageInterface
    {
        if (!isset(static::$var)) {
            static::$var = new RedisStorage(static::constVarPrefix() . ':Vars:', static::redisCallback());
        }
        return static::$var;
    }

    public static function exceptionHandler($ex)
    {
        $class = get_class($ex);
        $class_parts = explode('\\', $class);
        if (end($class_parts) == 'UserException') {
            static::response()->returnError(200, $ex->getMessage());
        } else if (end($class_parts) == 'HtmlException') {
            static::response()->returnError($ex->getCode(), $ex->getMessage(), method_exists($ex, 'getDescription') ? $ex->getDescription() : null);
        } else if (getenv('DEBUG')) {
            error_log($ex, 0);
            static::response()->returnError(500, '<pre>' . (string) $ex . '</pre>');
        } else {
            error_log($ex, 0);
            static::response()->returnError(500, self::ERR_INTERNAL_SERVER_ERROR);
        }
        exit;
    }
}
