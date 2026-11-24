<!DOCTYPE html>
<html class="h-full text-[12px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title data-inertia>{{ $socialMeta['title'] ?? config('app.name', 'Aiku') }}</title>
    @isset($socialMeta)
        <meta name="description" content="{{ $socialMeta['description'] }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $socialMeta['site_name'] }}">
        <meta property="og:title" content="{{ $socialMeta['title'] }}">
        <meta property="og:description" content="{{ $socialMeta['description'] }}">
        <meta property="og:url" content="{{ $socialMeta['url'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $socialMeta['title'] }}">
        <meta name="twitter:description" content="{{ $socialMeta['description'] }}">
        @if ($socialMeta['image'])
            <meta property="og:image" content="{{ $socialMeta['image'] }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $socialMeta['image_alt'] ?? $socialMeta['site_name'] }}">
            <meta name="twitter:image" content="{{ $socialMeta['image'] }}">
        @endif
    @endisset

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://app-rsrc.getbee.io">
    <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900|inter:100,200,300,400,500,600,700,800,900|lato:400,400i,700,700i,900&display=swap" rel="stylesheet" />
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Laila&family=Lobster&family=Playfair+Display&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:wght@200;400;500;700;900&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">


    @if (!empty($socialMeta['icon']))
        <link rel="icon" type="image/png" href="{{ $socialMeta['icon'] }}">
        <link rel="apple-touch-icon" href="{{ $socialMeta['icon'] }}">
    @elseif (config('app.env') === 'local')
        <link rel="icon" href="{{ url('favicon_local.svg?v=3') }}" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon_local-32.png?v=3') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon_local-180.png?v=3') }}">
    @elseif (config('app.env') === 'staging')
        <link rel="icon" href="{{ url('favicon_staging.svg?v=3') }}" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon_staging-32.png?v=3') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon_staging-180.png?v=3') }}">
    @else
        <link rel="icon" href="{{ url('favicon.svg?v=3') }}" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon-32.png?v=3') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon-180.png?v=3') }}">
    @endif

    @empty($socialMeta)
        <link rel="manifest" href="{{ url('grp.webmanifest') }}">
    @endempty

    @if (config('app.env') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
    @endif

    @if (\App\Actions\UI\Grp\ShowGrpRoutesScript::isServed())
        <script src="{{ route('grp.routes_script', ['v' => \App\Actions\UI\Grp\ShowGrpRoutesScript::version()]) }}"></script>
    @else
        @routes('grp')
    @endif
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
