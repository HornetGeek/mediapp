<?php

namespace App\Http\Requests;

use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;


class RepsCreateRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:representatives,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:representatives,phone'],
            'password' => ['required'],
            'area_ids' => 'required|array',
            'area_ids.*' => 'exists:areas,id',
            'line_ids' => 'nullable|array',
            'line_ids.*' => 'exists:lines,id',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'The phone number has already been taken.',
            'area_ids.required' => 'Please select at least one area.',
            'area_ids.array' => 'The areas must be an array.',
            'area_ids.*.exists' => 'One or more selected areas are invalid.',
            'line_ids.array' => 'The lines must be an array.',
            'line_ids.*.exists' => 'One or more selected lines are invalid.',
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
            'password' => 'Password',
            'phone' => 'Phone',
            'area_ids' => 'Areas',
            'line_ids' => 'Lines',
        ];
    }
}
