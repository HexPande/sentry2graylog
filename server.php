#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\AmqpClient;
use Gelf\Message;
use Illuminate\Support\Arr;
use Psr\Log\LogLevel;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

require_once __DIR__ . '/vendor/autoload.php';

$amqp = new AmqpClient([
    'host' => getenv('AMQP_HOST'),
    'port' => getenv('AMQP_PORT'),
    'user' => getenv('AMQP_USER'),
    'password' => getenv('AMQP_PASSWORD'),
    'vhost' => getenv('AMQP_VHOST'),
    'exchange' => getenv('AMQP_EXCHANGE'),
]);

$server = new Server('0.0.0.0', 80);

$server->on('start', function () {
    echo sprintf('Swoole http server is started at http://127.0.0.1:%s' . PHP_EOL, getenv('APP_PORT'));
});

$server->on('request', function (Request $request, Response $response) use ($amqp) {
    $path = trim($request->server['request_uri'], '/');
    $method = mb_strtoupper($request->server['request_method']);

    if ($method === 'GET' && $path === 'webhook') {
        $resource = $request->header['sentry-hook-resource'];

        if ($resource === 'metric_alert') {
            $payload = json_decode($request->getContent(), true, JSON_THROW_ON_ERROR);

            $prefix = '[alert] [sentry] ';
            $title = Arr::get($payload, 'data.metric_alert.alert_rule.name');
            $description = Arr::get($payload, 'data.description_text');
            $action = Arr::get($payload, 'action');

            $message = new Message();
            $message->setTimestamp(time());
            $message->setLevel(LogLevel::ALERT);
            $message->setHost('sentry.lptracker.ru');
            $message->setShortMessage($prefix . $title . ' (' . $action . ')');
            $message->setFullMessage($prefix . $title . ': ' . $description);
            $message->setAdditional('env_name', Arr::get($payload, 'data.metric_alert.alert_rule.environment'));
            $message->setAdditional('data_url', Arr::get($payload, 'data.web_url'));
            $message->setAdditional('data_status', Arr::get($payload, 'action'));
            $message->setAdditional('data_event', 'sentry:alert');

            $amqp->push($message);
        }

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['result' => 'OK']));
        return;
    }

    $response->status(404);
    $response->end('');
});

$server->start();
