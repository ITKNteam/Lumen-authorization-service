<?php

namespace App;

class Configuration {
    private $config = null;

    function __construct() {
        $json = file_get_contents(dirname(__FILE__) . '/../config.json');

        $this->config = $json ? json_decode($json, true) : [
            'redis' =>  [
                'host' => '127.0.0.1',
                'port' => '6379'
            ]
        ];
    }

    public function getRedis() {
        return $this->config['redis'];
    }
}