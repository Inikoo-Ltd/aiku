<!DOCTYPE html>
<html class="h-full text-[14px] lg:text-[16px]" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ $browserTitle ?? config('app.name') }}</title>


    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@php
    $irisSiteFontCss = Arr::get(request()->input('website')?->published_layout ?? [], 'theme.container.properties.text.fontFamily');
    preg_match("/'([^']+)'/", (string) $irisSiteFontCss, $irisFontMatch);
    $irisSiteFont = $irisFontMatch[1] ?? null;
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
    $irisPreloadFontUrls = collect(['Inter', $irisSiteFont])->filter()->unique()->flatMap(fn ($f) => $irisFontPreloads[$f] ?? [])->all();
@endphp
    @foreach($irisPreloadFontUrls as $irisFontUrl)
        <link rel="preload" as="font" type="font/woff2" href="{{ $irisFontUrl }}" crossorigin>
    @endforeach
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Comfortaa&family=Inter&family=Laila&family=Lobster&family=Playfair&family=Port+Lligat+Slab&family=Quicksand&family=Yatra+One&family=Raleway:wght@200;400;500;700;900&display=swap">


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

    {{ Vite::useHotFile('iris.hot')->useBuildDirectory('iris')->withEntryPoints(['resources/js/app-iris.js']) }}
    @inertiaHead

    <!-- Third parties (GTM, Luigi search) deferred to first interaction or shortly after load -->
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
                })(window, document, "script", "dataLayer", '{{ Arr::get(request()->input("website")->settings, "google_tag_id", "") }}');
                @endif

                @if(request()->input('website') && Arr::get(request()->input('website')->settings, 'luigisbox.lbx_code', ''))
                var lbx = document.createElement("script");
                lbx.async = true;
                lbx.src = "https://scripts.luigisbox.tech/{{ Arr::get(request()->input('website')->settings, 'luigisbox.lbx_code', '') }}.js";
                document.head.appendChild(lbx);
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

        .lb-iris-product-action {
            pointer-events: auto;
        }

        .lb-iris-product-action button {
            user-select: none;
            -webkit-user-select: none;
        }

        .lb-iris-cart-trigger,
        .lb-iris-back-in-stock,
        .lb-iris-cart-quantity {
            border: 0;
            cursor: pointer;
            height: 2.5rem;
            min-height: 2.5rem;
            min-width: 2.5rem;
            transition: all 0.2s ease-in-out;
        }

        .lb-iris-cart-trigger {
            align-items: center;
            background: #1f2937;
            border-radius: 9999px;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            color: #d1d5db;
            display: inline-flex;
            justify-content: center;
            width: 2.5rem;
        }

        .lb-iris-cart-trigger:hover {
            background: #374151;
        }

        .lb-iris-back-in-stock {
            align-items: center;
            background: #e5e7eb;
            border-radius: 9999px;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            color: #4b5563;
            display: inline-flex;
            justify-content: center;
            width: 2.5rem;
        }

        .lb-iris-back-in-stock:hover {
            background: #d1d5db;
        }

        .lb-iris-back-in-stock.is-active {
            color: #16a34a;
        }

        .lb-iris-cart-quantity {
            align-items: center;
            background: #e5e7eb;
            border-radius: 9999px;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            color: #111827;
            display: inline-flex;
            justify-content: space-between;
            overflow: hidden;
            padding: 0 0.25rem;
            width: 6rem;
        }

        .lb-iris-cart-quantity:hover {
            background: #d1d5db;
        }

        .lb-iris-cart-quantity.is-loading,
        .lb-iris-cart-trigger:disabled,
        .lb-iris-back-in-stock:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .lb-iris-quantity-button {
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 9999px;
            color: inherit;
            display: inline-flex;
            height: 2rem;
            justify-content: center;
            transition: background 0.2s ease-in-out, color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            width: 2rem;
        }

        .lb-iris-quantity-button:hover:not(:disabled) {
            background: rgb(255 255 255 / 0.8);
            color: #111827;
        }

        .lb-iris-quantity-button:disabled {
            cursor: not-allowed;
            opacity: 0.3;
        }

        .lb-iris-quantity-value {
            cursor: default;
            display: inline-flex;
            font-size: 0.875rem;
            font-weight: 500;
            justify-content: center;
            min-width: 1rem;
        }

        .lb-iris-icon {
            display: inline-flex;
            height: 1rem;
            width: 1rem;
        }

        .lb-iris-spinner {
            animation: lb-iris-spin 0.8s linear infinite;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 9999px;
            display: inline-flex;
            height: 1rem;
            width: 1rem;
        }

        @media (min-width: 768px) {
            .lb-iris-cart-quantity {
                justify-content: center;
                padding: 0;
                width: 2.5rem;
            }

            .lb-iris-product-action:hover .lb-iris-cart-quantity,
            .lb-iris-cart-quantity.is-loading {
                justify-content: space-between;
                padding: 0 0.25rem;
                width: 6rem;
            }

            .lb-iris-quantity-button {
                opacity: 0;
                pointer-events: none;
            }

            .lb-iris-product-action:hover .lb-iris-quantity-button,
            .lb-iris-cart-quantity.is-loading .lb-iris-quantity-button {
                opacity: 1;
                pointer-events: auto;
            }
        }

        @keyframes lb-iris-spin {
            100% {
                transform: rotate(360deg);
            }
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

<!-- Method: Convert price amount to price currency -->
<script>
    window.lbShopCurrencyCode = '{{ Arr::get(request()->input("currency_data", []), "code") }}' || null;

    window.lbFormatCurrency = function (amount) {
        if (amount === null || amount === undefined || amount === '') return '';
        if (!window.lbShopCurrencyCode) return amount;
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: window.lbShopCurrencyCode,
            }).format(parseFloat(amount) || 0);
        } catch (e) {
            return amount;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[convert-price-currency]').forEach(function (el) {
            var raw = el.getAttribute('convert-price-currency');
            if (raw) el.textContent = window.lbFormatCurrency(raw);
        });
    });

    var lbPriceObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                var els = node.querySelectorAll ? node.querySelectorAll('[convert-price-currency]') : [];
                els.forEach(function (el) {
                    var raw = el.getAttribute('convert-price-currency');
                    if (raw) el.textContent = window.lbFormatCurrency(raw);
                });
                if (node.hasAttribute && node.hasAttribute('convert-price-currency')) {
                    node.textContent = window.lbFormatCurrency(node.getAttribute('convert-price-currency'));
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        lbPriceObserver.observe(document.body, { childList: true, subtree: true });
    });
