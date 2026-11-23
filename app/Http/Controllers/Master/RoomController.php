<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\RoomService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(protected RoomService $roomService) {}

    public function index(Request $request)
    {
        $inputs = $request->all();

        return $this->roomService->index($inputs);
    }

    public function store(Request $request)
    {
        $inputs = $request->all();

        return $this->roomService->store($inputs);
    }

    public function show(int $id)
    {
        return $this->roomService->show($id);
    }

    public function update(Request $request, int $id)
    {
        $inputs = $request->all();

        return $this->roomService->update($id, $inputs);
    }

    public function destroy(int $id)
    {
        return $this->roomService->destroy($id);
    }
}
