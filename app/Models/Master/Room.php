<?php

namespace App\Models\Master;

use App\Models\Transaction\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $table = 'rooms';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'capacity',
        'is_active'
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'room_id', 'id');
    }
}
