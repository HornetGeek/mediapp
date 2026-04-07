<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use App\Traits\FailedValidationJson;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CompanyRequest extends FormRequest
{
    use FailedValidationJson;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:companies,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string'],
            'visits_per_day' => ['required', 'integer'],
            'num_of_reps' => ['required', 'integer'],
            'package_id' => ['required', 'exists:packages,id']
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Company Name',
            'email' => 'Email',
            'password' => 'Password',
            'phone' => 'Phone',
            'visits_per_day' => 'Visits Per Day',
            'num_of_reps' => 'Number of Representatives',
            'package_id' => 'Package ID',
        ];
    }
}
