/**
 * Remembers where the learner stopped and picks the lesson back up next time.
 *
 * Only works with the native <video> player (uploaded files). External embeds
 * run in a cross-origin iframe, so their playback position is not readable.
 */
const SAVE_EVERY_SECONDS = 15;
const MIN_RESUME_SECONDS = 10;   // not worth resuming a few seconds in
const END_MARGIN_SECONDS = 15;   // near the end? start from the top instead

export default function videoProgress({ saveUrl, resumeAt = 0 }) {
    return {
        resumedFrom: 0,
        lastSaved: 0,

        init() {
            const video = this.$refs.player;

            if (!video) {
                return;
            }

            video.addEventListener('loadedmetadata', () => {
                const duration = video.duration;
                const worthResuming =
                    resumeAt > MIN_RESUME_SECONDS &&
                    Number.isFinite(duration) &&
                    resumeAt < duration - END_MARGIN_SECONDS;

                if (worthResuming) {
                    video.currentTime = resumeAt;
                    this.lastSaved = resumeAt;
                    this.resumedFrom = resumeAt;
                }
            });

            video.addEventListener('timeupdate', () => {
                const at = Math.floor(video.currentTime);

                // Covers scrubbing backwards as well as ordinary playback.
                if (Math.abs(at - this.lastSaved) >= SAVE_EVERY_SECONDS) {
                    this.save(at);
                }
            });

            video.addEventListener('pause', () => this.save(Math.floor(video.currentTime)));

            // Finished: report the full length. The server decides whether that
            // clears the 90% bar, and sends us back to the top next visit.
            video.addEventListener('ended', () => {
                this.resumedFrom = 0;
                this.save(Math.floor(video.duration) || 0);
            });

            const flush = () => {
                if (video.ended) {
                    return;
                }
                this.beacon(Math.floor(video.currentTime));
            };

            window.addEventListener('pagehide', flush);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    flush();
                }
            });
        },

        startOver() {
            const video = this.$refs.player;

            video.currentTime = 0;
            this.resumedFrom = 0;
            this.save(0);
            video.play?.();
        },

        save(seconds) {
            this.lastSaved = seconds;

            fetch(saveUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'X-CSRF-TOKEN': this.token(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ progress_seconds: seconds }),
            }).catch(() => {
                // A dropped progress ping is not worth bothering the learner about.
            });
        },

        beacon(seconds) {
            this.lastSaved = seconds;

            // sendBeacon cannot set headers, so the CSRF token rides in the body.
            const form = new FormData();
            form.append('_token', this.token());
            form.append('progress_seconds', seconds);

            navigator.sendBeacon?.(saveUrl, form);
        },

        clock(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainder = String(Math.floor(seconds % 60)).padStart(2, '0');

            return `${minutes}:${remainder}`;
        },

        token() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },
    };
}
