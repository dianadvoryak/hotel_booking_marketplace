<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'room_types';
    protected $guarded = false;

    // К какому отелю относится комната
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    // Сетка доступности по дням для этой комнаты
    public function availabilities(): HasMany
    {
        return $this->hasMany(RoomAvailability::class, 'room_type_id');
    }
}
