<?php

namespace App\Services\Transaction;

use App\Enums\BookingStatus;
use App\Models\Master\Room;
use App\Models\Transaction\Booking;
use App\Models\User;
use App\Services\GeneralService\CommonService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BookingService extends CommonService
{
    public function index(array $inputs, User $user)
    {
        try {
            $query = Booking::query()->with(['room:id,name,capacity', 'user:id,name,email']);

            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            $query = $this->generalFilter($query, $inputs);
            $query = generalSortData($query, $inputs);

            $results = setPaginationOrNone($query, $inputs);

            return $results;
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to get booking', $e));
            return $this->errorResponse('BOOKING_FETCH_FAILED', recapErrorResponse('Gagal mengambil data booking: ', $e), 422);
        }
    }

    public function store(array $inputs, User $user)
    {
        $validator = Validator::make($inputs, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        try {
            return DB::transaction(function () use ($data, $user) {
                $room = Room::lockForUpdate()->find($data['room_id']);

                if (!$room->is_active) {
                    return $this->errorResponse('ROOM_INACTIVE', 'Room sedang tidak aktif', 422);
                }

                if ($this->hasOverlap($data['room_id'], $data['start_time'], $data['end_time'])) {
                    return $this->errorResponse('BOOKING_CONFLICT', 'Room sudah dibooking pada rentang waktu tersebut', 409);
                }

                $booking = Booking::create([
                    ...$data,
                    'user_id' => $user->id,
                    'status' => BookingStatus::Confirmed,
                ]);

                return $this->createdResponse($booking->refresh()->load(['room:id,name,capacity', 'user:id,name,email']));
            });
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to create booking', $e));
            return $this->errorResponse('BOOKING_CREATE_FAILED', recapErrorResponse('Gagal membuat booking: ', $e), 422);
        }
    }

    public function cancel(int $id, User $user)
    {
        try {
            $booking = Booking::find($id);

            if (!$booking) {
                return $this->errorResponse('BOOKING_NOT_FOUND', 'Booking tidak ditemukan', 404);
            }

            if (!$this->canAccess($booking, $user)) {
                return $this->errorResponse('FORBIDDEN', 'Anda tidak memiliki akses ke booking ini', 403);
            }

            if ($booking->status === BookingStatus::Cancelled) {
                return $this->errorResponse('BOOKING_CANCELLED', 'Booking sudah dibatalkan sebelumnya', 422);
            }

            $booking->update(['status' => BookingStatus::Cancelled]);

            return $this->successResponse($booking->refresh()->load(['room:id,name,capacity', 'user:id,name,email']));
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to cancel booking', $e));
            return $this->errorResponse('BOOKING_CANCEL_FAILED', recapErrorResponse('Gagal membatalkan booking: ', $e), 422);
        }
    }

    protected function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:150'],
            'start_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'end_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_time'],
        ];
    }

    protected function messages(): array
    {
        return [
            'room_id.required' => 'Room wajib dipilih',
            'room_id.integer' => 'Room tidak valid',
            'room_id.exists' => 'Room tidak ditemukan',
            'title.required' => 'Judul booking wajib diisi',
            'title.string' => 'Judul booking harus berupa teks',
            'title.max' => 'Judul booking maksimal 150 karakter',
            'start_time.required' => 'Waktu mulai wajib diisi',
            'start_time.date_format' => 'Format waktu mulai harus Y-m-d H:i:s',
            'start_time.after' => 'Waktu mulai harus setelah waktu sekarang',
            'end_time.required' => 'Waktu selesai wajib diisi',
            'end_time.date_format' => 'Format waktu selesai harus Y-m-d H:i:s',
            'end_time.after' => 'Waktu selesai harus setelah waktu mulai',
        ];
    }

    protected function generalFilter(Builder $query, array $inputs): Builder
    {
        if (!empty($inputs['search'])) {
            $query->where('title', 'ilike', '%' . $inputs['search'] . '%');
        }

        if (!empty($inputs['room_id'])) {
            $query->where('room_id', $inputs['room_id']);
        }

        if (!empty($inputs['user_id'])) {
            $query->where('user_id', $inputs['user_id']);
        }

        if (!empty($inputs['status'])) {
            $query->where('status', $inputs['status']);
        }

        if (!empty($inputs['start_date'])) {
            $query->whereDate('start_time', '>=', $inputs['start_date']);
        }

        if (!empty($inputs['end_date'])) {
            $query->whereDate('start_time', '<=', $inputs['end_date']);
        }

        return $query;
    }

    private function hasOverlap(int $roomId, string $startTime, string $endTime): bool
    {
        return Booking::where('room_id', $roomId)
            ->where('status', BookingStatus::Confirmed)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    private function canAccess(Booking $booking, User $user): bool
    {
        return $user->isAdmin() || $booking->user_id === $user->id;
    }
}
