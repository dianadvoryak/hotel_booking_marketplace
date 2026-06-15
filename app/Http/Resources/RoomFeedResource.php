<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RoomFeedResource',
    title: 'Модель номера в бесконечной ленте',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d'),
        new OA\Property(property: 'name', type: 'string', example: 'Люкс с видом на море'),
        new OA\Property(property: 'base_price', type: 'number', format: 'float', example: 12500.00),
        new OA\Property(
            property: 'hotel',
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '9c3a4f61-2e61-4b13-bb14-9b5cfda18712'),
                new OA\Property(property: 'name', type: 'string', example: 'Grand Royal Hotel'),
                new OA\Property(property: 'city', type: 'string', example: 'Сочи'),
                new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.9)
            ],
            type: 'object'
        )
    ]
)]
class RoomFeedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'base_price' => (float) $this['base_price'],
            'hotel' => [
                'id' => $this['hotel_id'],
                'name' => $this['hotel_name'],
                'city' => $this['hotel_city'],
                'rating' => (float) $this['hotel_rating'],
            ]
        ];
    }
}
