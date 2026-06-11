<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class HotelService
{
    /**
     * Получить список популярных отелей (кэширование массивов в Redis)
     *
     * @return array
     */
    public function getPopularHotels(): array
    {
        $cacheKey = 'hotels:popular';
        $cacheTtl = 1800; // 30 минут

        // Возвращаем и сохраняем только чистый массив данных
        return Cache::remember($cacheKey, $cacheTtl, function () {
            return Hotel::where('rating', '>=', 4.0)
                ->orderByDesc('rating')
                ->limit(6)
                ->get()
                ->toArray(); // ИСПРАВЛЕНО: переводим коллекцию в массив перед записью в Redis
        });
    }
}
