<?php

namespace App\Models\Transaction;

use App\Enums\BookingStatus;
use App\Models\Master\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'room_id',
        'user_id',
        'title',
        'start_time',
        'end_time',
        'status'
    ];

    protected $attributes = [
        'status' => 'confirmed',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:Y-m-d H:i:s',
            'end_time' => 'datetime:Y-m-d H:i:s',
            'status' => BookingStatus::class,
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
