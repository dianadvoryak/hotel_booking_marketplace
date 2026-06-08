<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Таблица отелей
        Schema::create('hotels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Связь: отель принадлежит пользователю-владельцу
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('city');
            $table->string('address');
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->timestamps();

            // Индекс для поиска отелей по городам (самый частый запрос)
            $table->index('city');
        });

        // Таблица категорий номеров
        Schema::create('room_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Связь: номер принадлежит конкретному отелю
            $table->foreignUuid('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name'); // Люкс, Стандарт и т.д.
            $table->integer('base_price'); // Цена в копейках/центах! (например, 500000 вместо 5000.00)
            $table->integer('capacity'); // Вместимость (кол-во человек)
            $table->integer('total_rooms'); // Сколько всего таких физических номеров есть в отеле
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels');
    }
};