</script>

@php
    $shopType = request()->input('shop_type');
    $supportsBasket = !in_array($shopType, ['dropshipping', 'fulfilment'], true);
    $oosNotificationActive = request()->input('website')
        ?->shop
        ?->outboxes()
        ?->where('code', \App\Enums\Comms\Outbox\OutboxCodeEnum::OOS_NOTIFICATION)
        ?->where('state', 'active')
        ?->exists() ?? false;
    $lbIrisConfig = [
        'isLoggedIn' => (bool) request()->user(),
        'shopType' => $shopType,
        'supportsBasket' => $supportsBasket,
        'oosNotificationActive' => $oosNotificationActive,
        'currencyCode' => Arr::get(request()->input('currency_data', []), 'code'),
        'basketTransactionDataUrl' => route('iris.json.basket.transaction_data'),
        'productOrderingUrlTemplate' => route('iris.json.product.ecom_ordering_data', ['product' => '__PRODUCT__']),
        'basketTransactionProductDataUrlTemplate' => route('iris.json.basket_transaction_product_data', ['transaction' => '__TRANSACTION__']),
        'addToBasketUrlTemplate' => route('iris.models.transaction.store', ['product' => '__PRODUCT__']),
        'updateBasketUrlTemplate' => route('iris.models.transaction.update', ['transaction' => '__TRANSACTION__']),
        'addBackInStockUrlTemplate' => route('iris.models.remind_back_in_stock.store', ['product' => '__PRODUCT__']),
        'removeBackInStockUrlTemplate' => route('iris.models.remind_back_in_stock.delete', ['product' => '__PRODUCT__']),
    ];
@endphp
<script>
    window.lbIrisConfig = {{ \Illuminate\Support\Js::from($lbIrisConfig) }};
</script>

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

<script type="text/x-template" id="template-results">
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <result :result="result" v-for="(result, i) in results" :key="i"></result>
    </div>
