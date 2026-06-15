<?php

namespace Tests\Feature\Api;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HotelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Тест: GET /api/hotels/popular возвращает правильную структуру JSON
     */
    public function test_can_get_popular_hotels(): void
    {
        // Создаем отель с высоким рейтингом
        Hotel::factory()->create([
            'name' => 'Grand Royal',
            'rating' => 4.8,
            'city' => 'Москва',
            'address' => 'ул. Ленина 1'
        ]);

        $response = $this->getJson('/api/hotels/popular');

        // Проверяем статус и структуру ответа в соответствии с HotelPopularResource
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'city', 'address', 'rating']
            ])
            ->assertJsonFragment(['name' => 'Grand Royal']);
    }

    /**
     * Тест: GET /api/rooms/feed возвращает порцию данных с курсором для скролла
     */
    public function test_can_get_rooms_feed_with_cursor(): void
    {
        $hotel = Hotel::factory()->create();
        RoomType::factory()->create([
            'hotel_id' => $hotel->id,
            'base_price' => 3000
        ]);

        // Делаем запрос к нашей бесконечной ленте
        $response = $this->getJson('/api/rooms/feed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'base_price',
                        'hotel' => ['id', 'name', 'city', 'rating']
                    ]
                ],
                'next_cursor',
                'has_more'
            ]);
    }
}
