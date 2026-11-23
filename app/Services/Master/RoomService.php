<?php

namespace App\Services\Master;

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

    public function show(int $id)
    {
        try {
            $room = Room::find($id);

            if (! $room) {
                return $this->errorResponse('ROOM_NOT_FOUND', 'Room tidak ditemukan', 404);
            }

            return $this->successResponse($room);
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to get room detail', $e));
            return $this->errorResponse('ROOM_FETCH_FAILED', recapErrorResponse('Gagal mengambil detail room: ', $e), 422);
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

    public function destroy(int $id)
    {
        try {
            $room = Room::find($id);

            if (! $room) {
                return $this->errorResponse('ROOM_NOT_FOUND', 'Room tidak ditemukan', 404);
            }

            if ($room->bookings()->exists()) {
                return $this->errorResponse('ROOM_HAS_BOOKINGS', 'Room tidak dapat dihapus karena masih memiliki booking', 409);
            }

            $room->delete();

            return $this->successResponse(['message' => 'Room berhasil dihapus']);
        } catch (Exception $e) {
            Log::error(recapErrorLog('Failed to delete room', $e));
            return $this->errorResponse('ROOM_DELETE_FAILED', recapErrorResponse('Gagal menghapus room: ', $e), 422);
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