</script>

<!-- Template: each product -->
<script type="text/x-template" id="template-result-item">
    <a :href="attributes?.web_url"
        :data-lb-product-card="Array.isArray(attributes.product_id) ? attributes.product_id[0] : attributes.product_id"
        :data-product-id="Array.isArray(attributes.product_id) ? attributes.product_id[0] : attributes.product_id"
        :data-product-stock="Array.isArray(attributes.stock_qty) ? attributes.stock_qty[0] : attributes.stock_qty"
        :data-product-availability="Array.isArray(attributes.availability) ? attributes.availability[0] : attributes.availability"
        class="text-gray-800 isolate h-full flex flex-col flex-grow no-underline">

        <!-- Image Area -->
        <div class="relative block w-full mb-1 rounded overflow-hidden aspect-square bg-white">
            <div class="relative w-full h-full">

                <!-- Product Image -->
                <img
                    v-if="attributes.image_link"
                    :src="attributes.image_link"
                    :alt="attributes.title || ''"
                    class="w-full h-full object-contain object-center select-none pointer-events-none"
                    :style="{ opacity: attributes.stock_qty[0] >= 1 ? 1 : 0.4 }"
                    loading="lazy"
                />

                <!-- No Image Placeholder -->
                <div v-else class="w-full h-full flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-300 opacity-20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                        <path d="M464 64H48C21.49 64 0 85.49 0 112v288c0 26.51 21.49 48 48 48h416c26.51 0 48-21.49 48-48V112c0-26.51-21.49-48-48-48zm16 336c0 8.822-7.178 16-16 16H48c-8.822 0-16-7.178-16-16V112c0-8.822 7.178-16 16-16h416c8.822 0 16 7.178 16 16v288zM112 232c30.928 0 56-25.072 56-56s-25.072-56-56-56-56 25.072-56 56 25.072 56 56 56zm0-80c13.234 0 24 10.766 24 24s-10.766 24-24 24-24-10.766-24-24 10.766-24 24-24zm207.029 23.029L224 270.059l-31.029-31.029c-9.373-9.373-24.569-9.373-33.941 0l-88 88A23.998 23.998 0 0 0 64 344v28c0 6.627 5.373 12 12 12h360c6.627 0 12-5.373 12-12v-92c0-6.365-2.529-12.47-7.029-16.971l-88-88c-9.373-9.372-24.569-9.372-33.942 0zM416 352H96v-4.686l80-80 48 48 112-112 80 80V352z"/>
                    </svg>
                </div>

                <!-- Out of Stock Overlay -->
                <div v-if="!(attributes.stock_qty[0] >= 1)" class="absolute inset-0 bg-white/40 flex items-end justify-center pb-2">
                    <span class="bg-red-50 text-red-600 text-xs font-medium px-2 py-1 rounded">Out of Stock</span>
                </div>

                <div
                    class="absolute right-2 bottom-2 z-10 lb-iris-product-action"
                    :data-lb-product-actions="Array.isArray(attributes.product_id) ? attributes.product_id[0] : attributes.product_id"
                    :data-product-id="Array.isArray(attributes.product_id) ? attributes.product_id[0] : attributes.product_id"
                    :data-product-stock="Array.isArray(attributes.stock_qty) ? attributes.stock_qty[0] : attributes.stock_qty"
                    :data-product-availability="Array.isArray(attributes.availability) ? attributes.availability[0] : attributes.availability"
                ></div>

            </div>
        </div>

        <!-- Info Below Image -->
        <div class="mt-2 flex-1 flex flex-col border-b border-gray-200 pb-2">

            <!-- Code + Stock Dot -->
            <div class="flex items-center gap-2">
                <span v-if="attributes.product_code" class="text-xs text-gray-500">{{ attributes.product_code[0] }}</span>
                <span
                    class="shrink-0 font-medium leading-snug"
                    :class="attributes.stock_qty[0] > 0 ? 'text-green-600' : 'text-red-600'"
                    style="font-size:0.85rem"
                >●</span>
            </div>

            <!-- Product Title -->
            <h3 class="font-bold !text-sm leading-4 mt-1 line-clamp-2 text-justify">
                <span v-if="attributes.units?.[0] != undefined && Number(attributes.units?.[0]) != 1" class="inline-block bg-blue-50 text-blue-600 text-xs font-medium px-1 rounded mr-1">
                    {{ Number(attributes.units?.[0]) }}x
                </span>
                {{ attributes.title }}
            </h3>

            <!-- HEADER: RRP + Profit -->
            <div v-if="attributes.price_rrp"
                class="mt-1 flex flex-col sm:flex-row sm:items-center justify-between gap-1 whitespace-nowrap text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px]">
                <div class="flex items-baseline gap-1 leading-none">
                    <span>RRP:</span>
                    <span class="font-medium relative xtop-[1px]" :convert-price-currency="attributes.price_rrp">{{ attributes.price_rrp }}</span>
                </div>
                <div v-if="attributes.margin" class="flex items-center gap-1">
                    <span>Profit:</span>
                    <span class="font-bold text-green-700">({{ attributes.margin }})</span>
                </div>
            </div>
        </div>

        <!-- Price (matching Prices3 layout) -->
        <div v-if="attributes.formatted_price || attributes.price_amount"
            class="font-sans border-gray-200 mt-2 mb-1 px-0 tabular-nums leading-none text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px]">

            

            <!-- PRICE ROW -->
            <div class="grid grid-cols-[auto_1fr] items-center gap-x-2">
                <div class="font-semibold whitespace-nowrap">
                    <span>Price</span>
                    <span class="font-light" style="font-size:0.8em"> (Excl. Vat)</span>
                </div>
                <div class="font-bold text-right min-w-0">
                    <!-- <span v-if="attributes.formatted_price" class="whitespace-nowrap">
                        {{ attributes.formatted_price }}
                    </span> -->
                    <span vxelse class="whitespace-nowrap" :convert-price-currency="attributes.price_amount || attributes.price">
                        {{ attributes.price_amount || attributes.price }}
                    </span>
                </div>
            </div>

        </div>
        <div v-else class="mt-auto pt-1">
            <a href="/app/login" class="block w-full text-center text-xs py-2 px-2 bg-gray-800 text-white rounded hover:bg-gray-700" style="transition:background-color 0.15s" @click.stop>
                Login for prices
            </a>
        </div>

    </a>
