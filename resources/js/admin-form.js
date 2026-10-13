/**
 * Admin form behaviour.
 *
 * `adminForm` is the plain case: guard against double submits and show the
 * button working. `adminUpload` replaces the native submit with XHR so a
 * 120 MB video upload can report real progress, and so validation errors come
 * back without throwing away everything already typed into the form.
 */

/**
 * Shared shape so <x-admin.field-error> works inside either component.
 *
 * A factory, not a shared object literal: spreading one literal into every
 * component would hand them all the same `errors` reference, so one form's
 * validation errors would surface in another's fields.
 *
 * Note for anyone extending this: these properties are spread into the
 * components below, and a spread evaluates getters rather than copying them.
 * Anything derived from state belongs here as a plain method, called as
 * `thing()` from the template, never as `get thing()`.
 */
function baseState() {
    return {
        submitting: false,
        errors: {},
        failure: '',
        // Set while one batch of `invalid` events is still arriving.
        collecting: false,
        hiddenBlockers: [],

        errorsFor(field) {
            return this.errors[field] || [];
        },

        /**
         * The browser cancels a submit when any control fails its constraints,
         * and if that control is hidden it cannot focus it to explain why --
         * so the button appears to do nothing at all. No submit event fires,
         * but `invalid` does (in the capture phase; it does not bubble), which
         * is the only chance to tell the admin what happened.
         *
         * The inactive branches of the video form are disabled rather than
         * merely hidden, so this should never fire for an invisible field. It
         * is the net under that: a silently dead button is the one failure an
         * admin cannot report usefully.
         */
        reportBlocked(event) {
            const control = event.target;

            if (! this.collecting) {
                this.collecting = true;
                this.errors = {};
                this.hiddenBlockers = [];
                // Every invalid control fires inside this one task, so a
                // timeout of 0 runs once, after all of them have reported.
                setTimeout(() => this.summariseBlocked(), 0);
            }

            this.errors = {
                ...this.errors,
                [control.name || control.id || 'field']:
                    [control.validationMessage || 'This value is not valid.'],
            };

            if (this.isHidden(control)) {
                this.hiddenBlockers.push(this.labelFor(control));
            }
        },

        summariseBlocked() {
            this.collecting = false;

            this.failure = this.hiddenBlockers.length
                ? this.hiddenBlockers.join(', ')
                  + ' needs a valid value, but this form is currently hiding it.'
                  + ' Change the options above to reveal that field, or reload the page to start clean.'
                : 'Please fix the highlighted fields.';
        },

        isHidden(control) {
            return control.type !== 'hidden' && control.offsetParent === null;
        },

        labelFor(control) {
            const label = control.id
                ? control.ownerDocument.querySelector('label[for="' + control.id + '"]')
                : null;

            return (label?.textContent || control.name || 'A field').trim();
        },
    };
}

export function adminForm() {
    return {
        ...baseState(),

        // Native submit: let the browser post, just stop a second one.
        start(event) {
            if (this.submitting) {
                event.preventDefault();

                return;
            }

            this.submitting = true;
        },
    };
}

export function adminUpload({
    maxVideoBytes = 0,
    maxThumbnailBytes = 0,
    fallbackRedirect = '',
    tokenUrl = '',
}) {
    return {
        ...baseState(),
        progress: 0,
        uploading: false,

        async submit(event) {
            if (this.submitting) {
                return;
            }

            const form = event.target;

            this.errors = {};
            this.failure = '';

            const tooBig = this.oversizeMessage(form);

            if (tooBig) {
                this.failure = tooBig;

                return;
            }

            this.submitting = true;
            this.progress = 0;
            this.uploading = this.hasFile(form);

            try {
                const result = await this.post(form);
                window.location.href = result.redirect || fallbackRedirect || window.location.href;
            } catch (e) {
                this.submitting = false;
                this.uploading = false;
                this.progress = 0;

                if (e.validation) {
                    this.errors = e.validation;
                    this.failure = 'Please fix the highlighted fields.';
                    this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (e.expired) {
                    this.failure = await this.renewSession(form)
                        ? 'Your session had expired, so nothing was saved. It has been renewed — press Save again.'
                        : 'Your session expired and could not be renewed. Sign in again in another tab, then press Save.';
                } else {
                    this.failure = e.message;
                }
            }
        },

        /**
         * A file over php.ini's post_max_size is truncated server-side and
         * surfaces as a baffling "page expired", so catch it here instead.
         */
        oversizeMessage(form) {
            const limits = [
                ['video_file', maxVideoBytes, 'video'],
                ['thumbnail', maxThumbnailBytes, 'thumbnail'],
            ];

            for (const [name, limit, label] of limits) {
                const file = this.pickedFile(form, name);

                if (file && limit && file.size > limit) {
                    return `That ${label} is ${this.mb(file.size)} MB. The limit is ${this.mb(limit)} MB.`;
                }
            }

            return '';
        },

        hasFile(form) {
            return ['video_file', 'thumbnail'].some((name) => this.pickedFile(form, name));
        },

        /**
         * A disabled input keeps its files but is left out of FormData, so a
         * file picked and then abandoned must not be measured, nor reported as
         * uploading.
         */
        pickedFile(form, name) {
            const input = form.elements[name];

            return input && ! input.disabled ? input.files?.[0] : null;
        },

        /**
         * "Please try again" is a dead end on its own: the _token in the DOM
         * is still the expired one, so every retry 419s identically. Fetch a
         * token for the new session and write it back into this form. Scoped
         * to the form, because the page also carries logout form tokens.
         */
        async renewSession(form) {
            if (! tokenUrl) {
                return false;
            }

            try {
                const response = await fetch(tokenUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                const { token } = await response.json();
                const field = form.querySelector('input[name="_token"]');

                if (! token || ! field) {
                    return false;
                }

                field.value = token;
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);

                return true;
            } catch (e) {
                return false;
            }
        },

        post(form) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                xhr.open('POST', form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        this.progress = Math.round((e.loaded / e.total) * 100);
                    }
                });

                xhr.addEventListener('load', () => {
                    const body = this.json(xhr);

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(body);

                        return;
                    }

                    if (xhr.status === 422) {
                        reject({ validation: body.errors || {} });

                        return;
                    }

                    // Recoverable, and the only failure worth retrying in place.
                    if (xhr.status === 419) {
                        reject({ expired: true });

                        return;
                    }

                    reject(new Error(body.message || this.statusMessage(xhr.status)));
                });

                xhr.addEventListener('error', () => reject(new Error('The connection dropped before the upload finished.')));
                xhr.addEventListener('abort', () => reject(new Error('The upload was cancelled.')));

                xhr.send(new FormData(form));
            });
        },

        json(xhr) {
            try {
                return JSON.parse(xhr.responseText);
            } catch (e) {
                return {};
            }
        },

        statusMessage(status) {
            if (status === 413) {
                return 'The server rejected the upload as too large.';
            }

            return 'Something went wrong saving this. It has been logged — please try again.';
        },

        mb(bytes) {
            return Math.round((bytes / 1048576) * 10) / 10;
        },
    };
}
