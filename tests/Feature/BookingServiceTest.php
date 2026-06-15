<?php

namespace Tests\Feature;

use App\Services\BookingService;
use App\Models\User;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Exceptions\BookingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase; // Автоматически очищает БД PostgreSQL перед каждым тестом

    private BookingService $bookingService;
    private User $user;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем Redis перед каждым тестом, чтобы исключить влияние старых данных
        Cache::flush();

        $this->bookingService = app(BookingService::class);

        // Создаем тестовые данные в PostgreSQL через фабрики (или напрямую)
        $this->user = User::factory()->create();
        $hotel = Hotel::factory()->create();

        // Создаем тип номера, где всего 1 свободная комната
        $this->roomType = RoomType::factory()->create([
            'hotel_id' => $hotel->id,
            'total_rooms' => 1,
            'base_price' => 5000
        ]);
    }

    /**
     * Тест: Успешное удержание номера в Redis и последующее подтверждение в БД.
     */
    public function test_successful_hold_and_confirm_flow(): void
    {
        $checkIn = Carbon::now()->addMonths(1)->toDateString();
        $checkOut = Carbon::now()->addMonths(1)->addDays(2)->toDateString();

        // 1. Пытаемся занять номер (Hold)
        $holdResult = $this->bookingService->holdRoom(
            $this->user->id,
            $this->roomType->id,
            $checkIn,
            $checkOut
        );

        $this->assertArrayHasKey('reservation_token', $holdResult);
        $token = $holdResult['reservation_token'];

        // Проверяем, что в Redis действительно появился токен резерва
        $this->assertTrue(Cache::has("reserve:token:{$token}"));

        // 2. Подтверждаем бронь по токену (Confirm)
        $booking = $this->bookingService->confirmBooking($token);

        // Проверяем, что бронь записалась в PostgreSQL
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'user_id' => $this->user->id,
            'status' => 'paid'
        ]);

        // Проверяем, что после подтверждения токен из Redis удалился (память очищена)
        $this->assertFalse(Cache::has("reserve:token:{$token}"));
    }

    /**
     * Тест: Защита от овербукинга. Если комната одна и она удерживается в Redis,
     * второй пользователь должен получить ошибку.
     */
    public function test_it_prevents_overbooking_using_redis_locks(): void
    {
        $checkIn = Carbon::now()->addMonths(1)->toDateString();
        $checkOut = Carbon::now()->addMonths(1)->addDays(2)->toDateString();

        // Первый пользователь успешно замораживает единственную комнату
        $this->bookingService->holdRoom(
            $this->user->id,
            $this->roomType->id,
            $checkIn,
            $checkOut
        );

        // Создаем второго пользователя, который пытается перехватить эту же комнату
        $secondUser = User::factory()->create();

        // Ожидаем, что система выбросит наше кастомное исключение BookingException
        $this->expectException(BookingException::class);

        // Второму пользователю должно быть отказано, так как комната занята в Redis
        $this->bookingService->holdRoom(
            $secondUser->id,
            $this->roomType->id,
            $checkIn,
            $checkOut
        );
    }
}
