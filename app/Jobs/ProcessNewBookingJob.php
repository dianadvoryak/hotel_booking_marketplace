<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class ProcessNewBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $bookingId;

    public function __construct(string $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    /**
     * Логика обработки задачи
     */
    public function handle(): void
    {
        // 1. Находим бронь со всеми связями из БД
        $booking = Booking::with(['user', 'hotel', 'items.roomType'])->find($this->bookingId);

        if (!$booking) {
            Log::error("RabbitMQ Job: Бронирование {$this->bookingId} не найдено.");
            return;
        }

        // 2. Формируем B2B-пакет данных (Data Transfer Object в виде массива)
        $externalPayload = [
            'event' => 'booking.created_successfully',
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'booking_id' => $booking->id,
                'total_price_cents' => $booking->total_price,
                'status' => $booking->status,
                'hotel' => [
                    'id' => $booking->hotel->id,
                    'name' => $booking->hotel->name,
                    'city' => $booking->hotel->city,
                ],
                'customer' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                ],
                'items' => $booking->items->map(fn($item) => [
                    'room_type_name' => $item->roomType->name,
                    'check_in' => $item->check_in_date,
                    'check_out' => $item->check_out_date,
                    'price_per_night_cents' => $item->price_per_night,
                ])->toArray(),
            ]
        ];

        // 3. ОТПРАВЛЯЕМ RAW-СООБЩЕНИЕ В RABBITMQ
        /** @var RabbitMQQueue $rabbitQueue */
        $rabbitQueue = Queue::connection('rabbitmq');

        // Отправляем в специальную выделенную очередь для внешних интеграций (например, 'external_sync')
        $targetQueue = 'external_sync';
        $rabbitQueue->pushRaw(json_encode($externalPayload), $targetQueue);

        // Логируем для локальной отладки
        Log::info("==================================================");
        Log::info("📬 JOB: Бронь {$booking->id} обработана внутренне.");
        Log::info("🚀 JOB: Данные успешно экспортированы в RabbitMQ в очередь '{$targetQueue}'!");
        Log::info("==================================================");
    }
}
