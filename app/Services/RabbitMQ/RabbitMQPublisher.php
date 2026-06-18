<?php

namespace App\Services\RabbitMQ;

use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private RabbitMQConfig $config;

    public function __construct(RabbitMQConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Опубликовать сообщение в обменник
     */
    public function publish(string $exchange, string $routingKey, array $payload): void
    {
        $channel = $this->config->getChannel();

        // Переводим массив данных в JSON строку
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Создаем AMQP сообщение с флагом доставки (delivery_mode = 2 делает сообщение постоянным/durable)
        $message = new AMQPMessage($jsonPayload, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Отправляем в RabbitMQ
        $channel->basic_publish($message, $exchange, $routingKey);
    }
}
