/**
 * Drives the "Buy now with Razorpay" button on a paid video page.
 *
 * Asks our own backend for an order (the price is decided there, never here),
 * opens Razorpay's modal, then posts the signed result back for verification.
 * Access is only ever granted server-side.
 */
export default function razorpayCheckout({ orderUrl, successUrl, failedUrl }) {
    return {
        loading: false,
        error: '',
        coupon: '',
        applied: null,

        async start() {
            if (this.loading) {
                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const options = await this.requestOrder();

                this.applied = options.coupon;

                if (typeof window.Razorpay !== 'function') {
                    throw new Error("Razorpay's checkout script did not load. Check your connection and refresh.");
                }

                this.openCheckout(options);
            } catch (e) {
                this.error = e.message;
                this.loading = false;
            }
        },

        async requestOrder() {
            const response = await fetch(orderUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                // The code is all we send; the price is decided on the server.
                body: JSON.stringify({ coupon_code: this.coupon.trim() }),
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(body.message || 'We could not start the checkout. Please try again.');
            }

            return body;
        },

        openCheckout(options) {
            const checkout = new window.Razorpay({
                ...options,
                handler: (response) => this.submit(successUrl, response),
                modal: {
                    // Closing the modal is not a failure, just a change of mind.
                    ondismiss: () => {
                        this.loading = false;
                    },
                },
            });

            checkout.on('payment.failed', (event) => {
                this.submit(failedUrl, {
                    razorpay_order_id: options.order_id,
                    reason: event?.error?.description ?? '',
                });
            });

            checkout.open();
        },

        /** Hand the result to Laravel as a normal form post so it can redirect. */
        submit(action, fields) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.style.display = 'none';

            const values = { _token: this.csrfToken(), ...fields };

            Object.entries(values).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value ?? '';
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },
    };
}
