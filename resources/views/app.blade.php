<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ auth()->user()?->appearance?->value ?? 'light' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Apply the saved appearance before the first paint so dark mode never flashes light. --}}
        <script>
            (function () {
                var root = document.documentElement;
                var mode = root.dataset.appearance;
                var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                root.classList.toggle('dark', dark);
            })();
        </script>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:400|barlow:400,500,600|barlow-condensed:500,600,700|jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
