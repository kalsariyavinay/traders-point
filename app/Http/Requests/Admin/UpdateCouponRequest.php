<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * Same rules, except the code may keep its own value and the expiry may
     * stay in the past on a coupon that has already run its course.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
            ],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
