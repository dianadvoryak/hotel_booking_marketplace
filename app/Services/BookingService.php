<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Models\RoomAvailability;
use App\Exceptions\BookingException;
use App\Jobs\ProcessNewBookingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BookingService
{
    /**
     * Создать бронирование с защитой от race conditions
     *
     * @throws BookingException
     */
    public function createBooking(string $userId, string $hotelId, string $roomTypeId, string $checkIn, string $checkOut): Booking
    {
        $startDate = Carbon::parse($checkIn);
        $endDate = Carbon::parse($checkOut);

        // Получаем все ночи проживания (исключая дату выезда для расчета доступности)
        $period = CarbonPeriod::create($startDate, $endDate->copy()->subDay());
        $dates = collect($period)->map(fn($date) => $date->toDateString())->toArray();

        // 1. ИСПОЛЬЗУЕМ REDIS LOCK
        // Создаем уникальный ключ блокировки для конкретного типа комнаты
        $lockKey = "lock:room_type:{$roomTypeId}";

        // Пытаемся взять блокировку на 5 секунд. Если занято — выбрасываем Exception
        $lock = Cache::store('redis')->lock($lockKey, 5);

        if (!$lock->get()) {
            throw BookingException::dynamicLockFailed();
        }

        try {
            // 2. ЗАПУСКАЕМ ТРАНЗАКЦИЮ БАЗЫ ДАННЫХ
            return DB::transaction(function () use ($userId, $hotelId, $roomTypeId, $startDate, $endDate, $dates) {

                // Получаем информацию о типе комнаты с блокировкой строки (FOR UPDATE)
                $roomType = RoomType::where('id', $roomTypeId)->lockForUpdate()->firstOrFail();

                // Получаем текущую занятость комнат на выбранные даты
                $availabilities = RoomAvailability::where('room_type_id', $roomTypeId)
                    ->whereIn('date', $dates)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('date');

                // Проверяем доступность на каждый день
                foreach ($dates as $date) {
                    $bookedCount = isset($availabilities[$date]) ? $availabilities[$date]->booked_count : 0;

                    // Если забронировано больше или равно, чем всего комнат в отеле — овербукинг!
                    if ($bookedCount >= $roomType->total_rooms) {
                        throw BookingException::noRoomsAvailable();
                    }
                }

                // Расчет стоимости
                $nightsCount = count($dates);
                $totalPrice = $roomType->base_price * $nightsCount;

                // Создаем шапку бронирования
                $booking = Booking::create([
                    'user_id' => $userId,
                    'hotel_id' => $hotelId,
                    'status' => 'pending',
                    'total_price' => $totalPrice,
                ]);

                // Создаем детали бронирования
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'room_type_id' => $roomTypeId,
                    'check_in_date' => $startDate->toDateString(),
                    'check_out_date' => $endDate->toDateString(),
                    'price_per_night' => $roomType->base_price,
                ]);


                foreach ($dates as $date) {
                    $availability = RoomAvailability::where('room_type_id', $roomTypeId)
                        ->where('date', $date)
                        ->first();

                    if ($availability) {
                        // Выполнит чистый UPDATE ... SET booked_count = booked_count + 1
                        $availability->increment('booked_count');
                    } else {
                        // Выполнит корректный INSERT со значением 1
                        RoomAvailability::create([
                            'room_type_id' => $roomTypeId,
                            'date' => $date,
                            'booked_count' => 1,
                        ]);
                    }
                }



                // 3. ОТПРАВЛЯЕМ СОБЫТИЕ В RABBITMQ
                // dispatch_after_commit выполнит отправку в очередь ТОЛЬКО после того,
                // как транзакция успешно запишется в базу данных (Senior-практика!)
                ProcessNewBookingJob::dispatch($booking->id)->afterCommit();

                return $booking;
            });

        } finally {
            // В блоке finally ОВЯЗАТЕЛЬНО отпускаем Redis Lock, что бы ни случилось
            $lock->release();
        }
    }

    /**
     * Зарезервировать номер на 10 минут для оплаты
     * @throws BookingException
     */
    public function holdRoom(string $userId, string $roomTypeId, string $checkIn, string $checkOut): array
    {
        $startDate = Carbon::parse($checkIn);
        $endDate = Carbon::parse($checkOut);
        $period = CarbonPeriod::create($startDate, $endDate->copy()->subDay());
        $dates = collect($period)->map(fn($date) => $date->toDateString())->toArray();

        // Senior-логика: создаем уникальный токен резерва
        $token = (string) str()->uuid();
        $roomType = RoomType::findOrFail($roomTypeId);

        // 1. Проверяем каждую дату на наличие ЖЕСТКИХ броней в БД + ВРЕМЕННЫХ резервов в Redis
        foreach ($dates as $date) {
            // Сколько забронировано в БД
            $dbBooked = RoomAvailability::where('room_type_id', $roomTypeId)
                ->where('date', $date)
                ->value('booked_count') ?? 0;

            // Сколько СЕЙЧАС удерживается в Redis другими пользователями (счетчик в Redis)
            $redisHoldKey = "rooms:hold:{$roomTypeId}:{$date}";
            $redisHoldCount = (int) Cache::get($redisHoldKey, 0);

            // Если сумма броней и резервов превышает общее кол-во комнат
            if (($dbBooked + $redisHoldCount) >= $roomType->total_rooms) {
                throw BookingException::noRoomsAvailable();
            }
        }

        // 2. Если места есть — атомарно увеличиваем счетчик удерживаемых комнат в Redis на 10 минут (600 секунд)
        foreach ($dates as $date) {
            $redisHoldKey = "rooms:hold:{$roomTypeId}:{$date}";

            // Если ключа нет, инициализируем его с TTL 10 минут, иначе просто инкрементируем
            if (!Cache::has($redisHoldKey)) {
                Cache::put($redisHoldKey, 1, 600);
            } else {
                Cache::increment($redisHoldKey);
            }
        }

        // 3. Сохраняем информацию о самом резерве в Redis, чтобы знать, какие даты и сколько комнат отпустить при отмене/оплате
        $reserveKey = "reserve:token:{$token}";
        Cache::put($reserveKey, [
            'user_id' => $userId,
            'room_type_id' => $roomTypeId,
            'dates' => $dates
        ], 600);

        return [
            'reservation_token' => $token,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'message' => 'Номер успешно заморожен на 10 минут. Ожидание оплаты.'
        ];
    }
}
