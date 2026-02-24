<!DOCTYPE html>
<html class="h-full text-[14px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ $browserTitle ?? config('app.name') }}</title>


    <link rel="preload" as="style" href="https://fonts.bunny.net/css?family=fira-sans:200,400,500,700,900&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Inter&family=Laila&family=Lobster&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:ital,wght@0,200;0,400;0,500;0,700;0,900;1,200;1,400;1,500;1,700;1,900&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=fira-sans:200,400,500,700,900&display=swap">
        <link
            href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Inter&family=Laila&family=Lobster&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:ital,wght@0,200;0,400;0,500;0,700;0,900;1,200;1,400;1,500;1,700;1,900&display=swap"
            rel="stylesheet">
    </noscript>

    @if(request()->input('website') && Arr::get(request()->input('website')->settings, 'jira_help_desk_widget', ''))
        @if(request()->header('X-Logged-Status') !== null || auth()->check())
            @if(request()->header('X-Logged-Status') === 'In' || auth()->check())
                <script async data-jsd-embedded
                        data-key="{{Arr::get(request()->input('website')->settings, 'jira_help_desk_widget', '') }}"
                        data-base-url="https://jsd-widget.atlassian.com"
                        src="https://jsd-widget.atlassian.com/assets/embed.js"></script>
            @endif
        @endif
    @endif



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
    @routes('iris')
    <!-- SSR: add Tailwind -->
    <link rel="stylesheet"
          href="{{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->asset('resources/css/app.css') }}">
    <link rel="stylesheet"
          href="{{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->asset('node_modules/@fortawesome/fontawesome-free/css/svg-with-js.min.css') }}">

    {{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->withEntryPoints(['resources/js/app-iris.js']) }}
    @inertiaHead

    @if(request()->input('website') && Arr::get(request()->input('website')->settings, 'google_tag_id', ''))
        <!-- Google Tag Manager -->
        <script>(function (w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    "gtm.start":
                        new Date().getTime(), event: "gtm.js"
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s), dl = l != "dataLayer" ? "&l=" + l : "";
                j.async = true;
                j.src =
                    "https://www.googletagmanager.com/gtm.js?id=" + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, "script", "dataLayer", '{{ Arr::get(request()->input("website")->settings, "google_tag_id", "") }}');</script>
        <!-- End Google Tag Manager -->
    @endif

    <!-- Section: Luigi analytics -->
    @if(request()->input('website') && Arr::get(request()->input('website')->settings, 'luigisbox.lbx_code', ''))
        <script async
                src="https://scripts.luigisbox.tech/{{ Arr::get(request()->input('website')->settings, 'luigisbox.lbx_code', '') }}.js"></script>
    @endif

    <style>
        #jsd-widget {
            bottom: 44px !important;
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

