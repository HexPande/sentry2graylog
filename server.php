<?php

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';

$server = new Server('0.0.0.0', 80);

$server->on('request', function (Request $request, Response $response) {
    $path = trim($request->server['request_uri'], '/');
    var_dump($request->getData());

    if ($path === 'webhook') {
        if ($body = $request->getContent()) {
            $payload = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response->header("Content-Type", "text/plain");
                $response->end("WebHook: " . $path);
                return;
            }
        }
    }

    $response->end('');
});

$server->start();