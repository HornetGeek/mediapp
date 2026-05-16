<?php

namespace App\Http\Requests;

use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;

class RepRegisterRequest extends FormRequest
{
    use FailedValidationJson;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhone((string) $this->input('phone')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:representatives,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:representatives,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_catalog_id' => ['required_without:requested_company_name', 'nullable', 'integer', 'exists:rep_company_catalogs,id', 'prohibits:requested_company_name'],
            'requested_company_name' => ['required_without:company_catalog_id', 'nullable', 'string', 'max:255', 'prohibits:company_catalog_id'],
            'requested_line_name' => ['required', 'string', 'max:255'],
            'requested_area_names' => ['nullable', 'array'],
            'requested_area_names.*' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the representative name.',
            'name.string' => 'Representative name must be text.',
            'name.max' => 'Representative name must not exceed 255 characters.',
            'email.required' => 'Please enter the representative email.',
            'email.email' => 'Please enter a valid representative email address.',
            'email.max' => 'Representative email must not exceed 255 characters.',
            'email.unique' => 'This email is already registered as a representative.',
            'phone.required' => 'Please enter the representative phone number.',
            'phone.string' => 'Representative phone number must be text.',
            'phone.max' => 'Representative phone number must not exceed 255 characters.',
            'phone.unique' => 'This phone number is already registered as a representative.',
            'password.required' => 'Please enter a password.',
            'password.string' => 'Password must be text.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'company_catalog_id.required_without' => 'Please select a listed company or enter your company name.',
            'company_catalog_id.integer' => 'Selected company is invalid.',
            'company_catalog_id.exists' => 'Selected company was not found.',
            'company_catalog_id.prohibits' => 'Choose either a listed company or a typed company name, not both.',
            'requested_company_name.required_without' => 'Please select a listed company or enter your company name.',
            'requested_company_name.string' => 'Company name must be text.',
            'requested_company_name.max' => 'Company name must not exceed 255 characters.',
            'requested_company_name.prohibits' => 'Choose either a listed company or a typed company name, not both.',
            'requested_line_name.required' => 'Please enter the line name.',
            'requested_line_name.string' => 'Line name must be text.',
            'requested_line_name.max' => 'Line name must not exceed 255 characters.',
            'requested_area_names.array' => 'Area names must be sent as a list.',
            'requested_area_names.*.required' => 'Each area name is required.',
            'requested_area_names.*.string' => 'Each area name must be text.',
            'requested_area_names.*.max' => 'Each area name must not exceed 255 characters.',
            'fcm_token.string' => 'FCM token must be text.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'password' => 'Password',
            'company_catalog_id' => 'Company',
            'requested_company_name' => 'Company Name',
            'requested_line_name' => 'Line Name',
            'requested_area_names' => 'Area Names',
            'requested_area_names.*' => 'Area Name',
            'fcm_token' => 'FCM Token',
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        $prefix = str_starts_with($trimmed, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        $normalized = $prefix . $digits;

        return preg_replace('/^(\+?20|0020)0(?=1)/', '$1', $normalized) ?? $normalized;
    }
}
