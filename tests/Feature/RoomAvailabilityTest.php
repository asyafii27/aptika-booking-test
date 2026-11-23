<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\CreatesBookingData;
use Tests\TestCase;

class RoomAvailabilityTest extends TestCase
{
    use CreatesBookingData, DatabaseMigrations;

    private const ENDPOINT = '/api/v1/rooms/available';

    public function test_only_active_rooms_without_overlapping_booking_are_returned(): void
    {
        $user = $this->createUser();
        $bookedRoom = $this->createRoom();
        $freeRoom = $this->createRoom();
        $this->createRoom(['is_active' => false]);
        $this->createBooking($bookedRoom, $user, '09:00', '10:00');

        $response = $this->actingWithToken($user)->getJson($this->url('09:30', '10:30'))->assertOk();

        $this->assertSame([$freeRoom->id], array_column($response->json(), 'id'));
    }

    public function test_adjacent_booking_does_not_block_room(): void
    {
        $user = $this->createUser();
        $room = $this->createRoom();
        $this->createBooking($room, $user, '09:00', '10:00');

        $response = $this->actingWithToken($user)->getJson($this->url('10:00', '11:00'))->assertOk();

        $this->assertSame([$room->id], array_column($response->json(), 'id'));
    }

    public function test_cancelled_booking_does_not_block_room(): void
    {
        $user = $this->createUser();
        $room = $this->createRoom();
        $this->createBooking($room, $user, '09:00', '10:00', BookingStatus::Cancelled);

        $response = $this->actingWithToken($user)->getJson($this->url('09:00', '10:00'))->assertOk();

        $this->assertSame([$room->id], array_column($response->json(), 'id'));
    }

    public function test_capacity_filter_returns_rooms_with_enough_capacity(): void
    {
        $user = $this->createUser();
        $this->createRoom(['capacity' => 5]);
        $bigRoom = $this->createRoom(['capacity' => 20]);

        $response = $this->actingWithToken($user)->getJson($this->url('09:00', '10:00') . '&capacity=10')->assertOk();

        $this->assertSame([$bigRoom->id], array_column($response->json(), 'id'));
    }

    public function test_pagination_is_used_when_page_is_given(): void
    {
        $user = $this->createUser();
        $this->createRoom();
        $this->createRoom();
        $this->createRoom();

        $this->actingWithToken($user)
            ->getJson($this->url('09:00', '10:00') . '&page=1&page_size=2')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_time_range_is_required_and_must_be_valid(): void
    {
        $user = $this->createUser();

        $this->actingWithToken($user)
            ->getJson(self::ENDPOINT)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['start_time', 'end_time']);

        $this->actingWithToken($user)
            ->getJson($this->url('10:00', '09:00'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_guest_cannot_check_availability(): void
    {
        $this->getJson($this->url('09:00', '10:00'))->assertUnauthorized();
    }

    private function url(string $start, string $end): string
    {
        return self::ENDPOINT . '?' . http_build_query([
            'start_time' => $this->at($start),
            'end_time' => $this->at($end),
        ]);
    }
}
