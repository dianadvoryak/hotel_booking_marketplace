<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Model>
 */
class HotelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Создает нового пользователя-владельца, если он не передан явным образом
            'owner_id' => User::factory(),
            'name' => $this->faker->company() . ' Hotel',
            'description' => $this->faker->paragraph(),
            'city' => $this->faker->randomElement(['Москва', 'Санкт-Петербург', 'Казань', 'Сочи']),
            'address' => $this->faker->streetAddress(),
            'rating' => $this->faker->randomFloat(2, 3, 5), // Рейтинг от 3.00 до 5.00
        ];
    }
}
