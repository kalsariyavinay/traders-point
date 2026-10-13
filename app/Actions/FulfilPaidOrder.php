<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Mail\PurchaseReceipt;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Marks a paid order complete and unlocks its video.
 *
 * Both the browser callback and the Razorpay webhook land here, and either can
 * arrive first (or twice), so the whole thing runs under a row lock and is safe
 * to call repeatedly.
 */
class FulfilPaidOrder
{
    /**
     * @return bool True when this call was the one that granted access.
     */
    public function __invoke(Order $order, string $paymentId): bool
    {
        $granted = DB::transaction(function () use ($order, $paymentId) {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                return false;
            }

            $locked->forceFill([
                'status' => OrderStatus::Success,
                'razorpay_payment_id' => $paymentId,
            ])->save();

            $locked->user->purchasedVideos()->syncWithoutDetaching([$locked->video_id]);

            // Only a paid-for use counts against a coupon's limit.
            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->increment('used_count');
            }

            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($granted) {
            $this->sendReceipt($order);
        }

        return $granted;
    }

    /**
     * Queued, and deliberately outside the transaction so the job is never
     * picked up before the order is committed.
     *
     * A receipt that cannot be queued must not cost someone the access they
     * have already paid for, so a failure here is logged rather than thrown.
     */
    private function sendReceipt(Order $order): void
    {
        try {
            $order->loadMissing(['user', 'video.category', 'coupon']);

            Mail::to($order->user)->send(new PurchaseReceipt($order));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
