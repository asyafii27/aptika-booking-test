<?php

namespace App\Services\GeneralService;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

abstract class CommonService
{
    use ApiResponse;

    protected function generalFilter(Builder $query, array $inputs): Builder
    {
        return $query;
    }

    protected function validationErrorResponse(Validator $validator): JsonResponse
    {
        return response()->json([
            'error_code' => 'VALIDATION_ERROR',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }
}
