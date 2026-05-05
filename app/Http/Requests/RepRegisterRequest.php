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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:representatives,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:representatives,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_catalog_id' => ['required_without:requested_company_name', 'nullable', 'integer', 'exists:rep_company_catalogs,id', 'prohibits:requested_company_name'],
            'requested_company_name' => ['required_without:company_catalog_id', 'nullable', 'string', 'max:255', 'prohibits:company_catalog_id'],
            'requested_line_name' => ['required', 'string', 'max:255'],
            'requested_area_names' => ['nullable', 'array'],
            'requested_area_names.*' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string'],
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
}
