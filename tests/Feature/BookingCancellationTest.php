<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\CreatesBookingData;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use CreatesBookingData, DatabaseMigrations;

    public function test_owner_can_cancel_booking(): void
    {
        $owner = $this->createUser();
        $booking = $this->createBooking($this->createRoom(), $owner, '09:00', '10:00');

        $this->actingWithToken($owner)
            ->patchJson($this->url($booking->id))
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Cancelled->value);

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_admin_can_cancel_booking_of_other_user(): void
    {
        $booking = $this->createBooking($this->createRoom(), $this->createUser(), '09:00', '10:00');

        $this->actingWithToken($this->createUser(UserRole::Admin))
            ->patchJson($this->url($booking->id))
            ->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_other_user_cannot_cancel_booking(): void
    {
        $booking = $this->createBooking($this->createRoom(), $this->createUser(), '09:00', '10:00');

        $this->actingWithToken($this->createUser())
            ->patchJson($this->url($booking->id))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
    }

    public function test_cancelled_booking_cannot_be_cancelled_again(): void
    {
        $owner = $this->createUser();
        $booking = $this->createBooking($this->createRoom(), $owner, '09:00', '10:00', BookingStatus::Cancelled);

        $this->actingWithToken($owner)
            ->patchJson($this->url($booking->id))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'BOOKING_CANCELLED');
    }

    public function test_cancel_unknown_booking_returns_not_found(): void
    {
        $this->actingWithToken($this->createUser())
            ->patchJson($this->url(999999))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'BOOKING_NOT_FOUND');
    }

    public function test_guest_cannot_cancel_booking(): void
    {
        $booking = $this->createBooking($this->createRoom(), $this->createUser(), '09:00', '10:00');

        $this->patchJson($this->url($booking->id))->assertUnauthorized();

        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
    }

    private function url(int $id): string
    {
        return "/api/v1/bookings/{$id}/cancel";
    }
}
