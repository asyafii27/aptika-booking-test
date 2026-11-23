<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\Transaction\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}

    public function index(Request $request)
    {
        $inputs = $request->all();

        return $this->bookingService->index($inputs, $request->user());
    }

    public function store(Request $request)
    {
        $inputs = $request->all();

        return $this->bookingService->store($inputs, $request->user());
    }

    public function cancel(Request $request, int $id)
    {
        return $this->bookingService->cancel($id, $request->user());
    }
}
