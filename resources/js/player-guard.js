/**
 * Deterrents around the player.
 *
 * Be clear-eyed about what this is. None of it is a security control: devtools
 * can be opened before the page loads, the keybindings below are only some of
 * the ways in, and nothing here survives someone who simply screen-records.
 * It raises the effort for a casual viewer and does nothing to a determined
 * one. The protection that matters is server-side -- a signed, session-bound,
 * expiring stream link that only ever answers in small slices.
 *
 * Kept deliberately narrow so it cannot break ordinary use: copy, paste, find
 * and reload all still work, and nothing is blocked outside the player.
 */
export default function playerGuard() {
    return {
        blockShortcut(event) {
            // Only while the pointer or focus is on the player, so the rest of
            // the page behaves like a normal web page.
            if (! this.$el.contains(document.activeElement) && ! this.$el.matches(':hover')) {
                return;
            }

            const key = (event.key || '').toLowerCase();

            const blocked =
                event.key === 'F12'
                || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['i', 'j', 'c'].includes(key))
                || ((event.ctrlKey || event.metaKey) && ['u', 's'].includes(key));

            if (blocked) {
                event.preventDefault();
            }
        },
    };
}
