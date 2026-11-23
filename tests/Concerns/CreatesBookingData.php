<?php

namespace Tests\Concerns;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Master\Room;
use App\Models\Transaction\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

trait CreatesBookingData
{
    protected function createUser(UserRole $role = UserRole::User): User
    {
        return User::create([
            'name' => 'User ' . Str::random(6),
            'email' => Str::lower(Str::random(10)) . '@test.com',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    protected function createRoom(array $attributes = []): Room
    {
        return Room::create([
            'name' => 'Ruang ' . Str::random(6),
            'capacity' => 10,
            ...$attributes,
        ]);
    }

    protected function createBooking(Room $room, User $user, string $start, string $end, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        return Booking::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Rapat ' . Str::random(6),
            'start_time' => $this->at($start),
            'end_time' => $this->at($end),
            'status' => $status,
        ]);
    }

    protected function at(string $time): string
    {
        return Carbon::tomorrow()->toDateString() . " {$time}:00";
    }

    protected function actingWithToken(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(JWTAuth::fromUser($user));
    }
}
