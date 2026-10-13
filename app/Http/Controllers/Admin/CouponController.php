<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $coupons = Coupon::query()
            ->when($search !== '', fn (Builder $query) => $query->where('code', 'like', '%'.strtoupper($search).'%'))
            // What the coupon has actually cost, not just how often it was typed.
            ->withCount(['orders as paid_orders' => fn (Builder $q) => $q->where('status', OrderStatus::Success)])
            ->withSum(['orders as discount_given' => fn (Builder $q) => $q->where('status', OrderStatus::Success)], 'discount_amount')
            ->withSum(['orders as revenue' => fn (Builder $q) => $q->where('status', OrderStatus::Success)], 'amount')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.coupons.index', [
            'coupons' => $coupons,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'coupon' => new Coupon(['discount_percent' => 10, 'is_active' => true]),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = Coupon::create($request->validated());

        return to_route('admin.coupons.index')
            ->with('success', "Coupon \"{$coupon->code}\" was created.");
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.edit', ['coupon' => $coupon]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->validated());

        return to_route('admin.coupons.index')
            ->with('success', "Coupon \"{$coupon->code}\" was updated.");
    }

    /** Pull a live coupon, or put a paused one back, without touching its dates. */
    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', $coupon->is_active
            ? "Coupon \"{$coupon->code}\" is live again."
            : "Coupon \"{$coupon->code}\" is paused.");
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        // Deleting would null the coupon_id on real orders and lose the record
        // of what was discounted. Pausing achieves the same thing safely.
        if ($coupon->orders()->exists()) {
            return to_route('admin.coupons.index')
                ->with('error', "\"{$coupon->code}\" has been used on orders and cannot be deleted. Pause it instead.");
        }

        $code = $coupon->code;
        $coupon->delete();

        return to_route('admin.coupons.index')
            ->with('success', "Coupon \"{$code}\" was deleted.");
    }
}
