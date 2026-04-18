<?php

namespace App\Http\Requests\Customers;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalize user input before the rules run.
        $this->merge($this->normalizedCustomerInput());
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+\-\s()]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $digits = preg_replace('/\D+/', '', $value) ?? '';

                    if (strlen($digits) < 7) {
                        $fail('Phone must contain at least 7 digits.');
                    }
                },
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                // Keep email uniqueness scoped to the current organization.
                Rule::unique('customers', 'email')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId),
                ),
            ],
            'address' => ['required', 'array'],
            'address.country' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.postal_code' => ['required', 'string', 'max:255'],
            'address.address_line1' => ['required', 'string', 'max:255'],
            'address.address_line2' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'phone.required' => 'Phone is required.',
            'phone.regex' => 'Phone may only contain digits, spaces, plus signs, hyphens, and parentheses.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'This email is already used by another customer in your organization.',
            'address.country.required' => 'Country is required.',
            'address.city.required' => 'City is required.',
            'address.postal_code.required' => 'Postal code is required.',
            'address.address_line1.required' => 'Address line 1 is required.',
        ];
    }

    private function normalizedCustomerInput(): array
    {
        return [
            'first_name' => $this->trimmedInput('first_name'),
            'middle_name' => $this->nullableTrimmedInput('middle_name'),
            'last_name' => $this->trimmedInput('last_name'),
            'phone' => $this->trimmedInput('phone'),
            'email' => $this->normalizedEmailInput(),
            'address' => $this->normalizedAddressInput(),
        ];
    }

    private function trimmedInput(string $key): mixed
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        return trim($value);
    }

    private function nullableTrimmedInput(string $key): mixed
    {
        $value = $this->trimmedInput($key);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function normalizedEmailInput(): mixed
    {
        $value = $this->trimmedInput('email');

        if (! is_string($value)) {
            return $value;
        }

        return mb_strtolower($value);
    }

    private function normalizedAddressInput(): mixed
    {
        $value = $this->input('address');

        if (! is_array($value)) {
            return $value;
        }

        return [
            'country' => $this->nullableTrimmedNestedInput($value, 'country'),
            'city' => $this->nullableTrimmedNestedInput($value, 'city'),
            'postal_code' => $this->nullableTrimmedNestedInput($value, 'postal_code'),
            'address_line1' => $this->nullableTrimmedNestedInput($value, 'address_line1'),
            'address_line2' => $this->nullableTrimmedNestedInput($value, 'address_line2'),
        ];
    }

    private function nullableTrimmedNestedInput(array $input, string $key): mixed
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
