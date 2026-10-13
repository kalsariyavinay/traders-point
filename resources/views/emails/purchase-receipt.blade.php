{{--
    Email clients are not browsers: tables, inline styles, no flexbox, no
    external CSS. Kept light so it reads in every client and prints cleanly.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }} — receipt</title>
</head>
<body style="margin:0; padding:0; background:#F2F4F9; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0E1529;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2F4F9; padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #E2E7F2;">

                {{-- Header --}}
                <tr>
                    <td style="background:#070B18; padding:22px 28px;">
                        <p style="margin:0; font-size:16px; font-weight:bold; color:#EAF0FF;">{{ $brand }}</p>
                        <p style="margin:4px 0 0; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#8795B8;">
                            Payment receipt
                        </p>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 14px; font-size:15px;">Hello {{ $user->name }},</p>

                        <p style="margin:0 0 22px; font-size:14px; line-height:1.65; color:#2A3550;">
                            Your payment went through and
                            <strong>{{ $video->title }}</strong> is now in your library for good.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border:1px solid #E2E7F2; border-radius:8px; font-size:13px;">
                            <tr>
                                <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">Lesson</td>
                                <td style="padding:11px 16px; text-align:right; font-weight:bold; border-bottom:1px solid #EDF0F7;">
                                    {{ $video->title }}
                                </td>
                            </tr>
                            @if ($video->category)
                                <tr>
                                    <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">Category</td>
                                    <td style="padding:11px 16px; text-align:right; border-bottom:1px solid #EDF0F7;">
                                        {{ $video->category->name }}
                                    </td>
                                </tr>
                            @endif
                            @if ((float) $order->discount_amount > 0)
                                <tr>
                                    <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">List price</td>
                                    <td style="padding:11px 16px; text-align:right; border-bottom:1px solid #EDF0F7;">
                                        &#8377;{{ number_format((float) $order->amount + (float) $order->discount_amount, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">
                                        Discount{{ $coupon ? ' ('.$coupon->code.')' : '' }}
                                    </td>
                                    <td style="padding:11px 16px; text-align:right; color:#0A8F5F; border-bottom:1px solid #EDF0F7;">
                                        &minus;&#8377;{{ number_format((float) $order->discount_amount, 2) }}
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">Paid on</td>
                                <td style="padding:11px 16px; text-align:right; border-bottom:1px solid #EDF0F7;">
                                    {{ $order->updated_at->format('d M Y, H:i') }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:11px 16px; color:#6B7A9B; border-bottom:1px solid #EDF0F7;">Payment id</td>
                                <td style="padding:11px 16px; text-align:right; font-family:monospace; font-size:12px; border-bottom:1px solid #EDF0F7;">
                                    {{ $order->razorpay_payment_id }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:13px 16px; font-weight:bold;">Total paid</td>
                                <td style="padding:13px 16px; text-align:right; font-weight:bold; font-size:16px;">
                                    &#8377;{{ number_format((float) $order->amount, 2) }}
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 8px;">
                            <tr>
                                <td style="background:#070B18; border-radius:999px;">
                                    <a href="{{ $videoUrl }}"
                                       style="display:inline-block; padding:12px 26px; font-size:13px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                        Watch it now
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:14px 0 0; font-size:13px; color:#6B7A9B;">
                            Everything you own is on your
                            <a href="{{ $dashboardUrl }}" style="color:#0E1529;">dashboard</a>.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:18px 28px 26px; border-top:1px solid #EDF0F7;">
                        <p style="margin:0; font-size:11px; line-height:1.6; color:#8795B8;">
                            {{ $brand }} is an education platform. We do not give buy or sell calls, and nothing
                            here is investment advice. Trading in securities carries risk of loss.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
