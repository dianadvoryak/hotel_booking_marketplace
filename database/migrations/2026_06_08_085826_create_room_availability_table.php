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
        Schema::create('room_availability', function (Blueprint $table) {
            $table->bigIncrements('id'); // Здесь UUID не нужен, это чисто техническая таблица-календарь
            $table->foreignUuid('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('date');
            $table->integer('booked_count')->default(0); // Сколько номеров уже занято
            $table->timestamps();

            // Уникальный составной индекс: защищает от дублирования дней для одной категории комнат
            $table->unique(['room_type_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availability');
    }
};
