<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use chatApp\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';



$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    1337
);

try {
    $server->run();
} catch (Exception $e) {
    echo "HEFTIGER ERROR --------- $e".PHP_EOL;
}

