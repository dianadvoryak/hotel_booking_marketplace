<?php

namespace Tests\Feature;

use App\Services\HotelService;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HotelServiceTest extends TestCase
{
    use RefreshDatabase;

    private HotelService $hotelService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->hotelService = app(HotelService::class);
    }

    /**
     * Тест: Проверяем, что список популярных отелей кэшируется в Redis
     * и при повторном вызове база данных не нагружается.
     */
    public function test_popular_hotels_are_cached_in_redis(): void
    {
        // Создаем популярный отель
        Hotel::factory()->create(['rating' => 5.0, 'name' => 'Cache Hotel']);

        // Первый вызов — данные берутся из БД и записываются в Redis
        $firstCall = $this->hotelService->getPopularHotels();
        $this->assertCount(1, $firstCall);
        $this->assertEquals('Cache Hotel', $firstCall[0]['name']);

        // Проверяем, что в Redis создался ключ кэша
        $this->assertTrue(Cache::has('hotels:popular'));

        // Удаляем отель из базы данных PostgreSQL напрямую!
        Hotel::query()->delete();

        // Второй вызов — данные ДОЛЖНЫ успешно вернуться из Redis, несмотря на то, что в БД пусто!
        $secondCall = $this->hotelService->getPopularHotels();
        $this->assertCount(1, $secondCall);
        $this->assertEquals('Cache Hotel', $secondCall[0]['name']);
    }
}
