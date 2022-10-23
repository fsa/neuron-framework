<?php

namespace FSA\Neuron;

abstract class App
{
    const ERR_ACCESS_DENIED = 'Неверное имя пользователя или пароль.';
    const ERR_INTERNAL_SERVER_ERROR = 'Внутренняя ошибка сервера';

    protected static $db;
    protected static $redis;
    protected static $response;
    protected static $settings;
    protected static $session;

    abstract protected static function constVarPrefix(): string;
    abstract protected static function constSessionName(): string;
    abstract protected static function constSettingsFile(): string;

    public static function init()
    {
        if ($tz = getenv('TZ')) {
            date_default_timezone_set($tz);
        }
    }

    public static function initHtml($main_template, $login_template, $message_template): ResponseHtml
    {
        static::init();
        static::$response = new ResponseHtml($main_template, $login_template, $message_template);
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

    public static function getSettings(string $name, $default_value = null)
    {
        if (is_null(static::$settings)) {
            static::$settings = require static::constSettingsFile();
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

    public static function redis(): RedisDB
    {
        if (is_null(static::$redis)) {
            static::$redis = new RedisDB(getenv('REDIS_URL'));
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
            $storage = new SessionStorageRedis(static::constVarPrefix(), static::redis());
            static::$session = new Session(getenv('SESSION_NAME') ?: static::constSessionName(), $storage);
            static::$session->setCookieOptions([
                'path' => getenv('SESSION_PATH'),
                'domain' => getenv('SESSION_DOMAIN'),
                'secure' => !empty(getenv('SESSION_SECURE')),
                'httponly' => !empty(getenv('SESSION_HTTPONLY')),
                'samesite' => getenv('SESSION_SAMESITE')
            ]);
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

    public static function getVar($name)
    {
        return static::redis()->get(static::constVarPrefix() . ':Vars:' . $name);
    }

    public static function setVar($name, $value)
    {
        static::redis()->set(static::constVarPrefix() . ':Vars:' . $name, $value);
    }

    public static function getVarJson($name, $array = true)
    {
        $val = static::getVar($name);
        return json_decode($val, $array);
    }

    public static function setVarJson($name, $object)
    {
        static::setVar($name, json_encode($object, JSON_UNESCAPED_UNICODE));
    }

    public static function delVar($name)
    {
        return static::redis()->del(static::constVarPrefix() . ':Vars:' . $name);
    }

    public static function exceptionHandler($ex)
    {
        $class = get_class($ex);
        $class_parts = explode('\\', $class);
        if (end($class_parts) == 'UserException') {
            static::response()->returnError(200, $ex->getMessage());
        } else if (end($class_parts) == 'HtmlException') {
            static::response()->returnError($ex->getCode(), $ex->getMessage());
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
