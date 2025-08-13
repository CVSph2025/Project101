<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA Meta Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="application-name" content="Homygo">
        <meta name="apple-mobile-web-app-title" content="Homygo">
        <meta name="theme-color" content="#4f46e5">
        <meta name="msapplication-navbutton-color" content="#4f46e5">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="msapplication-starturl" content="/">
        
        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">
        
        <!-- Apple Touch Icons -->
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192x192.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-192x192.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/icons/icon-192x192.png">

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
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- PWA Install Prompt -->
        <div id="pwa-install-prompt" class="hidden fixed bottom-4 left-4 right-4 bg-indigo-600 text-white p-4 rounded-lg shadow-lg z-50 md:left-auto md:right-4 md:max-w-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1 mr-3">
                    <p class="text-sm font-medium">Install Homygo App</p>
                    <p class="text-xs opacity-90">Get quick access and offline features</p>
                </div>
                <div class="flex space-x-2">
                    <button id="pwa-install-button" class="bg-white text-indigo-600 px-3 py-1 rounded text-sm font-medium">
                        Install
                    </button>
                    <button id="pwa-dismiss-button" class="text-white opacity-75 hover:opacity-100">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <!-- Service Worker Registration -->
        <script>
            // Service Worker Registration
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js')
                        .then(function(registration) {
                            console.log('SW registered: ', registration);
                        })
                        .catch(function(registrationError) {
                            console.log('SW registration failed: ', registrationError);
                        });
                });
            }

            // PWA Install Prompt
            let deferredPrompt;
            const installPrompt = document.getElementById('pwa-install-prompt');
            const installButton = document.getElementById('pwa-install-button');
            const dismissButton = document.getElementById('pwa-dismiss-button');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Show install prompt if not dismissed
                if (!localStorage.getItem('pwa-dismissed')) {
                    installPrompt.classList.remove('hidden');
                }
            });

            installButton.addEventListener('click', (e) => {
                installPrompt.classList.add('hidden');
                
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        }
                        deferredPrompt = null;
                    });
                }
            });

            dismissButton.addEventListener('click', () => {
                installPrompt.classList.add('hidden');
                localStorage.setItem('pwa-dismissed', 'true');
            });

            // Hide prompt if app is already installed
            window.addEventListener('appinstalled', (evt) => {
                console.log('PWA was installed');
                installPrompt.classList.add('hidden');
            });

            // Network status indicator
            function updateNetworkStatus() {
                const status = navigator.onLine ? 'online' : 'offline';
                console.log('Network status:', status);
                
                if (!navigator.onLine) {
                    // Show offline indicator
                    if (!document.getElementById('offline-indicator')) {
                        const indicator = document.createElement('div');
                        indicator.id = 'offline-indicator';
                        indicator.className = 'fixed top-0 left-0 right-0 bg-red-500 text-white text-center py-2 text-sm z-50';
                        indicator.textContent = 'You are offline. Some features may be limited.';
                        document.body.appendChild(indicator);
                    }
                } else {
                    // Hide offline indicator
                    const indicator = document.getElementById('offline-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            }

            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);
            updateNetworkStatus(); // Check initial status
        </script>

        @stack('scripts')
    </body>
</html>
