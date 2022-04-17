<?php

namespace FSA\Neuron;

use Redis,
    RedisException;

class RedisDB extends Redis {

    public function __construct($url)
    {
        if (!$url) {
            $db = ['host' => '127.0.0.1'];
        } else {
            $db = parse_url($url);
        }
        try {
            parent::__construct();
            $scheme = (isset($db['scheme']) and $db['scheme'] == 'rediss') ? "tls://" : '';
            $this->connect($scheme . $db["host"], $db["port"] ?? 6379);
        } catch (RedisException $ex) {
            throw new HtmlException('Redis connect failed: ' . $ex->getMessage(), 500);
        }
        if (!empty($db["pass"])) {
            if (empty($db["user"])) {
                $result = $this->auth($db["pass"]);
            } else {
                $result = $this->auth([$db["user"], $db["pass"]]);
            }
            if (!$result) {
                throw new HtmlException('Redis auth failed', 500);
            }
        }
        if (!empty($db["path"])) {
            $this->select((int)ltrim($db['path'], '/'));
        }
    }

}
