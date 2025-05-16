<!DOCTYPE html>
<html class="h-full text-[10px] lg:text-[16px]"  lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Aiku') }}</title>
        <link rel="icon" type="image/png" href="{{ url('favicon.png') }}">
        <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">
        
        @if (config('app.env', 'production') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
        @endif

        @routes('aiku-public')
        {{Vite::useHotFile('aiku-public.hot')->useBuildDirectory('aiku-public')->withEntryPoints(['resources/js/app-aiku-public.js'])}}
        @inertiaHead
    </head>
    <body class="font-sans antialiased h-full">
        @inertia
    </body>
</html>
