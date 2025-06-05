<!DOCTYPE html>
<html class="h-full text-[14px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia></title>

       <!--  <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-sans:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=inter:100,200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" /> -->
        <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=fira-sans:200,400,500,700,900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
        <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Inter&family=Laila&family=Lobster&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:ital,wght@0,200;0,400;0,500;0,700;0,900;1,200;1,400;1,500;1,700;1,900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
        <noscript>
            <link rel="stylesheet" href="https://fonts.bunny.net/css?family=fira-sans:200,400,500,700,900&display=swap">
            <link href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Inter&family=Laila&family=Lobster&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:ital,wght@0,200;0,400;0,500;0,700;0,900;1,200;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
        </noscript>



        @if(request()->get('website'))
            @cache('iris-favicon-'.request()->get('website')->id, 3600)
            <link rel="icon" type="image/png" sizes="16x16" href="{{ request()->get('website')->faviconSources(16, 16)['original'] ?? url('favicons/iris-favicon-16x16.png') }}">
            <link rel="icon" type="image/png" sizes="32x32" href="{{ request()->get('website')->faviconSources(32, 32)['original'] ?? url('favicons/iris-favicon-32x32.png') }}">
            <link rel="icon" type="image/png" sizes="48x48" href="{{ request()->get('website')->faviconSources(48, 48)['original'] ?? url('favicons/iris-favicon.ico') }}">
            <link rel="apple-touch-icon" sizes="180x180" href="{{ request()->get('website')->faviconSources(180, 180)['original'] ?? url('favicons/iris-apple-favicon-180x180.png') }}">
            @endcache
        @endif


        @if (config('app.env', 'production') === 'staging')
            <meta name="robots" content="noindex">
        @endif

        <!-- Scripts -->
        @routes('iris')
        <!-- SSR: add Tailwind -->
        <link rel="stylesheet" href="{{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->asset('resources/css/app.css') }}">
        <link rel="stylesheet" href="{{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->asset('node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css') }}">
        
        {{ Vite::useHotFile('iris.hot')->ushttps://ds.test/app/sysadmin/dropshippingeBuildDirectory('iris')->withEntryPoints(['resources/js/app-iris.js']) }}
        @inertiaHead

        @if(request()->get('website') && Arr::get(request()->get('website')->settings, 'google_tag_id', ''))
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ Arr::get(request()->get("website")->settings, "google_tag_id", "") }}');</script>
            <!-- End Google Tag Manager -->
        @endif
    </head>
    <body class="font-sans antialiased h-full">
        @if(request()->get('website') && Arr::get(request()->get('website')->settings, 'google_tag_id', ''))
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W7RHCWJ8"
            height="0" width="0" style="display:none;visibility:hidden" title="google_tag"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
        @endif

        @inertia
    </body>
</html>
