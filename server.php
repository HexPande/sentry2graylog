<?php

use App\AmqpClient;
use Gelf\Message;
use Illuminate\Support\Arr;
use Psr\Log\LogLevel;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

require_once __DIR__ . '/vendor/autoload.php';

$server = new Server('0.0.0.0', 80);
$amqp = new AmqpClient([
    'host' => getenv('AMQP_HOST'),
    'port' => getenv('AMQP_PORT'),
    'user' => getenv('AMQP_USER'),
    'password' => getenv('AMQP_PASSWORD'),
    'vhost' => getenv('AMQP_VHOST'),
    'exchange' => getenv('AMQP_EXCHANGE'),
]);

$server->on('request', function (Request $request, Response $response) use ($amqp) {
    $path = trim($request->server['request_uri'], '/');

    if ($path === 'webhook') {
        if ($request->header['sentry-hook-resource'] === 'metric_alert') {
            if ($body = $request->getContent()) {
                $payload = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $timestamp = $request->header['sentry-hook-timestamp'] ?? time();

                    $prefix = '[alert] [sentry] ';
                    $title = Arr::get($payload, 'data.metric_alert.alert_rule.name');
                    $description = Arr::get($payload, 'data.description_text');

                    $message = new Message();
                    $message->setTimestamp($timestamp);
                    $message->setLevel(LogLevel::ALERT);
                    $message->setHost('sentry.lptracker.ru');
                    $message->setShortMessage($prefix . $title);
                    $message->setFullMessage($prefix . $title . ': ' . $description);

                    $extra = [
                        'env_name' => Arr::get($payload, 'data.metric_alert.alert_rule.environment'),
                        'url' => Arr::get($payload, 'data.web_url'),
                        'status' => Arr::get($payload, 'action')
                    ];
                    foreach ($extra as $key => $value) {
                        $message->setAdditional('_data_' . $key, $value);
                    }

                    $amqp->push($message);

                    $response->header('Content-Type', 'application/json');
                    $response->end(json_encode(['result' => 'OK']));
                    return;
                }
            }
        }
    }

    $response->end('');
});

$server->start();