{{--
    Runs before the first paint, so the page never flashes the wrong theme.
    Must stay inline and synchronous in <head>, above the stylesheet.

    No stored choice falls back to dark: the public site is designed dark, and
    that is the first impression we want. Swap the last branch for
    `matchMedia('(prefers-color-scheme: light)').matches` to follow the OS.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var dark = stored ? stored === 'dark' : true;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
