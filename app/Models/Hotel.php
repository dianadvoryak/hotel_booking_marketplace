<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'hotels';
    protected $guarded = false;

    // Владелец отеля
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Категории номеров в этом отеле
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class, 'hotel_id');
    }

    // Все бронирования этого отеля
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'hotel_id');
    }
}
