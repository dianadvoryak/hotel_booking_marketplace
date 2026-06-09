<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Создаем дефолтных пользователей для тестов
        User::factory()->create([
            'name' => 'John Customer',
            'email' => 'client@example.com',
            'role' => 'customer',
        ]);

        $owner = User::factory()->create([
            'name' => 'Hotel Owner',
            'email' => 'owner@example.com',
            'role' => 'hotel_owner',
        ]);

        // 2. Создаем еще 10 случайных клиентов
        User::factory()->count(10)->create(['role' => 'customer']);

        // 3. Создаем 5 отелей для нашего владельца
        $hotels = Hotel::factory()
            ->count(5)
            ->create(['owner_id' => $owner->id]);

        // Массив для массовой вставки в таблицу доступности (для оптимизации скорости сидера)
        $availabilityData = [];
        $today = Carbon::today();

        // 4. Для каждого отеля создаем типы номеров и генерируем сетку доступности
        foreach ($hotels as $hotel) {
            // Создаем по 3 категории номеров в каждом отеле
            $roomTypes = RoomType::factory()
                ->count(3)
                ->create(['hotel_id' => $hotel->id]);

            foreach ($roomTypes as $roomType) {
                // Заполняем сетку доступности на 30 дней вперед
                for ($i = 0; $i < 30; $i++) {
                    $availabilityData[] = [
                        'room_type_id' => $roomType->id,
                        'date' => $today->copy()->addDays($i)->toDateString(),
                        'booked_count' => 0, // Изначально все номера свободны
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Вставляем данные пачкой (Bulk Insert), чтобы не делать тысячи запросов к БД
        foreach (array_chunk($availabilityData, 500) as $chunk) {
            DB::table('room_availability')->insert($chunk);
        }
    }
}
