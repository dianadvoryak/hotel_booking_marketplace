<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelPopularResource;
use App\Http\Resources\RoomFeedResource;
use App\Services\HotelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class HotelController extends Controller
{
    private HotelService $hotelService;

    public function __construct(HotelService $hotelService)
    {
        $this->hotelService = $hotelService;
    }

    #[OA\Get(
        path: '/hotels/popular',
        operationId: 'getPopularHotels',
        description: 'Возвращает топ отелей с рейтингом выше 4.0. Данные кэшируются в Redis на 30 минут.',
        summary: 'Получить список популярных отелей',
        tags: ['Hotels']
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешный возврат списка отелей',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/HotelPopularResource')
        )
    )]
    public function index(): JsonResponse
    {
        // Теперь это массив
        $hotelsArray = $this->hotelService->getPopularHotels();

        // Передаем массив в коллекцию ресурсов
        return response()->json(
            HotelPopularResource::collection($hotelsArray)
        );
    }

    #[OA\Get(
        path: '/rooms/feed',
        operationId: 'getRoomsFeed',
        description: 'Возвращает по одному самому дешевому номеру от каждого отеля. Оптимизировано через Cursor Pagination.',
        summary: 'Лента номеров для главной страницы (Infinite Scroll)',
        tags: ['Hotels']
    )]
    #[OA\QueryParameter(name: 'cursor', description: 'Указатель курсора для следующей страницы', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Успешный возврат порции данных ленты',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/RoomFeedResource')),
                new OA\Property(property: 'next_page_url', type: 'string', example: 'eyJiYXNlX3ByaWNlIjo0NT')
            ]
        )
    )]
    public function feed(Request $request): JsonResponse
    {
        // Приводим коллекцию stdClass (из Query Builder) в массивы для корректной работы нашего Resource
        $paginator = $this->hotelService->getRoomsFeed(10);

        $itemsArray = collect($paginator->items())->map(fn($item) => (array) $item)->toArray();

        return response()->json([
            'data' => RoomFeedResource::collection($itemsArray),
            'next_cursor' => $paginator->nextCursor()?->encode(), // Отдаем зашифрованный токен для следующего запроса фронтенду
            'has_more' => $paginator->hasMorePages(),
        ]);
    }
}
