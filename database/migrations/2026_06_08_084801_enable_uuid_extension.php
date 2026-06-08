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
        // Включаем расширение для работы с UUID в PostgreSQL
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp";');
    }
};
