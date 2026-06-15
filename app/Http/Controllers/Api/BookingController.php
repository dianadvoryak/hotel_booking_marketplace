<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmBookingRequest;
use App\Http\Requests\HoldRoomRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingService;
use App\Exceptions\BookingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    private BookingService $bookingService;

    // Внедряем наш сервис через конструктор
    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Создать бронирование отеля
     */
    #[OA\Post(
        path: "/bookings",
        operationId: "storeBooking",
        description: "Принимает параметры брони, проверяет доступность через Redis Lock, транзакционно обновляет сетку и отправляет задачу в RabbitMQ",
        summary: "Забронировать номер в отеле",
        tags: ["Bookings"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: "#/components/schemas/StoreBookingRequest")
    )]
    #[OA\Response(
        response: 201,
        description: "Бронирование успешно создано",
        content: new OA\JsonContent(ref: "#/components/schemas/BookingResource")
    )]
    #[OA\Response(
        response: 422,
        description: "Ошибка валидации или отсутствие свободных мест (Овербукинг)",
        content: new OA\JsonContent(properties: [
            new OA\Property(property: "error", type: "boolean", example: true),
            new OA\Property(property: "message", type: "string", example: "К сожалению, на выбранные даты нет свободных номеров.")
        ])
    )]
    #[OA\Response(
        response: 423,
        description: "Ресурс заблокирован (Параллельный запрос перехвачен Redis Lock)",
        content: new OA\JsonContent(properties: [
            new OA\Property(property: "error", type: "boolean", example: true),
            new OA\Property(property: "message", type: "string", example: "Система обрабатывает другой запрос на этот номер. Попробуйте еще раз.")
        ])
    )]

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            // В реальном API мы бы взяли ID текущего авторизованного юзера: auth()->id()
            // Для тестов пока возьмем любого случайного пользователя из базы данных
            $userId = \App\Models\User::firstOrFail()->id;

            // Вызываем логику сервиса (Redis Lock + БД транзакция + RabbitMQ)
            $booking = $this->bookingService->createBooking(
                $userId,
                $request->input('hotel_id'),
                $request->input('room_type_id'),
                $request->input('check_in'),
                $request->input('check_out')
            );

            // Возвращаем успешный ответ со статусом 201 Created
            return (new BookingResource($booking->load('hotel')))
                ->response()
                ->setStatusCode(201);

        } catch (BookingException $e) {
            // Перехватываем наши кастомные ошибки (нет мест или Redis Lock занят)
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], $e->getCode());

        } catch (\Exception $e) {
            // Логируем системные сбои, чтобы не показывать мясо ошибок клиенту
            Log::error("Ошибка при создании брони: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'error' => true,
                'message' => 'Внутренняя ошибка сервера. Попробуйте позже.'
            ], 500);
        }
    }


    #[OA\Post(
        path: "/bookings/hold",
        operationId: "holdRoom",
        description: "Блокирует свободную комнату в Redis на 10 минут. Предотвращает конкурентное бронирование без нагрузки на PostgreSQL.",
        summary: "Временно заблокировать (заморозить) номер для оплаты",
        tags: ["Bookings"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: "#/components/schemas/StoreBookingRequest")
    )]
    #[OA\Response(
        response: 200,
        description: "Комната успешно удержана",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "reservation_token", type: "string", format: "uuid", example: "3b25f123-1122-4467-bc1a-641bd3200aa9"),
                new OA\Property(property: "expires_at", type: "string", format: "date-time", example: "2026-06-11T16:40:00Z"),
                new OA\Property(property: "message", type: "string", example: "Номер успешно заморожен на 10 минут.")
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "Нет свободных мест на выбранные даты"
    )]
    public function hold(HoldRoomRequest $request): JsonResponse
    {
        try {
            $userId = \App\Models\User::firstOrFail()->id; // Тестовый юзер

            $result = $this->bookingService->holdRoom(
                $userId,
                $request->input('room_type_id'),
                $request->input('check_in'),
                $request->input('check_out')
            );

            return response()->json($result, 200);

        } catch (BookingException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    #[OA\Post(
        path: '/bookings/confirm',
        operationId: 'confirmBooking',
        description: 'Проверяет токен в Redis. Если он валиден, переносит бронь в PostgreSQL и очищает память в Redis.',
        summary: 'Подтвердить и оплатить бронирование по токену резерва',
        tags: ['Bookings']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['reservation_token'],
            properties: [
                new OA\Property(
                    property: 'reservation_token',
                    type: 'string',
                    format: 'uuid',
                    example: '3b25f123-1122-4467-bc1a-641bd3200aa9'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Бронирование успешно оплачено и сохранено',
        content: new OA\JsonContent(ref: '#/components/schemas/BookingResource')
    )]
    #[OA\Response(
        response: 410,
        description: 'Срок действия резерва истек (Gone)'
    )]
    public function confirm(ConfirmBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->confirmBooking(
                $request->input('reservation_token')
            );

            return (new BookingResource($booking->load('hotel')))
                ->response()
                ->setStatusCode(200);

        } catch (BookingException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }
}
