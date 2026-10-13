<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    /** The admin middleware already guards these routes. */
    public function authorize(): bool
    {
        return true;
    }

    /** Codes are stored upper-case so lookups never depend on how it was typed. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Coupon::normaliseCode((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/', 'unique:coupons,code'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers and hyphens only.',
            'expires_at.after' => 'An expiry date in the past would make the coupon dead on arrival.',
        ];
    }
}
