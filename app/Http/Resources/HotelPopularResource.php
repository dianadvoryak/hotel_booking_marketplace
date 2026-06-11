<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
     schema: "HotelPopularResource",
     title: "Модель популярного отеля на главной",
     properties: [
         new OA\Property(property: "id", type: "string", format: "uuid", example: "9c3a4f61-2e61-4b13-bb14-9b5cfda18712"),
         new OA\Property(property: "name", type: "string", example: "Grand Royal Hotel"),
         new OA\Property(property: "city", type: "string", example: "Москва"),
         new OA\Property(property: "address", type: "string", example: "ул. Ленина, д. 10"),
         new OA\Property(property: "rating", type: "number", format: "float", example: 4.85),
     ]
 )]
class HotelPopularResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this['id'],      // ИСПРАВЛЕНО: $this['id'] вместо $this->id
            'name'    => $this['name'],    // ИСПРАВЛЕНО
            'city'    => $this['city'],    // ИСПРАВЛЕНО
            'address' => $this['address'], // ИСПРАВЛЕНО
            'rating'  => (float) $this['rating'], // ИСПРАВЛЕНО
        ];
    }
}
