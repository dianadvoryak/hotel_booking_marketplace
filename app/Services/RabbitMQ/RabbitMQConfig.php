<?php

namespace App\Services\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitMQConfig
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * Получить активный и настроенный канал RabbitMQ
     * @throws \Exception
     */
    public function getChannel(): AMQPChannel
    {
        if ($this->channel && $this->channel->is_open()) {
            return $this->channel;
        }

        // 1. Создаем соединение с брокером (данные берем из .env)
        $this->connection = new AMQPStreamConnection(
            config('queue.connections.rabbitmq.host', '127.0.0.1'),
            config('queue.connections.rabbitmq.port', 5672),
            config('queue.connections.rabbitmq.user', 'guest'),
            config('queue.connections.rabbitmq.password', 'guest')
        );

        $this->channel = $this->connection->channel();

        // 2. Инициализируем топологию (Обменники и Очереди)
        $this->registerTopology();

        return $this->channel;
    }

    /**
     * Декларируем обменники, очереди и связи между ними
     */
    private function registerTopology(): void
    {
        // Создаем EXCHANGE (Обменник) для бронирований
        // 'direct' означает, что сообщения будут доставляться строго по совпадению routing_key
        $this->channel->exchange_declare(
            exchange: 'bookings.exchange',
            type: 'direct',
            passive: false,
            durable: true, // Сохранять обменник при перезапуске RabbitMQ
            auto_delete: false
        );

        // Создаем QUEUE (Очередь) для генерации ваучеров
        $this->channel->queue_declare(
            queue: 'bookings.vouchers.queue',
            passive: false,
            durable: true, // Очередь не пропадет при перезагрузке брокера
            exclusive: false,
            auto_delete: false
        );

        // Связываем очередь с обменником с помощью Ключа Маршрутизации (Routing Key)
        $this->channel->queue_bind(
            queue: 'bookings.vouchers.queue',
            exchange: 'bookings.exchange',
            routing_key: 'booking.created'
        );
    }

    /**
     * Закрываем соединения при уничтожении класса
     */
    public function __destruct()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
