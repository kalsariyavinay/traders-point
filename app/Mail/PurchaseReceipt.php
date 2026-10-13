<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Receipt for a paid video, sent once the order is fulfilled.
 *
 * Queued, so a slow or unreachable mail server never holds up the request that
 * just unlocked someone's purchase.
 */
class PurchaseReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your receipt for "'.$this->order->video->title.'"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-receipt',
            with: [
                'brand' => config('academy.brand'),
                'user' => $this->order->user,
                'video' => $this->order->video,
                'coupon' => $this->order->coupon,
                'dashboardUrl' => route('dashboard'),
                'videoUrl' => route('videos.show', $this->order->video),
            ],
        );
    }
}
