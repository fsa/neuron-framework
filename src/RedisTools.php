<?php

namespace FSA\Neuron;

use Redis;

class RedisTools
{

    public function __construct(private Redis $redis)
    {
    }

    public function searchKeys($search_key)
    {
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $it = NULL;
        $result = [];
        while ($arr_keys = $this->redis->scan($it, $search_key)) {
            foreach ($arr_keys as $str_key) {
                $result[] = $str_key;
            }
        }
        return $result;
    }

    public function deleteKeys($search_key)
    {
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $it = NULL;
        $count = 0;
        while ($arr_keys = $this->redis->scan($it, $search_key)) {
            foreach ($arr_keys as $str_key) {
                $this->redis->del($str_key);
                $count++;
            }
        }
        return $count;
    }
}
