<?php

namespace App\Http\Requests;

use App\Traits\FailedValidationJson;
use Illuminate\Foundation\Http\FormRequest;

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

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhone((string) $this->input('phone')),
            ]);
        }
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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'address_1' => ['required', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'exists:specialties,id'],

            // التحقق من الأوقات
            // 'availabilities' => ['array'],
            // 'availabilities.*.date' => ['date'],
            // 'availabilities.*.start_time' => ['date_format:H:i'],
            // 'availabilities.*.end_time' => ['date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the doctor name.',
            'name.string' => 'Doctor name must be text.',
            'name.max' => 'Doctor name must not exceed 255 characters.',
            'email.required' => 'Please enter the doctor email.',
            'email.email' => 'Please enter a valid doctor email address.',
            'email.max' => 'Doctor email must not exceed 255 characters.',
            'email.unique' => 'This email is already registered as a doctor.',
            'phone.required' => 'Please enter the doctor phone number.',
            'phone.string' => 'Doctor phone number must be text.',
            'phone.max' => 'Doctor phone number must not exceed 255 characters.',
            'password.required' => 'Please enter a password.',
            'password.string' => 'Password must be text.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 6 characters.',
            'address_1.required' => 'Please enter the doctor address.',
            'address_1.string' => 'Doctor address must be text.',
            'address_1.max' => 'Doctor address must not exceed 255 characters.',
            'specialty_id.exists' => 'Selected specialty was not found.',
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
            'specialty_id' => 'Specialty',
            'availabilities.*.date' => 'Date',
            'availabilities.*.start_time' => 'Start Time',
            'availabilities.*.end_time' => 'End Time',
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
