<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreBookingRequest",
    title: "Запрос на создание бронирования",
    required: ["hotel_id", "room_type_id", "check_in", "check_out"],
    properties: [
        new OA\Property(property: "hotel_id", description: "UUID отеля", type: "string", format: "uuid", example: "9c3a4f61-2e61-4b13-bb14-9b5cfda18712"),
        new OA\Property(property: "room_type_id", description: "UUID категории номера", type: "string", format: "uuid", example: "9c3a4f61-2e61-4b13-bb14-9b5cfda18754"),
        new OA\Property(property: "check_in", description: "Дата заезда (YYYY-MM-DD)", type: "string", format: "date", example: "2026-07-10"),
        new OA\Property(property: "check_out", description: "Дата выезда (YYYY-MM-DD)", type: "string", format: "date", example: "2026-07-15")
    ]
)]
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Здесь будет проверка прав (например, Auth::check())
    }

    public function rules(): array
    {
        return [
            // Проверяем, что ID отеля и комнаты — это валидные UUID и они существуют в БД
            'hotel_id'     => ['required', 'uuid', 'exists:hotels,id'],
            'room_type_id' => ['required', 'uuid', 'exists:room_types,id'],

            // Дата заезда должна быть не раньше, чем сегодня
            'check_in'     => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],

            // Дата выезда должна быть минимум на 1 день позже даты заезда
            'check_out'    => ['required', 'date', 'date_format:Y-m-d', 'after:check_in'],
        ];
    }

    /**
     * Кастомные сообщения об ошибках (Senior-практика для фронтенда)
     */
    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'Дата заезда не может быть в прошлом.',
            'check_out.after'         => 'Дата выезда должна быть позже даты заезда.',
            'room_type_id.exists'     => 'Выбранный тип номера не существует.',
        ];
    }
}
