<!DOCTYPE html>
<html class="h-full text-[12px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Aiku') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    <link href="https://fonts.bunny.net/css?family=inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />


    @if (config('app.env') === 'local')
        <link rel="icon" type="image/png" href="{{ url('favicon_local.png') }}">
        <link rel="icon" href="{{ url('favicon_local.svg') }}" type="image/svg+xml">
    @else
        <link rel="icon" type="image/png" href="{{ url('favicon.png') }}">
        <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">
    @endif

    @if (config('app.env') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
    @endif

    @routes('grp')
    {{Vite::useHotFile('grp.hot')->useBuildDirectory('grp')->withEntryPoints(['resources/js/app-grp.js'])}}
    @inertiaHead

    @if (config('app.env') === 'production')
    <style>
        iframe[name='JSD widget'] {
            /* display: block; */
            bottom: 10px !important;
            right: 0 !important;
            /* margin-right: 55px;
            margin-bottom: 15px; */

            opacity: .80;
        }
    </style>
    <script data-jsd-embedded data-key="efb5edc3-6921-4d19-8fa2-300ec340b897" data-base-url="https://jsd-widget.atlassian.com" src="https://jsd-widget.atlassian.com/assets/embed.js"></script>
    @endif
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
