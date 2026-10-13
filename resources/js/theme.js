/**
 * Theme store. The <html> class is already set by the inline head script, so
 * this only has to read it back and keep it in step from here on.
 */
export default {
    dark: true,

    init() {
        this.dark = document.documentElement.classList.contains('dark');
    },

    toggle() {
        this.apply(!this.dark);
    },

    apply(dark) {
        this.dark = dark;
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        } catch (e) {
            // Private browsing: the toggle still works for this page view.
        }
    },
};
