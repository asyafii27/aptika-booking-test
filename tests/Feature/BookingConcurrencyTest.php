<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Master\Room;
use App\Models\Transaction\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const ENDPOINT = '/api/v1/bookings';

    private const PARALLEL_REQUESTS = 10;

    private ?Process $server = null;

    protected function setUp(): void
    {
        if (! str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
            $this->fail('Test ini menjalankan migrate:fresh, gunakan database yang berakhiran _test');
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->server?->stop(0);

        parent::tearDown();
    }

    public function test_booking_on_free_slot_is_created(): void
    {
        $room = $this->createRoom();

        $this->postBooking($this->createUser(), $this->payload($room, '09:00', '10:00'))
            ->assertCreated()
            ->assertJsonPath('room_id', $room->id)
            ->assertJsonPath('status', BookingStatus::Confirmed->value);

        $this->assertSame(1, Booking::count());
    }

    #[DataProvider('overlappingSlots')]
    public function test_overlapping_booking_is_rejected(string $start, string $end): void
    {
        $room = $this->createRoom();

        $this->postBooking($this->createUser(), $this->payload($room, '09:00', '10:00'))->assertCreated();

        $this->postBooking($this->createUser(), $this->payload($room, $start, $end))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_CONFLICT');

        $this->assertSame(1, Booking::count());
    }

    public static function overlappingSlots(): array
    {
        return [
            'slot sama persis' => ['09:00', '10:00'],
            'beririsan di awal' => ['08:30', '09:30'],
            'beririsan di akhir' => ['09:30', '10:30'],
            'berada di dalam slot' => ['09:15', '09:45'],
            'membungkus slot' => ['08:00', '11:00'],
        ];
    }

    #[DataProvider('adjacentSlots')]
    public function test_adjacent_booking_is_allowed(string $start, string $end): void
    {
        $room = $this->createRoom();

        $this->postBooking($this->createUser(), $this->payload($room, '09:00', '10:00'))->assertCreated();
        $this->postBooking($this->createUser(), $this->payload($room, $start, $end))->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    public static function adjacentSlots(): array
    {
        return [
            'tepat sebelum' => ['08:00', '09:00'],
            'tepat sesudah' => ['10:00', '11:00'],
        ];
    }

    public function test_same_slot_on_different_room_is_allowed(): void
    {
        $firstRoom = $this->createRoom();
        $secondRoom = $this->createRoom();

        $this->postBooking($this->createUser(), $this->payload($firstRoom, '09:00', '10:00'))->assertCreated();
        $this->postBooking($this->createUser(), $this->payload($secondRoom, '09:00', '10:00'))->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    public function test_cancelled_booking_does_not_block_slot(): void
    {
        $room = $this->createRoom();
        $owner = $this->createUser();

        $bookingId = $this->postBooking($owner, $this->payload($room, '09:00', '10:00'))->assertCreated()->json('id');

        $this->app['auth']->forgetGuards();
        $this->withToken(JWTAuth::fromUser($owner))
            ->patchJson(self::ENDPOINT . "/{$bookingId}/cancel")
            ->assertOk();

        $this->postBooking($this->createUser(), $this->payload($room, '09:00', '10:00'))->assertCreated();

        $this->assertSame(1, Booking::where('status', BookingStatus::Confirmed)->count());
    }

    public function test_parallel_requests_for_same_slot_only_create_one_booking(): void
    {
        $baseUrl = $this->startServer();
        $room = $this->createRoom();
        $payload = $this->payload($room, '13:00', '14:00');

        $statuses = $this->sendParallel(
            $baseUrl,
            collect(range(1, self::PARALLEL_REQUESTS))->map(fn() => $payload)->all()
        );

        $this->assertSame(1, $statuses->get(201, 0), 'Harus tepat 1 request yang berhasil, hasil: ' . $statuses->toJson());
        $this->assertSame(self::PARALLEL_REQUESTS - 1, $statuses->get(409, 0), 'Sisanya harus ditolak 409, hasil: ' . $statuses->toJson());
        $this->assertSame(1, Booking::where('room_id', $room->id)->count());
    }

    public function test_parallel_requests_for_different_slots_are_all_created(): void
    {
        $baseUrl = $this->startServer();
        $room = $this->createRoom();

        $payloads = collect(range(0, self::PARALLEL_REQUESTS - 1))
            ->map(fn(int $hour) => $this->payload($room, sprintf('%02d:00', 8 + $hour), sprintf('%02d:00', 9 + $hour)))
            ->all();

        $statuses = $this->sendParallel($baseUrl, $payloads);

        $this->assertSame(self::PARALLEL_REQUESTS, $statuses->get(201, 0), 'Semua request harus berhasil, hasil: ' . $statuses->toJson());
        $this->assertSame(self::PARALLEL_REQUESTS, Booking::where('room_id', $room->id)->count());
    }

    private function sendParallel(string $baseUrl, array $payloads)
    {
        $requests = collect($payloads)->map(fn(array $payload) => [
            'token' => JWTAuth::fromUser($this->createUser()),
            'payload' => $payload,
        ]);

        $responses = Http::pool(fn(Pool $pool) => $requests->map(
            fn(array $request) => $pool->baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($request['token'])
                ->timeout(30)
                ->post(self::ENDPOINT, $request['payload'])
        )->all());

        return collect($responses)
            ->map(fn($response) => $response instanceof Response ? $response->status() : 'connection_error')
            ->countBy();
    }

    private function startServer(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('PHP built-in server hanya bisa multi worker di Linux, jalankan test ini di dalam container Docker');
        }

        $port = random_int(20000, 40000);

        $this->server = new Process(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
            public_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => config('database.default'),
                'DB_HOST' => config('database.connections.pgsql.host'),
                'DB_PORT' => (string) config('database.connections.pgsql.port'),
                'DB_DATABASE' => config('database.connections.pgsql.database'),
                'CACHE_STORE' => 'array',
                'PHP_CLI_SERVER_WORKERS' => (string) self::PARALLEL_REQUESTS,
            ],
        );
        $this->server->start();

        $baseUrl = "http://127.0.0.1:{$port}";
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            try {
                Http::timeout(1)->get("{$baseUrl}/up");

                return $baseUrl;
            } catch (ConnectionException) {
                usleep(100000);
            }
        }

        $this->fail('Server test gagal dijalankan: ' . $this->server->getErrorOutput());
    }

    private function postBooking(User $user, array $payload): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(JWTAuth::fromUser($user))->postJson(self::ENDPOINT, $payload);
    }

    private function payload(Room $room, string $start, string $end): array
    {
        $date = Carbon::tomorrow()->toDateString();

        return [
            'room_id' => $room->id,
            'title' => 'Rapat ' . Str::random(6),
            'start_time' => "{$date} {$start}:00",
            'end_time' => "{$date} {$end}:00",
        ];
    }

    private function createRoom(): Room
    {
        return Room::create([
            'name' => 'Ruang ' . Str::random(6),
            'capacity' => 10,
        ]);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'User ' . Str::random(6),
            'email' => Str::lower(Str::random(10)) . '@test.com',
            'password' => 'password123',
        ]);
    }
}
