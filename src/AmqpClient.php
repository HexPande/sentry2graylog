<?php

namespace App;

use Exception;
use Gelf\Message;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class AmqpClient
{
    private array $config;
    private AbstractConnection $connection;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @throws Exception
     */
    public function push(Message $message): void
    {
        $msg = new AMQPMessage(json_encode($message->toArray()), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json',
        ]);

        $this->getChannel()->basic_publish($msg, $this->config['exchange']);
    }

    /**
     * @throws Exception
     */
    private function getChannel(): AMQPChannel
    {
        $channelId = 1;
        $connection = $this->getConnection();
        $channel = $connection->channel($channelId);

        if (!$channel->is_open()) {
            $channel->close();
            $channel = $connection->channel($channelId);
        }

        return $channel;
    }

    /**
     * @throws Exception
     */
    private function createConnection(): AbstractConnection
    {
        return new AMQPSocketConnection(
            host: $this->config['host'],
            port: $this->config['port'],
            user: $this->config['user'],
            password: $this->config['password'],
            vhost: $this->config['vhost'],
            keepalive: true
        );
    }

    /**
     * @throws Exception
     */
    private function getConnection(): AbstractConnection
    {
        if (empty($this->connection)) {
            return $this->connection = $this->createConnection();
        }

        if (!$this->connection->isConnected()) {
            return $this->connection = $this->createConnection();
        }

        return $this->connection;
    }
}
