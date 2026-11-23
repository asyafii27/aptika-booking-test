<?php

namespace App\Services\Master;

use App\Enums\BookingStatus;
use App\Models\Master\Room;
use App\Services\GeneralService\CommonService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RoomService extends CommonService
{
    public function index(array $inputs)
    {
        try {
            $query = Room::query();
            $query = $this->generalFilter($query, $inputs);
            $query = generalSortData($query, $inputs);

            $results = setPaginationOrNone($query, $inputs);

            return $results;
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to get room', $e));
            return $this->errorResponse('ROOM_FETCH_FAILED', recapErrorResponse('Gagal mengambil data room: ', $e), 422);
        }
    }

    public function available(array $inputs)
    {
        $validator = Validator::make($inputs, $this->availableRules(), $this->availableMessages());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        try {
            $query = Room::query()
                ->where('is_active', true)
                ->whereDoesntHave('bookings', function (Builder $booking) use ($data) {
                    $booking->where('status', BookingStatus::Confirmed)
                        ->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>', $data['start_time']);
                });

            if (! empty($data['capacity'])) {
                $query->where('capacity', '>=', $data['capacity']);
            }

            $query = $this->generalFilter($query, $inputs);
            $query = generalSortData($query, $inputs);

            $results = setPaginationOrNone($query, $inputs);

            return $results;
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to get available room', $e));
            return $this->errorResponse('ROOM_FETCH_FAILED', recapErrorResponse('Gagal mengambil data room tersedia: ', $e), 422);
        }
    }

    public function store(array $inputs)
    {
        $validator = Validator::make($inputs, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $room = Room::create($validator->validated());

            return $this->createdResponse($room->refresh());
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to create room', $e));
            return $this->errorResponse('ROOM_CREATE_FAILED', recapErrorResponse('Gagal membuat room: ', $e), 422);
        }
    }

    public function update(int $id, array $inputs)
    {
        $validator = Validator::make($inputs, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $room = Room::find($id);

            if (! $room) {
                return $this->errorResponse('ROOM_NOT_FOUND', 'Room tidak ditemukan', 404);
            }

            $room->update($validator->validated());

            return $this->successResponse($room->refresh());
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to update room', $e));
            return $this->errorResponse('ROOM_UPDATE_FAILED', recapErrorResponse('Gagal mengubah room: ', $e), 422);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama room wajib diisi',
            'name.string' => 'Nama room harus berupa teks',
            'name.max' => 'Nama room maksimal 100 karakter',
            'capacity.required' => 'Kapasitas wajib diisi',
            'capacity.integer' => 'Kapasitas harus berupa angka bulat',
            'capacity.min' => 'Kapasitas minimal 1',
        ];
    }

    protected function availableRules(): array
    {
        return [
            'start_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_time' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_time'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function availableMessages(): array
    {
        return [
            'start_time.required' => 'Waktu mulai wajib diisi',
            'start_time.date_format' => 'Format waktu mulai harus Y-m-d H:i:s',
            'end_time.required' => 'Waktu selesai wajib diisi',
            'end_time.date_format' => 'Format waktu selesai harus Y-m-d H:i:s',
            'end_time.after' => 'Waktu selesai harus setelah waktu mulai',
            'capacity.integer' => 'Kapasitas harus berupa angka bulat',
            'capacity.min' => 'Kapasitas minimal 1',
        ];
    }

    protected function generalFilter(Builder $query, array $inputs): Builder
    {
        if (! empty($inputs['search'])) {
            $query->where('name', 'ilike', '%' . $inputs['search'] . '%');
        }

        if (isset($inputs['is_active']) && $inputs['is_active'] !== '') {
            $query->where('is_active', filter_var($inputs['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }
}
