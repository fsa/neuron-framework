<?php

namespace FSA\Neuron;

class DBRedis {

    private static $redis=null;

    private function __construct() {
    }

    private function __clone() {
    }

    public static function getInstance(): \Redis {
        if (self::$redis) {
            return self::$redis;
        }
        return self::connect(getenv('REDIS_URL'));
    }

    public static function connect($url=null) {
        if (!$url) {
            $db = ['host'=>'127.0.0.1'];
        }  else {
            $db = parse_url($url);
        }
        self::$redis=new \Redis();
        try {
            self::$redis->connect((empty($db['scheme'])?'':$db['scheme']."://").$db["host"], $db["port"] ?? 6379);
        } catch (\RedisException $ex) {
            throw new AppException('Redis connect failed: '.$ex->getMessage());
        }
        if(!empty($db["pass"])) {
            if(empty($db["user"])) {
                $result=self::$redis->auth($db["pass"]);
            } else {
                $result=self::$redis->auth([$db["user"], $db["pass"]]);
            }
            if(!$result) {
                throw new AppException('Redis auth failed');
            }
        }
        if(!empty($db["path"])) {
            self::$redis->select((int)ltrim($db['path'], '/'));
        }
        return self::$redis;
    }

    public static function isConnected(): bool {
        return !is_null(self::$redis);
    }

    public static function disconnect(): void {
        self::$redis=null;
    }

    public static function __callStatic($name, $args) {
        $callback=array(self::getInstance(), $name);
        return call_user_func_array($callback, $args);
    }

}
