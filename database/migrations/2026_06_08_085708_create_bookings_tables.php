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
        // Главная таблица заказа
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Связи: кто бронирует и какой отель
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('hotel_id')->constrained('hotels')->restrictOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'paid'])->default('pending');
            $table->integer('total_price'); // Общая сумма в копейках
            $table->timestamps();

            $table->index('status'); // Индекс для фильтрации по статусам в админке
        });

        // Детализация комнат в заказе
        Schema::create('booking_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Связи: к какому заказу относится и какой тип номера выбран
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('price_per_night'); // Фиксируем цену на момент бронирования
            $table->timestamps();

            // Индекс для быстрого поиска броней на конкретные даты
            $table->index(['check_in_date', 'check_out_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
