<?php

namespace FSA\Neuron;

use Composer\Autoload\ClassLoader;

class Settings {

    private static $_instance=null;
    private $settings;

    private function __clone() {
        
    }

    private function __construct() {
        # Hack?
        $loaders=ClassLoader::getRegisteredLoaders();
        $this->settings=require array_key_first($loaders).'/../settings.php';
    }

    public static function getInstance() {
        if (self::$_instance===null) {
            self::$_instance=new self;
        }
        return self::$_instance;
    }

    public static function get(string $name,$default_value=null) {
        $s=self::getInstance();
        if (isset($s->settings[$name])) {
            return $s->settings[$name];
        }
        return $default_value;
    }

}
