<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;

class CompanyAdminLoginRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required'],
            'fcm_token' => ['sometimes', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'email' => 'Email',
            'password' => 'Password',
        ];
    }
}
