<!DOCTYPE html>
<html class="h-full text-[14px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-release" content="{{ config('sentry.release') }}">
    <title inertia>{{ $browserTitle ?? config('app.name') }}</title>


    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://media.aiku.io">
@php
    $irisPublishedLayout = request()->input('website')?->published_layout ?? [];
    $irisSiteFontCss = Arr::get($irisPublishedLayout, 'theme.container.properties.text.fontFamily');
    preg_match("/'([^']+)'/", (string) $irisSiteFontCss, $irisFontMatch);
    $irisSiteFont = $irisFontMatch[1] ?? null;

    /*
     * Website furniture (topbar, announcements, header, menu) can set per-block fonts in
     * inline styles independent of the theme font (awgifts.pl: theme unset, announcement
     * uses Raleway); those render above the fold on every page so they are critical too.
     */
    preg_match_all(
        "/(?:font-family:|fontFamily[\"']?\s*[:=])\s*\\\\?[\"']*\\\\?[\"']?([A-Za-z][A-Za-z ]+)/",
        json_encode($irisPublishedLayout),
        $irisFurnitureFontMatches
    );
    $irisFurnitureFonts = array_unique(array_map('trim', $irisFurnitureFontMatches[1] ?? []));
    $irisFontPreloads = [
        'Inter'     => [
            'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuLyfAZ9hiJ-Ck-8.woff2',
            'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuLyfAZFhiJ-Ck-_seA.woff2',
        ],
        'Raleway'   => [
            'https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyC0IT4ttDfA.woff2',
            'https://fonts.gstatic.com/s/raleway/v37/1Ptug8zYS_SKggPNyCMIT4ttDfCmxA.woff2',
        ],
        'Quicksand' => [
            'https://fonts.gstatic.com/s/quicksand/v37/6xK-dSZaM9iE8KbpRA_LJ3z8mH9BOJvgkP8o58a-wjw3UD0.woff2',
            'https://fonts.gstatic.com/s/quicksand/v37/6xK-dSZaM9iE8KbpRA_LJ3z8mH9BOJvgkP8o58i-wjw3UD2uFw.woff2',
        ],
    ];
    $irisCriticalFamilies = collect(['Inter', $irisSiteFont])->merge($irisFurnitureFonts)->filter()->unique();
    $irisPreloadFontUrls = $irisCriticalFamilies->flatMap(fn ($f) => $irisFontPreloads[$f] ?? [])->unique()->all();

    /*
     * Only Inter + the website's own font may block rendering; the other editor-pickable
     * families (decorative, used by some CMS text blocks) load after first paint so they
     * never sit on the LCP critical request chain.
     */
    $irisCss2Specs = [
        'Comfortaa'        => 'Comfortaa',
        'Inter'            => 'Inter',
        'Laila'            => 'Laila',
        'Lobster'          => 'Lobster',
        'Playfair'         => 'Playfair',
        'Playfair Display' => 'Playfair',
        'Port Lligat Slab' => 'Port+Lligat+Slab',
        'Quicksand'        => 'Quicksand',
        'Yatra One'        => 'Yatra+One',
        'Raleway'          => 'Raleway:wght@200;400;500;700;900',
    ];
    $irisCriticalFontSpecs = $irisCriticalFamilies->map(fn ($f) => $irisCss2Specs[$f] ?? null)->filter()->unique()->values();
    $irisDeferredFontSpecs = collect($irisCss2Specs)->values()->unique()->diff($irisCriticalFontSpecs)->values();
    $irisCss2Url = fn ($specs) => 'https://fonts.googleapis.com/css2?family='.$specs->implode('&family=').'&display=swap';
@endphp
    @foreach($irisPreloadFontUrls as $irisFontUrl)
        <link rel="preload" as="font" type="font/woff2" href="{{ $irisFontUrl }}" crossorigin>
    @endforeach
    <link rel="stylesheet" href="{{ $irisCss2Url($irisCriticalFontSpecs) }}">
    <link rel="stylesheet" href="{{ $irisCss2Url($irisDeferredFontSpecs) }}" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="{{ $irisCss2Url($irisDeferredFontSpecs) }}">
    </noscript>


    @if(request()->input('favicons'))
        <link rel="icon" type="image/png" sizes="16x16" href="{{ request()->input('favicons')['16']}}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ request()->input('favicons')['32'] }}">
        <link rel="icon" type="image/png" sizes="48x48" href="{{ request()->input('favicons')['48'] }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ request()->input('favicons')['180'] }}">
    @endif


    @if (config('app.env', 'production') === 'staging')
        <meta name="robots" content="noindex">
    @endif

    <!-- Scripts -->
    <!-- SSR: add Tailwind -->
    <link rel="stylesheet"
          href="{{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->asset('resources/css/app.css') }}">

    {{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->withEntryPoints(['resources/js/app-iris.js']) }}
    @inertiaHead

    <!-- Third parties (GTM) deferred to first interaction or shortly after load -->
    <script>
        (function () {
            var fired = false;
            var events = ["pointerdown", "keydown", "touchstart", "scroll"];
            var loadThirdParties = function () {
                if (fired) return;
                fired = true;
                events.forEach(function (e) { window.removeEventListener(e, loadThirdParties, { passive: true }); });

                @if(request()->input('website') && Arr::get(request()->input('website')->settings, 'google_tag_id', ''))
                (function (w, d, s, l, i) {
                    w[l] = w[l] || [];
                    w[l].push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
                    var f = d.getElementsByTagName(s)[0],
                        j = d.createElement(s), dl = l != "dataLayer" ? "&l=" + l : "";
                    j.async = true;
                    j.src = "https://www.googletagmanager.com/gtm.js?id=" + i + dl;
                    f.parentNode.insertBefore(j, f);
                })(window, document, "script", "gtmDataLayer", '{{ Arr::get(request()->input("website")->settings, "google_tag_id", "") }}');
                @endif

            };

            events.forEach(function (e) { window.addEventListener(e, loadThirdParties, { once: true, passive: true }); });
            if (document.readyState === "complete") {
                setTimeout(loadThirdParties, 3500);
            } else {
                window.addEventListener("load", function () { setTimeout(loadThirdParties, 3500); }, { once: true });
            }
        })();
    </script>

    <style>
        #jsd-widget {
            min-width: 370px !important;
            max-width: 370px !important;
            margin-bottom: 44px !important;
            margin-right: 22px !important;
        }
    </style>
</head>
<body class="font-sans antialiased h-full">

@if(request()->input('website') && Arr::get(request()->input('website')->settings, 'google_tag_id', ''))
    <!-- Google Tag Manager (noscript) -->
    <noscript>
        <iframe
            src="https://www.googletagmanager.com/ns.html?id={{ Arr::get(request()->input('website')->settings, 'google_tag_id', '') }}"
            height="0" width="0" style="display:none;visibility:hidden" title="google_tag"></iframe>
    </noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif


@inertia
</body>

@php
    $lbIrisConfig = [
        'chatEnabled' => (bool) Arr::get(request()->input('website')?->settings ?? [], 'enable_chat', false),
    ];
@endphp
<script>
    window.lbIrisConfig = {{ \Illuminate\Support\Js::from($lbIrisConfig) }};
</script>

</html>