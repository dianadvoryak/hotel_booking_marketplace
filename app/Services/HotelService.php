<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
                ->toArray(); // переводим коллекцию в массив перед записью в Redis
        });
    }

    /**
     * Получить ленту уникальных номеров (по одному от отеля) для бесконечного скролла
     */
    public function getRoomsFeed(int $perPage = 10): CursorPaginator
    {
        // Строим подзапрос с оконной функцией, чтобы вытащить по 1 самой дешевой комнате из каждого отеля
        $subQuery = DB::table('room_types')
            ->join('hotels', 'room_types.hotel_id', '=', 'hotels.id')
            ->select([
                'room_types.id',
                'room_types.name',
                'room_types.base_price',
                'room_types.hotel_id',
                'hotels.name as hotel_name',
                'hotels.city as hotel_city',
                'hotels.rating as hotel_rating',
                // Нумеруем строки внутри группы каждого отеля, сортируя по цене
                DB::raw('ROW_NUMBER() OVER (PARTITION BY room_types.hotel_id ORDER BY room_types.base_price ASC) as rn')
            ]);

        // Оборачиваем в основной запрос и берем только первые номера (rn = 1)
        // Для бесконечной ленты сортируем, например, по цене или ID, чтобы курсор работал стабильно
        $query = DB::table(DB::raw("({$subQuery->toSql()}) as ranked_rooms"))
            ->mergeBindings($subQuery) // Переносим привязки параметров SQL, если они появятся
            ->where('rn', 1)
            ->orderBy('base_price', 'asc')
            ->orderBy('id', 'asc');

        // Используем курсорную пагинацию вместо paginate() для Infinite Scroll
        return $query->cursorPaginate($perPage);
    }
}
