<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Haus Stock') }}</title>

{{-- Apply the saved theme and sidebar state before anything paints (no flash of the wrong layout). --}}
<script>
    try {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || (! theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
        if (localStorage.getItem('sidebar') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch (e) {}
</script>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
