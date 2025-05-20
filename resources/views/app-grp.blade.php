<!DOCTYPE html>
<html class="h-full text-[14px] lg:text-[16px]"  lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Aiku') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <link rel="icon" type="image/png" href="{{ url('favicon.png') }}">
        <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">

        @if (config('app.env', 'production') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
        @endif

        @routes('grp')
        {{Vite::useHotFile('grp.hot')->useBuildDirectory('grp')->withEntryPoints(['resources/js/app-grp.js'])}}
        @inertiaHead
    </head>
    <body class="font-sans antialiased h-full text-slate-700">
        @inertia
    </body>
</html>
