<!DOCTYPE html>
<html class="h-full text-[10px] lg:text-[16px]"  lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        @if(request()->get('website'))
        @cache('iris-favicon-'.request()->get('website')->id, 3600)
            <link rel="icon" type="image/png" sizes="16x16" href="{{ request()->get('website')->faviconSources(16, 16)['original'] ?? url('favicons/iris-favicon-16x16.png') }}">
            <link rel="icon" type="image/png" sizes="32x32" href="{{ request()->get('website')->faviconSources(32, 32)['original'] ?? url('favicons/iris-favicon-32x32.png') }}">
            <link rel="icon" type="image/png" sizes="48x48" href="{{ request()->get('website')->faviconSources(48, 48)['original'] ?? url('favicons/iris-favicon.ico') }}">
            <link rel="apple-touch-icon" sizes="180x180" href="{{ request()->get('website')->faviconSources(180, 180)['original'] ?? url('favicons/iris-apple-favicon-180x180.png') }}">
        @endcache
        @endif

        @if (config('app.env', 'production') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
        @endif

        <!-- Scripts -->
        @routes('retina')
        {{Vite::useHotFile('retina.hot')->useBuildDirectory('retina')->withEntryPoints(['resources/js/app-retina.js'])}}
        @inertiaHead
    </head>
    <body class="font-sans antialiased h-full text-slate-700">
        @inertia
    </body>
</html>
