<!DOCTYPE html>
<html class="h-full text-[10px] lg:text-[16px]"  lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <!-- <meta name="csrf-token" content="{{ csrf_token() }}"> -->
        <meta name="shopify-api-key" content="{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key', $shopDomain ?? Auth::user()->name ) }}"/>
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Pupil') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900|inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ url('favicon.png') }}">
        <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">

        @if (config('app.env', 'production') === 'staging')
        <!-- == -->
        <meta name="robots" content="noindex">
        @endif

        <!-- Scripts -->
        @routes('pupil')
        {{Vite::useHotFile('pupil.hot')->useBuildDirectory('pupil')->withEntryPoints(['resources/js/app-pupil.js'])}}
        @inertiaHead
    </head>
    <!-- <body class="font-sans antialiased h-full text-slate-700"> -->
    <body class="font-sans antialiased text-slate-700">
        @if(\Osiset\ShopifyApp\Util::isMPAApplication())
            @include('shopify-app::partials.token_handler')
       @endif
        @inertia
    </body>
</html>