@verbatim
<!-- Script: template custom quick search for all (category, sub, department, tag, collection) -->
<script type="text/x-template" id="template-quick-search-custom-base">
    <div v-if="items && items.length" class="lb-qs relative mt-3">
        <!-- Left arrow -->
        <button type="button" class="hidden sm:flex absolute left-0 top-1/2 -translate-y-1/2 z-10
            h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90
            shadow-sm hover:bg-white hover:shadow transition
            disabled:opacity-40 disabled:hover:shadow-sm disabled:hover:bg-white/90" aria-label="Scroll left"
            onclick="window.lbQuickSearchScroll(this, -1)">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                <path fill-rule="evenodd"
                    d="M12.707 15.707a1 1 0 0 1-1.414 0l-5-5a1 1 0 0 1 0-1.414l5-5a1 1 0 1 1 1.414 1.414L8.414 10l4.293 4.293a1 1 0 0 1 0 1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Right arrow -->
        <button type="button" class="hidden sm:flex absolute right-0 top-1/2 -translate-y-1/2 z-10
            h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90
            shadow-sm hover:bg-white hover:shadow transition
            disabled:opacity-40 disabled:hover:shadow-sm disabled:hover:bg-white/90" aria-label="Scroll right"
            onclick="window.lbQuickSearchScroll(this, 1)">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                <path fill-rule="evenodd"
                    d="M7.293 4.293a1 1 0 0 1 1.414 0l5 5a1 1 0 0 1 0 1.414l-5 5a1 1 0 1 1-1.414-1.414L11.586 10 7.293 5.707a1 1 0 0 1 0-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Rail -->
        <div data-lb-rail class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory px-1 pb-2
            [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden
            sm:px-10" role="list">
            <a v-for="item in items"
                :key="(item.url || '') + (item.attributes && item.attributes.title ? item.attributes.title : '')"
                :href="(item.attributes && item.attributes.web_url && item.attributes.web_url[0]) ? item.attributes.web_url[0] : item.url"
                class="snap-start shrink-0 w-[200px] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow-md transition"
                role="listitem">
                <!-- Image (full cover, square) -->
                <div class="relative w-full aspect-square">
                    <img
                        v-if="(item.attributes && item.attributes.image_link) ? true : false"
                        :src="(item.attributes && item.attributes.image_link) ? item.attributes.image_link : ''"
                        :alt="(item.attributes && item.attributes.title) ? item.attributes.title : 'Category'"
                        class="absolute inset-0 h-full w-full object-cover" loading="lazy"
                    />
                    <span v-else class="absolute inset-0 flex items-center justify-center opacity-30 text-3xl md:text-5xl">
                        <svg class="svg-inline--fa fa-image fa-fw" aria-hidden="true" focusable="false" data-prefix="fal" data-icon="image" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="--e447116b-layout\?\.app\?\.webpage_layout\?\.container\?\.properties\?\.text\?\.fontFamily: 'Raleway', sans-serif;"><path class="" fill="currentColor" d="M464 64H48C21.49 64 0 85.49 0 112v288c0 26.51 21.49 48 48 48h416c26.51 0 48-21.49 48-48V112c0-26.51-21.49-48-48-48zm16 336c0 8.822-7.178 16-16 16H48c-8.822 0-16-7.178-16-16V112c0-8.822 7.178-16 16-16h416c8.822 0 16 7.178 16 16v288zM112 232c30.928 0 56-25.072 56-56s-25.072-56-56-56-56 25.072-56 56 25.072 56 56 56zm0-80c13.234 0 24 10.766 24 24s-10.766 24-24 24-24-10.766-24-24 10.766-24 24-24zm207.029 23.029L224 270.059l-31.029-31.029c-9.373-9.373-24.569-9.373-33.941 0l-88 88A23.998 23.998 0 0 0 64 344v28c0 6.627 5.373 12 12 12h360c6.627 0 12-5.373 12-12v-92c0-6.365-2.529-12.47-7.029-16.971l-88-88c-9.373-9.372-24.569-9.372-33.942 0zM416 352H96v-4.686l80-80 48 48 112-112 80 80V352z"></path></svg>
                    </span>
                </div>

                
                <!-- Title bar (no white gap, centered) -->
                <div class="bg-gray-100 px-3 text-sm font-semibold text-gray-800 text-center
                    flex items-center justify-center h-[52px] leading-snug">
                    <span class="line-clamp-2">
                        {{ item.attributes.title }}
                    </span>
                </div>
            </a>
        </div>
    </div>
</script>

<!-- List: template quick search to overrides -->
<script type="text/x-template" id="template-quick-search-category"></script>
<script type="text/x-template" id="template-quick-search-brand"></script>
<script type="text/x-template" id="template-quick-search-tag"></script>
<script type="text/x-template" id="template-quick-search-department"></script>
<script type="text/x-template" id="template-quick-search-sub_department"></script>
<script type="text/x-template" id="template-quick-search-collection"></script>

<!-- Script: slider functionality -->
<script>
    window.lbQuickSearchScroll = function(btn, direction) {
        const root = btn.closest('.lb-qs');
        if (!root) return;

        const rail = root.querySelector('[data-lb-rail]');
        if (!rail) return;

        // Scroll kira-kira 1 "card" lebar + gap
        const firstCard = rail.querySelector('a');
        const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 180;
        const gap = 16; // sesuai gap-4
        const amount = (cardWidth + gap) * 1.5;

        rail.scrollBy({
            left: direction * amount,
            behavior: 'smooth'
        });

        // Optional: update disabled state setelah scroll settle
        window.requestAnimationFrame(() => window.lbQuickSearchUpdateArrows(root));
        setTimeout(() => window.lbQuickSearchUpdateArrows(root), 250);
    };

    window.lbQuickSearchUpdateArrows = function(root) {
        const rail = root.querySelector('[data-lb-rail]');
        if (!rail) return;

        const leftBtn = root.querySelector('button[aria-label="Scroll left"]');
        const rightBtn = root.querySelector('button[aria-label="Scroll right"]');

        const maxScrollLeft = rail.scrollWidth - rail.clientWidth;
        const atStart = rail.scrollLeft <= 1;
        const atEnd = rail.scrollLeft >= (maxScrollLeft - 1);

        if (leftBtn) leftBtn.disabled = atStart;
        if (rightBtn) rightBtn.disabled = atEnd;
    };

    // Optional: auto-init arrow state on page load and on resize
    window.addEventListener('load', () => {
        document.querySelectorAll('.lb-qs').forEach(root => window.lbQuickSearchUpdateArrows(root));
    });
    window.addEventListener('resize', () => {
        document.querySelectorAll('.lb-qs').forEach(root => window.lbQuickSearchUpdateArrows(root));
    });

    // Optional: update while user scrolls (smooth)
    document.addEventListener('scroll', (e) => {
        const rail = e.target;
        if (!(rail instanceof HTMLElement)) return;
        if (!rail.matches('[data-lb-rail]')) return;
        const root = rail.closest('.lb-qs');
        if (root) window.lbQuickSearchUpdateArrows(root);
    }, true);
</script>

<!-- Script: to copy Template Custom to all Quick Search -->
<script>
    (function () {
        const base = document.getElementById('template-quick-search-custom-base');
        if (!base) return;

        const targets = [
            'template-quick-search-category',
            'template-quick-search-brand',
            'template-quick-search-tag',
            'template-quick-search-department',
            'template-quick-search-sub_department',
            'template-quick-search-collection',
        ];

        targets.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;

        // kalau template itu sudah ada isinya, jangan ditimpa
        if (el.innerHTML && el.innerHTML.trim().length > 0) return;

        el.innerHTML = base.innerHTML;
        });
    })();
</script>
@endverbatim

</html>