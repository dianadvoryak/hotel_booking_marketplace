<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelPopularResource;
use App\Services\HotelService;
use Illuminate\Http\JsonResponse;
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
}
