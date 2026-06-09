<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Model>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => $this->faker->randomElement(['Стандарт', 'Люкс', 'Семейный', 'Президентский']),
            // Цена в копейках/центах: от 3 000 до 25 000 рублей/долларов за ночь
            'base_price' => $this->faker->numberBetween(300000, 2500000),
            'capacity' => $this->faker->numberBetween(1, 4),
            'total_rooms' => $this->faker->numberBetween(5, 20), // Всего номеров такого типа в отеле
        ];
    }
}
