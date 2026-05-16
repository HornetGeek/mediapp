<?php

namespace App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Helpers\ApiResponse;

trait FailedValidationJson
{
    protected function failedValidation(Validator $validator)
    {
        $response = ApiResponse::sendResponse(422, $validator->errors()->first(), [
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $validator->errors()->toArray(),
        ]);

        throw new ValidationException($validator, $response);
    }
}
