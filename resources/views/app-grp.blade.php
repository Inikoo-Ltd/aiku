<!DOCTYPE html>
<html class="h-full text-[12px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title inertia>{{ config('app.name', 'Aiku') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://auth.getbee.io">
    <link rel="preconnect" href="https://app-rsrc.getbee.io">
    <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900|inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Laila&family=Lobster&family=Playfair+Display&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:wght@200;400;500;700;900&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">


    @if (config('app.env') === 'local')
        <link rel="icon" href="{{ url('favicon_local.svg') }}" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon_local-32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon_local-180.png') }}">
    @else
        <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon-32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon-180.png') }}">
    @endif

    @if (config('app.env') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
    @endif

    @routes('grp')
    {{Vite::useHotFile('grp.hot')->useBuildDirectory('grp')->withEntryPoints(['resources/js/app-grp.js'])}}
    @inertiaHead


</head>
<body class="font-sans antialiased h-full text-slate-700">
@inertia
<script>
    window.component = {
        php: @json(str_replace('\\', '/', Route::currentRouteAction())),
        vue: ''
    }
</script>
</body>
</html>
