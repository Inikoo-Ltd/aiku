@php
    $consentCountries = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH'];
    $needsConsent = in_array(mb_strtoupper((string) request()->header('CF-IPCountry')), $consentCountries, true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'aiku' }}</title>
    <meta name="description" content="{{ $description ?? 'aiku is an open source operating system for commerce: ERP, warehouse, fulfilment, storefronts, marketplaces, CRM and marketing in one codebase.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $title ?? 'aiku' }}">
    <meta property="og:description" content="{{ $description ?? 'aiku is an open source operating system for commerce.' }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:image" content="{{ url('favicon_public-180.png?v=3') }}">
    <meta name="twitter:card" content="summary_large_image">
    @if (config('app.env') === 'staging')
        <meta name="robots" content="noindex">
    @endif
    <link rel="icon" href="{{ url('favicon_public.svg?v=3') }}" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon_public-32.png?v=3') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon_public-180.png?v=3') }}">
    <link rel="alternate" type="application/xml" title="Sitemap" href="{{ route('aiku-public.sitemap') }}">
    <link rel="alternate" type="application/rss+xml" title="aiku — engineering notes" href="{{ route('aiku-public.feed') }}">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=newsreader:400,400i,600|inter:400,500,600|jetbrains-mono:400&display=swap">
    @production
        <script>
            (function(w, d, t, u, o) {
                w[u] = w[u] || [], o.ts = (new Date).getTime();
                var n = d.createElement(t);
                n.src = "https://bat.bing.net/bat.js?ti=" + o.ti + ("uetq" != u ? "&q=" + u : ""),
                n.async = 1, n.onload = n.onreadystatechange = function() {
                    var s = this.readyState;
                    s && "loaded" !== s && "complete" !== s ||
                    (o.q = w[u], w[u] = new UET(o), w[u].push("pageLoad"),
                    n.onload = n.onreadystatechange = null)
                };
                var i = d.getElementsByTagName(t)[0];
                i.parentNode.insertBefore(n, i);
            })(window, document, "script", "uetq", {
                ti: "343269034",
                enableAutoSpaTracking: true
            });
        </script>
        <script>
            window.uetq = window.uetq || [];
            window.aikuNeedsConsent = @json($needsConsent);
            window.aikuConsent = null;
            try { window.aikuConsent = localStorage.getItem('aiku-consent'); } catch (e) {}
            window.uetq.push('consent', 'default', {
                'ad_storage': (!window.aikuNeedsConsent || window.aikuConsent === 'granted') ? 'granted' : 'denied'
            });
        </script>
    @endproduction
    {!! $head ?? '' !!}
    <style>
        :root {
            --paper: #fbfaf6; --ink: #1c1b22; --muted: #5b5a66; --rule: #e5e2d8;
            --accent: #3730a3; --accent-soft: #eceaff; --code-bg: #f1efe8;
            --serif: "Newsreader", Georgia, "Times New Roman", serif;
            --sans: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
            --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        @media (prefers-color-scheme: dark) {
            :root { --paper: #14131a; --ink: #ecebf2; --muted: #a4a2b3; --rule: #2b2a36; --accent: #a5b4fc; --accent-soft: #23224a; --code-bg: #1e1d28; }
            img.sketch { filter: invert(0.92) hue-rotate(180deg); }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: var(--sans); font-size: 17px; line-height: 1.6; }
        body::before { content: ""; position: fixed; inset: 0; z-index: -2; pointer-events: none; opacity: .9;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='400'%3E%3Cfilter id='p'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' seed='2'/%3E%3CfeColorMatrix values='0 0 0 0 0.86 0 0 0 0 0.82 0 0 0 0 0.72 0 0 0 0.45 -0.1'/%3E%3C/filter%3E%3Crect width='400' height='400' filter='url(%23p)'/%3E%3C/svg%3E"); }
        .hero { position: relative; }
        .hero::before { content: ""; position: absolute; z-index: -1; right: -8%; top: -20%; width: 46%; aspect-ratio: 1; border-radius: 48% 52% 55% 45% / 50% 45% 55% 50%; background: #8b5cf6; opacity: .16; filter: blur(28px); mix-blend-mode: multiply; }
        .hero::after { content: ""; position: absolute; z-index: -1; left: -14%; bottom: -30%; width: 30%; aspect-ratio: 1; border-radius: 55% 45% 48% 52% / 45% 55% 50% 50%; background: #f3c04a; opacity: .18; filter: blur(26px); mix-blend-mode: multiply; }
        @media (prefers-color-scheme: dark) { body::before, .hero::before, .hero::after { display: none; } }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 24px; }
        .narrow { max-width: 720px; margin: 0 auto; padding: 0 24px; }
        header.site { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 20px; padding: 28px 0; border-bottom: 1px solid var(--rule); }
        header.site .brand { display: flex; align-items: center; gap: 12px; color: var(--ink); font-family: var(--serif); font-size: 26px; font-weight: 600; letter-spacing: -0.01em; }
        header.site .brand svg { width: 34px; height: 37px; color: var(--ink); transform-box: fill-box; transform-origin: 50% 85%; animation: aiku-logo-dance 14s ease-in-out infinite; }
        @keyframes aiku-logo-dance {
            0%, 4%, 11%, 100% { transform: translate(0, 0) rotate(0) scale(1); }
            5% { transform: translate(0, -1px) rotate(-3deg) scale(1.01); }
            7% { transform: translate(1px, -3px) rotate(3deg) scale(1.03); }
            9% { transform: translate(-1px, -1px) rotate(-2deg) scale(1.01); }
            10% { transform: translate(0, 0) rotate(1deg) scale(1); }
        }
        @media (prefers-reduced-motion: reduce) {
            header.site .brand svg { animation: none; }
        }
        header.site nav { display: flex; gap: 28px; font-size: 15px; font-weight: 500; }
        header.site nav a { color: var(--muted); }
        header.site nav a[aria-current] { color: var(--ink); }
        h1, h2, h3 { font-family: var(--serif); font-weight: 600; letter-spacing: -0.015em; line-height: 1.15; margin: 0 0 0.5em; }
        h1 { font-size: clamp(40px, 6vw, 68px); }
        h2 { font-size: clamp(28px, 3.6vw, 40px); margin-top: 1.6em; }
        h3 { font-size: 24px; margin-top: 1.4em; }
        p.lede { font-family: var(--serif); font-size: clamp(20px, 2.4vw, 26px); line-height: 1.4; color: var(--muted); max-width: 34em; }
        .eyebrow { font-size: 13px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); margin-bottom: 18px; }
        .hero { padding: 96px 0 48px; }
        .hero .actions { margin-top: 36px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; font-weight: 500; }
        .btn { display: inline-block; padding: 12px 20px; border: 1px solid var(--ink); border-radius: 999px; color: var(--ink); font-weight: 500; }
        .btn:hover { background: var(--ink); color: var(--paper); text-decoration: none; }
        figure { margin: 56px 0; }
        figure img { width: 100%; height: auto; border: 1px solid var(--rule); border-radius: 8px; display: block; }
        figcaption { margin-top: 12px; font-size: 14px; color: var(--muted); }
        section.chapter { padding: 24px 0; border-top: 1px solid var(--rule); }
        .modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0 48px; margin: 32px 0 0; padding: 0; list-style: none; }
        .modules li { padding: 18px 0; border-bottom: 1px solid var(--rule); }
        .modules b { display: block; font-family: var(--serif); font-size: 21px; font-weight: 600; }
        .modules span { color: var(--muted); font-size: 15px; }
        .logos { display: flex; flex-wrap: wrap; gap: 28px 36px; align-items: center; margin: 28px 0; opacity: 0.85; }
        .logos img { height: 22px; width: auto; }
        @media (prefers-color-scheme: dark) { .logos img { filter: invert(1) hue-rotate(180deg); } }
        .stack { font-family: var(--mono); font-size: 14px; color: var(--muted); line-height: 1.9; }
        .posts { list-style: none; margin: 28px 0 0; padding: 0; }
        .posts li { padding: 26px 0; border-top: 1px solid var(--rule); display: grid; grid-template-columns: 140px 1fr; gap: 24px; }
        .posts time { color: var(--muted); font-size: 14px; padding-top: 6px; }
        .posts h3 { margin: 0 0 6px; font-size: 24px; }
        .posts h3 a { color: var(--ink); }
        .posts h3 a:hover { color: var(--accent); }
        .posts p { margin: 0; color: var(--muted); }
        .tags { margin-top: 10px; font-size: 13px; color: var(--muted); }
        .tags span, .tags a { margin-right: 12px; color: var(--muted); }
        .pager { display: flex; justify-content: space-between; align-items: center; padding: 28px 0 0; border-top: 1px solid var(--rule); font-weight: 500; }
        .pager .muted { color: var(--muted); font-size: 14px; font-weight: 400; }
        .search { display: flex; gap: 12px; align-items: center; margin: 32px 0 4px; position: relative; }
        .search input { flex: 1; max-width: 420px; font: inherit; font-size: 15px; padding: 9px 14px; border: 1px solid var(--rule); border-radius: 999px; background: transparent; color: var(--ink); }
        .search input:focus { outline: none; border-color: var(--accent); }
        .search a { font-size: 14px; }
        .search-results { position: relative; list-style: none; margin: -4px 0 4px; padding: 0; max-width: 420px; border: 1px solid var(--rule); border-radius: 12px; background: var(--paper); }
        .search-results li { padding: 10px 14px; border-top: 1px solid var(--rule); }
        .search-results li:first-child { border-top: none; }
        .search-results li a { font-weight: 500; }
        .search-results li p { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
        .search-results li.empty, .search-results li.engine { color: var(--muted); font-size: 13px; }
        .search-results mark { background: var(--accent-soft); color: inherit; border-radius: 3px; padding: 0 2px; }
        header.site .search-header { display: flex; align-items: center; position: relative; }
        .search-results.floating { position: absolute; top: calc(100% + 8px); right: 0; width: 340px; max-width: 90vw; margin: 0; z-index: 50; box-shadow: 0 8px 24px rgba(0,0,0,.12); max-height: 60vh; overflow-y: auto; }
        .search-results.floating li p { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .search .search-results.floating { left: 0; right: auto; width: 100%; max-width: 420px; }
        header.site .search-header input { font: inherit; font-size: 14px; padding: 7px 12px; border: 1px solid var(--rule); border-radius: 999px; background: transparent; color: var(--ink); width: 160px; }
        header.site .search-header input:focus { outline: none; border-color: var(--accent); }
        .tagbar { display: flex; flex-wrap: wrap; gap: 8px; margin: 28px 0 8px; }
        .tagbar a { font-size: 13px; padding: 5px 11px; border: 1px solid var(--rule); border-radius: 999px; color: var(--muted); }
        .tagbar a span { opacity: .6; margin-left: 3px; }
        .tagbar a[aria-current] { background: var(--ink); color: var(--paper); border-color: var(--ink); }
        .tagbar a:hover { text-decoration: none; border-color: var(--ink); }
        .tagbar details { display: contents; }
        .tagbar summary { list-style: none; cursor: pointer; font-size: 13px; padding: 5px 11px; border: 1px dashed var(--rule); border-radius: 999px; color: var(--muted); }
        .tagbar summary::-webkit-details-marker { display: none; }
        .tagbar summary span { opacity: .6; margin-left: 3px; }
        .tagbar details[open] summary { display: none; }
        .tagbar details > div { display: contents; }
        article.post { padding: 72px 0; }
        article.post .meta { color: var(--muted); font-size: 15px; margin-bottom: 40px; }
        article.post .body { font-size: 18px; }
        article.post .body p, article.post .body li { line-height: 1.75; }
        article.post .body h2 { font-size: 30px; margin-top: 2em; }
        article.post .body h3 { font-size: 22px; }
        article.post .body blockquote { margin: 1.6em 0; padding-left: 1.2em; border-left: 3px solid var(--accent); color: var(--muted); font-family: var(--serif); font-size: 21px; }
        article.post .body pre { background: var(--code-bg); padding: 18px 20px; border-radius: 8px; overflow-x: auto; font-size: 14px; line-height: 1.55; }
        article.post .body code { font-family: var(--mono); font-size: 0.92em; background: var(--code-bg); padding: 0.1em 0.35em; border-radius: 4px; }
        article.post .body pre code { background: none; padding: 0; }
        article.post .body table { border-collapse: collapse; width: 100%; font-size: 15px; margin: 1.5em 0; }
        article.post .body th, article.post .body td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--rule); vertical-align: top; }
        article.post .body aside.tldr { margin: 0 0 2.2em; padding: 18px 22px; border-left: 3px solid var(--accent); background: var(--accent-soft); border-radius: 0 8px 8px 0; font-family: var(--serif); font-size: 19px; line-height: 1.5; }
        article.post .body aside.tldr strong { font-family: var(--sans); font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); display: block; margin-bottom: 6px; }
        article.post .body aside.tldr.bottom { margin: 2.5em 0 0; }
        article.post .body aside.technical { margin: 2.4em 0; padding: 18px 22px; background: var(--code-bg); border: 1px solid var(--rule); border-radius: 8px; font-family: var(--mono); font-size: 13.5px; line-height: 1.7; }
        article.post .body aside.technical strong { display: block; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
        article.post .body aside.technical ul { margin: 0; padding-left: 1.1em; }
        article.post .body aside.technical li { line-height: 1.7; }
        article.post .body aside.technical a { word-break: break-all; }
        article.post .body aside.technical code { background: none; padding: 0; }
        article.post .body aside.wayfinder { margin: 2.5em 0 0; padding: 18px 22px; border: 1px solid var(--accent); background: var(--accent-soft); border-radius: 8px; font-size: 15.5px; line-height: 1.7; }
        article.post .body aside.wayfinder strong { display: block; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
        article.post .body aside.wayfinder ul { margin: 0; padding-left: 1.1em; }
        article.post .body aside.wayfinder b { font-weight: 600; }
        article.post .body img { max-width: 100%; height: auto; border: 1px solid var(--rule); border-radius: 8px; }
        footer.site { position: relative; margin-top: 96px; padding: 40px 0 64px; border-top: 1px solid var(--rule); color: var(--muted); font-size: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        footer.site a { color: var(--muted); margin-right: 20px; }
        .footer-license { display: flex; align-items: center; gap: 7px; }
        .footer-animation-trigger { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 31px; margin: -7px 0; padding: 0; border: 0; background: transparent; color: var(--ink); line-height: 0; cursor: pointer; transition: transform .18s ease; }
        .footer-animation-trigger:hover { transform: translateY(-1px) scale(1.07); }
        .footer-animation-trigger:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 4px; }
        .footer-animation-trigger:disabled { cursor: default; opacity: .65; }
        .footer-animation-trigger .footer-mascot-art { width: 26px; height: 29px; }
        .footer-mascot { --gait-speed: .42s; position: absolute; z-index: 1; top: -45px; left: 0; width: 40px; height: 44px; color: var(--ink); pointer-events: none; opacity: 0; }
        .footer-mascot > .footer-mascot-art { width: 100%; height: 100%; transform-origin: 50% 88%; }
        .footer-mascot-art, .footer-mascot-art img { display: block; }
        .footer-mascot-art img { width: 100%; height: 100%; }
        .footer-character { --gait-speed: .42s; }
        .footer-character .footer-mascot-art img { transform-origin: 50% 88%; animation: aiku-character-gait var(--gait-speed) steps(2, end) infinite; }
        .footer-mascot[data-routine="patrol"] { animation: aiku-footer-patrol 18s linear both; }
        .footer-mascot[data-routine="patrol"] > .footer-mascot-art { animation: aiku-footer-patrol-stunt 18s ease-in-out both; }
        .footer-mascot[data-routine="dance"] { left: calc(50% - 20px); --gait-speed: .28s; animation: aiku-footer-appearance 7s ease both; }
        .footer-mascot[data-routine="dance"] > .footer-mascot-art { animation: aiku-footer-dance 7s ease-in-out both; }
        .footer-mascot[data-routine="dash"] { --gait-speed: .2s; animation: aiku-footer-dash 6s cubic-bezier(.3, .05, .65, .95) both; }
        .footer-mascot[data-routine="dash"] > .footer-mascot-art { animation: aiku-footer-hop .48s ease-in-out infinite alternate; }
        @keyframes aiku-character-gait { 0% { transform: translateY(0) rotate(-1.5deg); } 100% { transform: translateY(-2px) rotate(1.5deg); } }
        @keyframes aiku-footer-patrol {
            0% { left: 0; opacity: 0; transform: scaleX(1); }
            3% { opacity: 1; }
            34%, 50% { left: calc(50% - 20px); opacity: 1; transform: scaleX(1); }
            72%, 76% { left: calc(100% - 40px); opacity: 1; transform: scaleX(1); }
            79% { left: calc(100% - 40px); opacity: 1; transform: scaleX(-1); }
            97% { left: 0; opacity: 1; transform: scaleX(-1); }
            100% { left: 0; opacity: 0; transform: scaleX(-1); }
        }
        @keyframes aiku-footer-patrol-stunt {
            0%, 34%, 49%, 100% { transform: translateY(0) rotate(0) scale(1); }
            37% { transform: translateY(-4px) rotate(-10deg) scale(1.04); }
            40% { transform: translateY(0) rotate(10deg) scale(1); }
            43% { transform: translateY(-12px) rotate(180deg) scale(1.12); }
            46% { transform: translateY(0) rotate(360deg) scale(1); }
        }
        @keyframes aiku-footer-appearance { 0%, 100% { opacity: 0; } 8%, 92% { opacity: 1; } }
        @keyframes aiku-footer-dance {
            0%, 8%, 92%, 100% { transform: translateY(0) rotate(0) scale(1); }
            18% { transform: translateY(-5px) rotate(-12deg) scale(1.05); }
            28% { transform: translateY(0) rotate(12deg) scale(.96); }
            42% { transform: translateY(-14px) rotate(180deg) scale(1.14); }
            56% { transform: translateY(0) rotate(360deg) scale(1); }
            68% { transform: translateY(-6px) rotate(348deg) scale(1.08); }
            80% { transform: translateY(0) rotate(372deg) scale(1); }
        }
        @keyframes aiku-footer-dash {
            0% { left: 0; opacity: 0; transform: scaleX(1); }
            8% { opacity: 1; }
            48% { left: calc(100% - 40px); opacity: 1; transform: scaleX(1); }
            55% { left: calc(100% - 40px); transform: scaleX(-1); }
            92% { left: 0; opacity: 1; transform: scaleX(-1); }
            100% { left: 0; opacity: 0; transform: scaleX(-1); }
        }
        @keyframes aiku-footer-hop { 0% { transform: translateY(0) rotate(-2deg); } 100% { transform: translateY(-4px) rotate(2deg); } }
        .footer-romance { position: absolute; z-index: 1; top: -45px; left: 0; right: 0; height: 44px; pointer-events: none; color: var(--ink); animation: aiku-romance-scene 16s linear both; }
        .footer-romance__partner { position: absolute; bottom: 0; width: 40px; height: 44px; }
        .footer-romance__partner .footer-mascot-art, .footer-romance__kid .footer-mascot-art { width: 100%; height: 100%; }
        .footer-romance__partner--left { animation: aiku-romance-left 16s ease-in-out both; }
        .footer-romance__partner--right { animation: aiku-romance-right 16s ease-in-out both; }
        .footer-romance__heart { position: absolute; left: calc(50% - 7px); top: -8px; color: #e879a0; font-family: var(--serif); font-size: 20px; line-height: 1; animation: aiku-romance-heart 16s ease-out both; }
        .footer-romance__kid { --gait-speed: .2s; position: absolute; bottom: 0; left: calc(50% - 24px); width: 19px; height: 22px; opacity: 0; animation: aiku-romance-kid 16s ease-in both; }
        .footer-romance__kid--two { animation-delay: .32s; }
        .footer-romance__kid--three { animation-delay: .64s; }
        @keyframes aiku-romance-scene { 0%, 100% { opacity: 0; } 3%, 96% { opacity: 1; } }
        @keyframes aiku-romance-left {
            0% { left: -42px; opacity: 0; transform: rotate(0); }
            5% { opacity: 1; }
            26% { left: calc(50% - 42px); transform: rotate(0); }
            32%, 38% { left: calc(50% - 39px); transform: translateX(3px) rotate(9deg); }
            43% { left: calc(50% - 42px); transform: translateY(-5px) rotate(-5deg); }
            50%, 72% { left: calc(50% - 42px); transform: translateY(0) rotate(0); }
            78%, 84% { left: calc(50% - 42px); transform: rotate(-10deg); }
            92%, 100% { left: calc(50% - 42px); opacity: 0; transform: rotate(0); }
        }
        @keyframes aiku-romance-right {
            0% { left: calc(100% + 2px); opacity: 0; transform: scaleX(-1); }
            5% { opacity: 1; }
            26% { left: calc(50% + 2px); transform: scaleX(-1); }
            32%, 38% { left: calc(50% - 1px); transform: translateX(-3px) rotate(-9deg) scaleX(-1); }
            44%, 54% { left: calc(50% + 2px); transform: translateY(0) scaleX(-1); }
            58% { left: calc(50% + 2px); transform: translateY(-5px) rotate(-8deg) scaleX(-1); }
            63% { left: calc(50% + 2px); transform: translateY(0) rotate(8deg) scaleX(-1); }
            68% { left: calc(50% + 2px); transform: scaleX(1); }
            90%, 100% { left: calc(100% + 45px); opacity: 1; transform: scaleX(1); }
        }
        @keyframes aiku-romance-heart {
            0%, 27% { opacity: 0; transform: translateY(8px) scale(.3); }
            33% { opacity: 1; transform: translateY(0) scale(1.15); }
            40% { opacity: 0; transform: translateY(-16px) scale(.8); }
            100% { opacity: 0; }
        }
        @keyframes aiku-romance-kid {
            0%, 44% { left: calc(50% - 24px); opacity: 0; transform: translateY(8px) scale(.2); }
            49% { opacity: 1; transform: translateY(-5px) scale(.65); }
            54%, 66% { left: calc(50% - 16px); opacity: 1; transform: translateY(0) scale(.65); }
            92%, 100% { left: calc(100% + 20px); opacity: 1; transform: translateY(0) scale(.65); }
        }
        .footer-duel { position: absolute; z-index: 1; top: -45px; left: 0; right: 0; height: 44px; pointer-events: none; color: var(--ink); animation: aiku-duel-scene 13s linear both; }
        .footer-duel__fighter { position: absolute; bottom: 0; width: 40px; height: 44px; }
        .footer-duel__fighter .footer-mascot-art { width: 100%; height: 100%; }
        .footer-duel__fighter--left { animation: aiku-duel-left 13s ease-in-out both; }
        .footer-duel__fighter--right { animation: aiku-duel-right 13s ease-in-out both; }
        .footer-duel__fighter--left .footer-mascot-art img { filter: hue-rotate(-20deg) saturate(1.15); }
        .footer-duel__fighter--right .footer-mascot-art img { filter: hue-rotate(145deg) saturate(1.45); }
        .footer-duel__bullet { position: absolute; top: 23px; width: 8px; height: 3px; border-radius: 1px; background: #f3c04a; box-shadow: 0 0 7px rgba(243, 192, 74, .9); opacity: 0; }
        .footer-duel__bullet--left { animation: aiku-duel-bullet-left 13s linear both; }
        .footer-duel__bullet--right { animation: aiku-duel-bullet-right 13s linear both; }
        .footer-duel__impact { position: absolute; left: calc(12% + 22px); top: 9px; color: #ef4444; font-size: 20px; line-height: 1; opacity: 0; animation: aiku-duel-impact 13s ease-out both; }
        @keyframes aiku-duel-scene { 0%, 100% { opacity: 0; } 3%, 96% { opacity: 1; } }
        @keyframes aiku-duel-left {
            0% { left: -42px; opacity: 0; transform: scaleX(1); }
            5% { opacity: 1; }
            20%, 48% { left: 12%; transform: translate(0, 0) scaleX(1); }
            52% { left: 12%; transform: translateX(-4px) scaleX(1); }
            57% { left: 12%; transform: translate(0, 0) scaleX(1); }
            62% { left: 12%; transform: translateY(-5px) rotate(-20deg) scaleX(1); }
            70%, 88% { left: 12%; opacity: 1; transform: translate(-7px, 14px) rotate(-90deg) scale(.92); }
            96%, 100% { left: 12%; opacity: 0; transform: translate(-7px, 14px) rotate(-90deg) scale(.92); }
        }
        @keyframes aiku-duel-right {
            0% { left: calc(100% + 2px); opacity: 0; transform: scaleX(-1); }
            5% { opacity: 1; }
            20%, 31% { left: calc(88% - 40px); transform: translateY(0) scaleX(-1); }
            37%, 43% { left: calc(88% - 40px); transform: translateY(-23px) rotate(-7deg) scaleX(-1); }
            48% { left: calc(88% - 40px); transform: translateY(0) scaleX(-1); }
            52% { left: calc(88% - 40px); transform: translateX(4px) scaleX(-1); }
            58%, 73% { left: calc(88% - 40px); transform: translate(0, 0) scaleX(-1); }
            78% { left: calc(88% - 40px); transform: translateY(-7px) rotate(-8deg) scaleX(-1); }
            84%, 96% { left: calc(88% - 40px); opacity: 1; transform: translateY(0) rotate(5deg) scaleX(-1); }
            100% { left: calc(88% - 40px); opacity: 0; transform: translateY(0) scaleX(-1); }
        }
        @keyframes aiku-duel-bullet-left {
            0%, 25% { left: calc(12% + 31px); opacity: 0; }
            27% { opacity: 1; }
            44% { left: calc(88% - 30px); opacity: 1; }
            45%, 100% { left: calc(88% - 30px); opacity: 0; }
        }
        @keyframes aiku-duel-bullet-right {
            0%, 50% { left: calc(88% - 10px); opacity: 0; }
            52% { opacity: 1; }
            62% { left: calc(12% + 30px); opacity: 1; }
            63%, 100% { left: calc(12% + 30px); opacity: 0; }
        }
        @keyframes aiku-duel-impact {
            0%, 59% { opacity: 0; transform: scale(.2) rotate(0); }
            62% { opacity: 1; transform: scale(1.35) rotate(35deg); }
            68%, 100% { opacity: 0; transform: scale(.5) rotate(90deg); }
        }
        @media (prefers-color-scheme: dark) {
            .footer-mascot-art img { filter: invert(.92) hue-rotate(180deg); }
            .footer-duel__fighter--left .footer-mascot-art img { filter: invert(.92) hue-rotate(160deg) saturate(1.15); }
            .footer-duel__fighter--right .footer-mascot-art img { filter: invert(.92) hue-rotate(325deg) saturate(1.45); }
        }
        @media (prefers-reduced-motion: reduce) {
            .footer-mascot, .footer-romance, .footer-duel { display: none !important; }
            .footer-mascot, .footer-mascot *, .footer-romance, .footer-romance *, .footer-duel, .footer-duel * { animation: none !important; }
        }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            header.site { flex-direction: column; align-items: flex-start; gap: 14px; }
            .posts li { grid-template-columns: 1fr; gap: 6px; }
            .hero { padding: 56px 0 32px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="site">
        <a class="brand" href="{{ route('aiku-public.home') }}">{!! str_replace('style="color:#1f1e2a" ', '', file_get_contents(public_path('art/invader-sketch.svg'))) !!}aiku</a>
        <nav>
            <a href="{{ route('aiku-public.blog.index') }}" @if(request()->routeIs('aiku-public.blog.*')) aria-current="page" @endif>Engineering notes</a>
            <a href="{{ route('aiku-public.docs.index') }}" @if(request()->routeIs('aiku-public.docs.*')) aria-current="page" @endif>Documentation</a>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener"><svg viewBox="0 0 16 16" width="15" height="15" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>Source</a>
        </nav>
        <form class="search-header" method="get" action="{{ route('aiku-public.blog.index') }}" role="search">
            <input type="search" name="q" placeholder="Search…" aria-label="Search the notes and documentation" id="header-search-input">
            <ul class="search-results floating" id="header-search-results" hidden></ul>
        </form>
    </header>
</div>

<script>
    fetch('{{ route('aiku-public.visit') }}?p=' + encodeURIComponent(location.pathname + location.search) + '&r=' + encodeURIComponent(document.referrer), {keepalive: true});
    window.wireNotesSearch = function (input, results) {
        if (!input || !results) return;
        var timer = null;
        var logTimer = null;

        function logSearch(query) {
            clearTimeout(logTimer);
            logTimer = setTimeout(function () {
                fetch('{{ route('aiku-public.visit') }}?p=/~search/' + encodeURIComponent(query), {keepalive: true});
            }, 2000);
        }

        function clearResults() {
            results.hidden = true;
            results.innerHTML = '';
        }

        function render(data, query) {
            if (!data.hits.length) {
                results.innerHTML = '<li class="empty">No matches for "' + query + '".</li>';
                results.hidden = false;
                return;
            }
            results.innerHTML = data.hits.map(function (hit) {
                return '<li><a href="' + hit.url + '">' + hit.highlight.title + '</a>'
                    + '<p>' + hit.highlight.summary + '</p></li>';
            }).join('') + '<li class="engine">via ' + (data.engine === 'typesense' ? 'Typesense' : 'search') + '</li>';
            results.hidden = false;
        }

        input.addEventListener('input', function () {
            var query = input.value.trim();
            clearTimeout(timer);
            if (query.length < 2) {
                clearResults();
                return;
            }
            timer = setTimeout(function () {
                fetch('{{ route('aiku-public.search') }}?q=' + encodeURIComponent(query))
                    .then(function (response) { return response.json(); })
                    .then(function (data) { render(data, query); logSearch(query); })
                    .catch(clearResults);
            }, 150);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                input.value = '';
                clearResults();
            }
        });

        document.addEventListener('click', function (event) {
            if (!input.contains(event.target) && !results.contains(event.target)) {
                clearResults();
            }
        });
    };
    window.wireNotesSearch(document.getElementById('header-search-input'), document.getElementById('header-search-results'));
</script>

{{ $slot }}

<div class="wrap">
    <footer class="site">
        <span class="footer-mascot footer-character" data-footer-mascot hidden aria-hidden="true">
            <x-aiku-public.footer-mascot-svg/>
        </span>
        <span class="footer-romance" data-footer-romance hidden aria-hidden="true">
            <span class="footer-romance__partner footer-romance__partner--left footer-character">
                <x-aiku-public.footer-mascot-svg/>
            </span>
            <span class="footer-romance__partner footer-romance__partner--right footer-character">
                <x-aiku-public.footer-mascot-svg/>
            </span>
            <span class="footer-romance__heart">♥</span>
            <span class="footer-romance__kid footer-romance__kid--one footer-character"><x-aiku-public.footer-mascot-svg/></span>
            <span class="footer-romance__kid footer-romance__kid--two footer-character"><x-aiku-public.footer-mascot-svg/></span>
            <span class="footer-romance__kid footer-romance__kid--three footer-character"><x-aiku-public.footer-mascot-svg/></span>
        </span>
        <span class="footer-duel" data-footer-duel hidden aria-hidden="true">
            <span class="footer-duel__fighter footer-duel__fighter--left footer-character"><x-aiku-public.footer-mascot-svg/></span>
            <span class="footer-duel__fighter footer-duel__fighter--right footer-character"><x-aiku-public.footer-mascot-svg/></span>
            <span class="footer-duel__bullet footer-duel__bullet--left"></span>
            <span class="footer-duel__bullet footer-duel__bullet--right"></span>
            <span class="footer-duel__impact">✦</span>
        </span>
        <div>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener"><svg viewBox="0 0 16 16" width="15" height="15" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>GitHub</a>
            <a href="{{ route('aiku-public.blog.index') }}">Engineering notes</a>
            <a href="{{ route('aiku-public.docs.index') }}">Documentation</a>
            <a href="{{ route('aiku-public.feed') }}">RSS</a>
            <a href="{{ route('aiku-public.sitemap') }}">Sitemap</a>
            <a href="{{ route('aiku-public.whatsapp-term-policies') }}" @if(request()->routeIs('aiku-public.whatsapp-term-policies')) aria-current="page" @endif>WhatsApp policy</a>
            <a href="mailto:hello@aiku.io">hello@aiku.io</a>
        </div>
        <div class="footer-license">
            <button class="footer-animation-trigger" type="button" data-footer-animation-trigger aria-label="Play the next mascot animation" title="Play mascot animation">
                <x-aiku-public.footer-mascot-svg/>
            </button>
            <span>aiku</span>
            <span>is open source software (<a href="https://github.com/Inikoo-Ltd/aiku/blob/main/LICENSE" rel="noopener" style="margin:0">AGPL-3.0</a>).</span>
        </div>
    </footer>
</div>

<script>
    (function () {
        var mascot = document.querySelector('[data-footer-mascot]');
        var romance = document.querySelector('[data-footer-romance]');
        var duel = document.querySelector('[data-footer-duel]');
        var trigger = document.querySelector('[data-footer-animation-trigger]');
        if (!mascot || !romance || !duel || !trigger) return;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            trigger.disabled = true;
            return;
        }

        var routines = ['patrol', 'dance', 'dash', 'romance', 'duel'];
        var routineIndex = 0;
        var pendingFrame = null;

        trigger.addEventListener('click', function () {
            mascot.hidden = true;
            romance.hidden = true;
            duel.hidden = true;
            mascot.removeAttribute('data-routine');
            if (pendingFrame) window.cancelAnimationFrame(pendingFrame);

            var routine = routines[routineIndex % routines.length];
            routineIndex += 1;
            pendingFrame = window.requestAnimationFrame(function () {
                if (routine === 'romance') {
                    romance.hidden = false;
                } else if (routine === 'duel') {
                    duel.hidden = false;
                } else {
                    mascot.dataset.routine = routine;
                    mascot.hidden = false;
                }
                pendingFrame = null;
            });
        });

        mascot.addEventListener('animationend', function (event) {
            if (event.target === mascot) mascot.hidden = true;
        });
        romance.addEventListener('animationend', function (event) {
            if (event.target === romance) romance.hidden = true;
        });
        duel.addEventListener('animationend', function (event) {
            if (event.target === duel) duel.hidden = true;
        });
    })();
</script>

@production
    @if ($needsConsent)
        <style>
            .consent {
                position: fixed; left: 20px; bottom: 20px; z-index: 60;
                width: min(330px, calc(100vw - 40px));
                padding: 16px 18px 14px;
                border: 1px solid var(--rule); border-radius: 14px;
                background:
                    radial-gradient(120% 90% at 8% 0%, rgba(217, 148, 74, 0.16), transparent 60%),
                    radial-gradient(110% 100% at 100% 100%, rgba(90, 150, 150, 0.16), transparent 62%),
                    var(--paper);
                box-shadow: 0 10px 34px rgba(28, 27, 34, 0.14);
                font-size: 13.5px; line-height: 1.55; color: var(--muted);
                opacity: 0; transform: translateY(10px);
                transition: opacity .5s ease, transform .5s ease;
            }
            .consent.in { opacity: 1; transform: none; }
            .consent p { margin: 0 0 12px; }
            .consent-actions { display: flex; align-items: center; gap: 14px; }
            .consent button {
                font: inherit; cursor: pointer; border-radius: 8px;
                padding: 6px 14px; border: 0;
                background: var(--accent); color: var(--paper);
            }
            .consent button.plain {
                background: none; color: var(--muted); padding: 6px 0;
                text-decoration: underline; text-underline-offset: 3px;
            }
            @media (prefers-reduced-motion: reduce) {
                .consent { transition: none; }
            }
        </style>

        <div class="consent" id="consent" hidden>
            <p>We use one Microsoft cookie to see whether our ads bring anyone here. Nothing else, and nothing about you.</p>
            <div class="consent-actions">
                <button type="button" data-consent="granted">Allow</button>
                <button type="button" class="plain" data-consent="denied">No thanks</button>
            </div>
        </div>

        <script>
            (function () {
                var el = document.getElementById('consent');
                if (!el || window.aikuConsent) { return; }

                el.hidden = false;
                requestAnimationFrame(function () { el.classList.add('in'); });

                el.addEventListener('click', function (event) {
                    var choice = event.target.getAttribute('data-consent');
                    if (!choice) { return; }

                    try { localStorage.setItem('aiku-consent', choice); } catch (e) {}

                    if (choice === 'granted') {
                        window.uetq = window.uetq || [];
                        window.uetq.push('consent', 'update', { 'ad_storage': 'granted' });
                    }

                    el.classList.remove('in');
                    setTimeout(function () { el.hidden = true; }, 500);
                });
            })();
        </script>
    @endif
@endproduction
</body>
</html>