</script>

<!-- <script type="text/x-template" id="template-result-default">
    <div class="lb-result-default">
        rrrrrrrrrrrrrrrrrrrrrrrrrrrrrr {{ attributes.title }} - {{ url }}
    </div>
</script> -->

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


<!-- Script: handle button 'add to basket' and button 'remind me when in stock' email' -->
<script>
    (function () {
        const config = window.lbIrisConfig;

        if (!config) {
            return;
        }

        const state = {
            basketLoaded: false,
            basketPromise: null,
            basketByProductId: {},
            orderingByProductId: {},
            orderingPromises: {},
            updateTimers: {},
            loadingProductIds: new Set(),
        };

        const icon = {
            cart: '<svg viewBox="0 0 576 512" class="lb-iris-icon" fill="currentColor" aria-hidden="true"><path d="M528.12 301.319l47.273-208A24 24 0 0 0 552 64H128l-9.401-44.447A24 24 0 0 0 95.104 0H24A24 24 0 0 0 0 24v16a24 24 0 0 0 24 24h39.104l70.395 332.447A63.997 63.997 0 1 0 215.271 416h209.458a64 64 0 1 0 62.482-80H181.817l-6.545-32h328.848a24 24 0 0 0 24-18.681z"></path></svg>',
            plus: '<svg viewBox="0 0 448 512" class="lb-iris-icon" fill="currentColor" aria-hidden="true"><path d="M256 80c0-17.673-14.327-32-32-32s-32 14.327-32 32V208H64c-17.673 0-32 14.327-32 32s14.327 32 32 32H192V400c0 17.673 14.327 32 32 32s32-14.327 32-32V272H384c17.673 0 32-14.327 32-32s-14.327-32-32-32H256V80z"></path></svg>',
            minus: '<svg viewBox="0 0 448 512" class="lb-iris-icon" fill="currentColor" aria-hidden="true"><path d="M416 240c0 17.673-14.327 32-32 32H64c-17.673 0-32-14.327-32-32s14.327-32 32-32H384c17.673 0 32 14.327 32 32z"></path></svg>',
            envelope: '<svg viewBox="0 0 512 512" class="lb-iris-icon" fill="currentColor" aria-hidden="true"><path d="M502.3 190.8 327.4 338c-15.3 12.8-37.5 12.8-52.8 0L9.7 190.8C3.9 186 0 178.8 0 171V112c0-26.5 21.5-48 48-48h416c26.5 0 48 21.5 48 48v59c0 7.8-3.9 15-9.7 19.8zM0 214.7l163.5 137.6a144 144 0 0 0 185 0L512 214.7V400c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V214.7z"></path></svg>',
            envelopeCheck: '<svg viewBox="0 0 640 512" class="lb-iris-icon" fill="currentColor" aria-hidden="true"><path d="M320 352c-15.9 0-31.3-5.7-43.4-15.9L32 144.1V400c0 8.8 7.2 16 16 16h288c0 17.7 2.8 34.8 8 50.9c-7.8 1.9-15.9 3.1-24 3.1H48C21.5 470 0 448.5 0 422V102C0 75.5 21.5 54 48 54H464c26.5 0 48 21.5 48 48V237.4c-10.2-5-21-9-32-11.8V144.1L363.4 240.1C351.3 250.3 335.9 256 320 256zm0-64c8.2 0 16.2-2.8 22.6-8.1L480 166.3V102c0-8.8-7.2-16-16-16H48c-8.8 0-16 7.2-16 16v64.3L297.4 279.9c6.4 5.3 14.4 8.1 22.6 8.1zM616 352c13.3 0 24 10.7 24 24s-10.7 24-24 24H483.9l35 35c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-76-76c-9.4-9.4-9.4-24.6 0-33.9l76-76c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-35 35H616z"></path></svg>',
        };

        const spinner = '<span class="lb-iris-spinner" aria-hidden="true"></span>';

        const toNumber = (value) => {
            const number = Number(value);

            return Number.isFinite(number) ? number : 0;
        };

        const productIdOf = (element) => {
            const productId = parseInt(element.dataset.productId || element.dataset.lbProductActions || '', 10);

            return Number.isFinite(productId) ? productId : null;
        };

        const stockOf = (element) => {
            const stock = parseInt(element.dataset.productStock || '0', 10);

            return Number.isFinite(stock) ? stock : 0;
        };

        const getBasketState = (productId) => {
            if (!state.basketByProductId[productId]) {
                state.basketByProductId[productId] = {
                    transaction_id: null,
                    quantity_ordered: 0,
                    quantity_ordered_new: 0,
                };
            }

            return state.basketByProductId[productId];
        };

        const getOrderingState = (productId) => {
            if (!state.orderingByProductId[productId]) {
                state.orderingByProductId[productId] = {
                    back_in_stock: false,
                };
            }

            return state.orderingByProductId[productId];
        };

        const productMounts = (productId) => (
            document.querySelectorAll('[data-lb-product-actions][data-product-id="' + productId + '"]')
        );

        const updateMountStock = (productId, stock) => {
            productMounts(productId).forEach((mount) => {
                mount.dataset.productStock = String(stock);
                const card = mount.closest('[data-lb-product-card]');

                if (card) {
                    card.dataset.productStock = String(stock);
                }
            });
        };

        const routeFor = (template, token, value) => template.replace(token, String(value));

        const refreshExternalCustomerData = () => {
            if (window.aikuIris && typeof window.aikuIris.refreshCustomerData === 'function') {
                window.aikuIris.refreshCustomerData();
            }
        };

        const syncTransactionProductData = async (productId, transactionId) => {
            if (!transactionId) {
                return;
            }

            try {
                const response = await window.axios.get(
                    routeFor(config.basketTransactionProductDataUrlTemplate, '__TRANSACTION__', transactionId)
                );
                const basket = getBasketState(productId);

                basket.transaction_id = response.data?.transaction_id ?? transactionId;
                basket.quantity_ordered = toNumber(response.data?.quantity_ordered);
                basket.quantity_ordered_new = toNumber(response.data?.quantity_ordered_new ?? response.data?.quantity_ordered);

                if (typeof response.data?.stock !== 'undefined') {
                    updateMountStock(productId, toNumber(response.data.stock));
                }
            } catch (error) {
                console.error(error);
            } finally {
                refreshProductMounts(productId);
            }
        };

        const ensureBasketLoaded = async () => {
            if (!config.isLoggedIn || state.basketLoaded || state.basketPromise) {
                return state.basketPromise;
            }

            state.basketPromise = window.axios.get(config.basketTransactionDataUrl)
                .then((response) => {
                    Object.entries(response.data || {}).forEach(([productId, basket]) => {
                        state.basketByProductId[productId] = {
                            transaction_id: basket?.transaction_id ?? null,
                            quantity_ordered: toNumber(basket?.quantity_ordered),
                            quantity_ordered_new: toNumber(basket?.quantity_ordered_new ?? basket?.quantity_ordered),
                        };
                    });
                    state.basketLoaded = true;
                })
                .catch((error) => {
                    console.error(error);
                })
                .finally(() => {
                    state.basketPromise = null;
                    refreshAllProductMounts();
                });

            return state.basketPromise;
        };

        const ensureOrderingLoaded = async (productId) => {
            if (!config.isLoggedIn || !productId || state.orderingPromises[productId]) {
                return state.orderingPromises[productId];
            }

            if (state.orderingByProductId[productId] && typeof state.orderingByProductId[productId].back_in_stock !== 'undefined' && state.orderingByProductId[productId].loaded) {
                return Promise.resolve(state.orderingByProductId[productId]);
            }

            state.orderingPromises[productId] = window.axios.get(
                routeFor(config.productOrderingUrlTemplate, '__PRODUCT__', productId)
            ).then((response) => {
                const data = response.data || {};
                const ordering = getOrderingState(productId);
                const basket = getBasketState(productId);

                ordering.back_in_stock = Boolean(data.back_in_stock ?? data.is_back_in_stock);
                ordering.loaded = true;

                if (typeof data.stock !== 'undefined') {
                    updateMountStock(productId, toNumber(data.stock));
                }

                if (data.transaction_id || data.quantity_ordered) {
                    basket.transaction_id = data.transaction_id ?? basket.transaction_id;
                    basket.quantity_ordered = toNumber(data.quantity_ordered);
                    basket.quantity_ordered_new = toNumber(data.quantity_ordered_new ?? data.quantity_ordered);
                }

                return ordering;
            }).catch((error) => {
                console.error(error);
            }).finally(() => {
                delete state.orderingPromises[productId];
                refreshProductMounts(productId);
            });

            return state.orderingPromises[productId];
        };

        const setLoading = (productId, value) => {
            if (value) {
                state.loadingProductIds.add(productId);
            } else {
                state.loadingProductIds.delete(productId);
            }

            refreshProductMounts(productId);
        };

        const commitBasketState = async (productId) => {
            const basket = getBasketState(productId);
            const nextQuantity = toNumber(basket.quantity_ordered_new);
            const currentQuantity = toNumber(basket.quantity_ordered);

            if (nextQuantity === currentQuantity || state.loadingProductIds.has(productId)) {
                return;
            }

            setLoading(productId, true);

            try {
                if (!currentQuantity && nextQuantity > 0) {
                    const response = await window.axios.post(
                        routeFor(config.addToBasketUrlTemplate, '__PRODUCT__', productId),
                        {
                            quantity: nextQuantity,
                        }
                    );

                    basket.transaction_id = response.data?.transaction_id ?? basket.transaction_id;
                    basket.quantity_ordered = toNumber(response.data?.quantity_ordered ?? nextQuantity);
                    basket.quantity_ordered_new = toNumber(response.data?.quantity_ordered ?? nextQuantity);

                    refreshExternalCustomerData();

                    if (basket.transaction_id) {
                        syncTransactionProductData(productId, basket.transaction_id);
                    }
                } else {
                    if (!basket.transaction_id) {
                        await ensureOrderingLoaded(productId);
                    }

                    if (!basket.transaction_id) {
                        throw new Error('Missing basket transaction');
                    }

                    await window.axios.post(
                        routeFor(config.updateBasketUrlTemplate, '__TRANSACTION__', basket.transaction_id),
                        {
                            quantity_ordered: nextQuantity,
                            quantity: nextQuantity,
                        }
                    );

                    basket.quantity_ordered = nextQuantity;
                    basket.quantity_ordered_new = nextQuantity;

                    if (!nextQuantity) {
                        basket.transaction_id = null;
                    }

                    refreshExternalCustomerData();

                    if (basket.transaction_id) {
                        syncTransactionProductData(productId, basket.transaction_id);
                    }
                }
            } catch (error) {
                basket.quantity_ordered_new = currentQuantity;
                console.error(error);
            } finally {
                setLoading(productId, false);
                refreshProductMounts(productId);
            }
        };

        const scheduleBasketCommit = (productId) => {
            if (state.updateTimers[productId]) {
                window.clearTimeout(state.updateTimers[productId]);
            }

            state.updateTimers[productId] = window.setTimeout(() => {
                delete state.updateTimers[productId];
                commitBasketState(productId);
            }, 900);
        };

        const updateQuantity = (productId, stock, quantity) => {
            const basket = getBasketState(productId);
            const clampedQuantity = Math.max(0, Math.min(quantity, stock));

            basket.quantity_ordered_new = clampedQuantity;
            refreshProductMounts(productId);

            if (basket.quantity_ordered_new !== toNumber(basket.quantity_ordered)) {
                scheduleBasketCommit(productId);
            }
        };

        const instantAddToBasket = (productId, stock) => {
            const basket = getBasketState(productId);

            basket.quantity_ordered_new = Math.max(0, Math.min(1, stock));
            refreshProductMounts(productId);
            commitBasketState(productId);
        };

        const toggleBackInStock = async (productId, addReminder) => {
            if (!productId || state.loadingProductIds.has(productId)) {
                return;
            }

            setLoading(productId, true);

            try {
                const url = addReminder
                    ? routeFor(config.addBackInStockUrlTemplate, '__PRODUCT__', productId)
                    : routeFor(config.removeBackInStockUrlTemplate, '__PRODUCT__', productId);

                if (addReminder) {
                    await window.axios.post(url, {});
                } else {
                    await window.axios.delete(url);
                }

                getOrderingState(productId).back_in_stock = addReminder;
                getOrderingState(productId).loaded = true;
                refreshExternalCustomerData();
            } catch (error) {
                console.error(error);
            } finally {
                setLoading(productId, false);
                refreshProductMounts(productId);
            }
        };

        const bindCartTrigger = (mount, productId, stock) => {
            const button = mount.querySelector('[data-lb-cart-trigger]');

            if (!button) {
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                instantAddToBasket(productId, stock);
            });
        };

        const bindQuantityControls = (mount, productId, stock) => {
            const decrement = mount.querySelector('[data-lb-qty-decrement]');
            const increment = mount.querySelector('[data-lb-qty-increment]');

            if (decrement) {
                decrement.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const basket = getBasketState(productId);
                    updateQuantity(productId, stock, toNumber(basket.quantity_ordered_new) - 1);
                });
            }

            if (increment) {
                increment.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const basket = getBasketState(productId);
                    updateQuantity(productId, stock, toNumber(basket.quantity_ordered_new) + 1);
                });
            }
        };

        const bindBackInStock = (mount, productId, isBackInStock) => {
            const button = mount.querySelector('[data-lb-back-in-stock]');

            if (!button) {
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleBackInStock(productId, !isBackInStock);
            });
        };

        const collectActionMounts = (root, productIds) => {
            if (!(root instanceof HTMLElement)) {
                return;
            }

            if (root.matches('[data-lb-product-actions]')) {
                const productId = productIdOf(root);

                if (productId) {
                    productIds.add(productId);
                }
            }

            root.querySelectorAll('[data-lb-product-actions]').forEach((mount) => {
                const productId = productIdOf(mount);

                if (productId) {
                    productIds.add(productId);
                }
            });
        };

        const cartTriggerMarkup = (isLoading) => (
            '<button type="button" data-lb-cart-trigger class="lb-iris-cart-trigger" title="Add to basket" ' + (isLoading ? 'disabled' : '') + '>' +
                (isLoading ? spinner : icon.cart) +
            '</button>'
        );

        const cartQuantityMarkup = (currentQuantity, stock, isLoading) => (
            '<div class="lb-iris-cart-quantity' + (isLoading ? ' is-loading' : '') + '">' +
                '<button type="button" data-lb-qty-decrement class="lb-iris-quantity-button" ' + ((isLoading || currentQuantity <= 0) ? 'disabled' : '') + '>' + icon.minus + '</button>' +
                '<span class="lb-iris-quantity-value">' + currentQuantity + '</span>' +
                '<button type="button" data-lb-qty-increment class="lb-iris-quantity-button" ' + ((isLoading || currentQuantity >= stock) ? 'disabled' : '') + '>' + icon.plus + '</button>' +
            '</div>'
        );

        const backInStockMarkup = (isBackInStock, isLoading) => (
            '<button type="button" data-lb-back-in-stock class="lb-iris-back-in-stock' + (isBackInStock ? ' is-active' : '') + '" title="' + (isBackInStock ? 'You will be notified' : 'Remind me when back in stock') + '" ' + (isLoading ? 'disabled' : '') + '>' +
                (isLoading ? spinner : (isBackInStock ? icon.envelopeCheck : icon.envelope)) +
            '</button>'
        );

        const refreshActionMount = (mount) => {
            const productId = productIdOf(mount);

            mount.onclick = (event) => {
                event.preventDefault();
                event.stopPropagation();
            };

            if (!config.isLoggedIn || !productId) {
                mount.innerHTML = '';
                return;
            }

            const stock = stockOf(mount);
            const basket = getBasketState(productId);
            const ordering = getOrderingState(productId);
            const isLoading = state.loadingProductIds.has(productId);
            const hasQuantity = toNumber(basket.quantity_ordered) > 0 || toNumber(basket.quantity_ordered_new) > 0;
            const renderKey = [
                config.isLoggedIn ? '1' : '0',
                config.supportsBasket ? '1' : '0',
                config.oosNotificationActive ? '1' : '0',
                productId,
                stock,
                toNumber(basket.quantity_ordered),
                toNumber(basket.quantity_ordered_new),
                ordering.back_in_stock ? '1' : '0',
                isLoading ? '1' : '0',
            ].join(':');

            if (stock <= 0) {
                if (!config.oosNotificationActive) {
                    if (mount.innerHTML !== '') {
                        mount.innerHTML = '';
                    }
                    delete mount.dataset.renderKey;
                    return;
                }

                if (mount.dataset.renderKey !== renderKey) {
                    mount.innerHTML = backInStockMarkup(Boolean(ordering.back_in_stock), isLoading);
                    mount.dataset.renderKey = renderKey;
                    bindBackInStock(mount, productId, Boolean(ordering.back_in_stock));
                }

                if (!ordering.loaded) {
                    ensureOrderingLoaded(productId);
                }

                return;
            }

            if (!config.supportsBasket) {
                if (mount.innerHTML !== '') {
                    mount.innerHTML = '';
                }
                delete mount.dataset.renderKey;
                return;
            }

            if (!hasQuantity) {
                if (mount.dataset.renderKey !== renderKey) {
                    mount.innerHTML = cartTriggerMarkup(isLoading);
                    mount.dataset.renderKey = renderKey;
                    bindCartTrigger(mount, productId, stock);
                }
                return;
            }

            if (mount.dataset.renderKey !== renderKey) {
                mount.innerHTML = cartQuantityMarkup(toNumber(basket.quantity_ordered_new), stock, isLoading);
                mount.dataset.renderKey = renderKey;
                bindQuantityControls(mount, productId, stock);
            }
        };

        const refreshProductMounts = (productId) => {
            productMounts(productId).forEach((mount) => {
                refreshActionMount(mount);
            });
        };

        const refreshAllProductMounts = () => {
            document.querySelectorAll('[data-lb-product-actions]').forEach((mount) => {
                refreshActionMount(mount);
            });
        };

        const boot = () => {
            if (!window.axios) {
                window.setTimeout(boot, 50);
                return;
            }

            if (config.isLoggedIn && config.supportsBasket) {
                ensureBasketLoaded();
            }

            refreshAllProductMounts();

            const observer = new MutationObserver((mutations) => {
                const productIds = new Set();

                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        collectActionMounts(node, productIds);
                    });
                });

                if (!productIds.size) {
                    return;
                }

                productIds.forEach((productId) => {
                    refreshProductMounts(productId);
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot, { once: true });
        } else {
            boot();
        }
    })();
</script>
@endverbatim

</html>