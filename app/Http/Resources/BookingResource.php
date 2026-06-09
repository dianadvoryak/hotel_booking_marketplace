<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "BookingResource",
    title: "Модель успешного ответа бронирования",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: "9c3b88fe-1422-4467-bc1a-641bd3200ff1"),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "total_price", description: "Итоговая сумма (в валюте)", type: "string", example: "450.00"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-06-09T16:30:00Z"),
        new OA\Property(property: "hotel", properties: [
                new OA\Property(property: "name", type: "string", example: "Grand Hyatt Hotel"),
                new OA\Property(property: "city", type: "string", example: "Москва")
            ], type: "object"
        )
    ]
)]
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            // Переводим копейки обратно в рубли/доллары для отображения клиенту
            'total_price' => number_format($this->total_price / 100, 2, '.', ''),
            'created_at' => $this->created_at->toIso8601String(),

            // Загружаем связи, только если они были подтянуты (оптимизация)
            'hotel' => $this->whenLoaded('hotel', fn() => [
                'name' => $this->hotel->name,
                'city' => $this->hotel->city,
            ]),
        ];
    }
}
