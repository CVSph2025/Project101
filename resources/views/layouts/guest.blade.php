<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Cache busting for development -->
        <script>
            // Clear any cached React references
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        registration.unregister();
                    }
                });
            }
            // Clear browser cache for this session
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        if (name.includes('react') || name.includes('tsx')) {
                            caches.delete(name);
                        }
                    });
                });
            }
        </script>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <!-- HomyGo Logo -->
            <div class="flex justify-center mt-8">
                <a href="/">
                    <img src="{{ asset('H.svg') }}" alt="Homygo Logo" class="w-16 h-16 hover:scale-110 transition-transform duration-300">
                </a>
            </div>

            <!-- Login card below -->
            <div class="bg-white rounded-lg shadow-md p-6 max-w-sm mx-auto mt-4 w-full sm:max-w-md">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
