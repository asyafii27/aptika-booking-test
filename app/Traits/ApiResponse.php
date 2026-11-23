<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
    protected function successResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $this->resolveData($data),
        ], $status);
    }

    protected function createdResponse(mixed $data): JsonResponse
    {
        return response()->json($this->resolveData($data), 201);
    }

    protected function errorResponse(string $errorCode, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error_code' => $errorCode,
            'message'    => $message,
        ], $status);
    }

    private function resolveData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        return $data;
    }
}
