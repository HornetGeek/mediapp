<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class DoctorsRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:doctors'],
            'phone' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'address_1' => ['required', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'exists:specialties,id'],

            // التحقق من الأوقات
            // 'availabilities' => ['array'],
            // 'availabilities.*.date' => ['date'],
            // 'availabilities.*.start_time' => ['date_format:H:i'],
            // 'availabilities.*.end_time' => ['date_format:H:i'],
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
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'password' => 'Password',
            'address_1' => 'Address 1',
            'address_2' => 'Address 2',
            'availabilities.*.date' => 'Date',
            'availabilities.*.start_time' => 'Start Time',
            'availabilities.*.end_time' => 'End Time',
        ];
    }
}
