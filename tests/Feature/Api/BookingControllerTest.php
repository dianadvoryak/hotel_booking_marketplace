<?php

namespace Tests\Feature\Api;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Создаем тестового пользователя для контроллера
        $this->user = User::factory()->create();
        $hotel = Hotel::factory()->create();
        $this->roomType = RoomType::factory()->create([
            'hotel_id' => $hotel->id,
            'total_rooms' => 1,
            'base_price' => 5000
        ]);
    }

    /**
     * Тест: Полный цикл через API — Холд -> Подтверждение
     */
    public function test_full_api_booking_flow(): void
    {
        $checkIn = Carbon::now()->addMonths(1)->toDateString();
        $checkOut = Carbon::now()->addMonths(1)->addDays(2)->toDateString();

        // 1. Тестируем POST /api/bookings/hold
        $holdResponse = $this->postJson('/api/bookings/hold', [
            'room_type_id' => $this->roomType->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        $holdResponse->assertStatus(200)
            ->assertJsonStructure(['reservation_token', 'expires_at', 'message']);

        $token = $holdResponse->json('reservation_token');

        // 2. Тестируем POST /api/bookings/confirm с полученным токеном
        $confirmResponse = $this->postJson('/api/bookings/confirm', [
            'reservation_token' => $token
        ]);

        // Ожидаем успешный статус создания брони в БД
        $confirmResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'status', 'total_price', 'hotel']
            ]);

        // Проверяем, что в БД статус изменился на оплачено
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->user->id,
            'status' => 'paid'
        ]);
    }

    /**
     * Тест: Валидация API должна вернуть 422, если даты переданы неверно
     */
    public function test_hold_endpoint_returns_validation_errors(): void
    {
        // Передаем дату выезда РАНЬШЕ даты заезда
        $response = $this->postJson('/api/bookings/hold', [
            'room_type_id' => $this->roomType->id,
            'check_in' => Carbon::now()->addMonths(1)->toDateString(),
            'check_out' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_out']);
    }
}
