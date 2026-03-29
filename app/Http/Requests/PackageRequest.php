<?php

namespace App\Http\Requests;

use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'plan_type' => 'required_without:duration|in:quarterly,semi_annual,annual,custom_days',
            'duration' => 'required_without:plan_type|required_if:plan_type,custom_days|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ];
    }
}
