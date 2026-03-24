<?php

namespace App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Helpers\ApiResponse;

trait FailedValidationJson
{
    protected function failedValidation(Validator $validator)
    {
        $response = ApiResponse::sendResponse(422, 'Validation Error', $validator->errors()->all());
        throw new ValidationException($validator, $response);
    }
}
