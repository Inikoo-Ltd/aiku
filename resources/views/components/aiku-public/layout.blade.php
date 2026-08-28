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
    <meta property="og:image" content="{{ url('favicon-180.png?v=3') }}">
    <meta name="twitter:card" content="summary_large_image">
    @if (config('app.env') === 'staging')
        <meta name="robots" content="noindex">
    @endif
    <link rel="icon" href="{{ url('favicon.svg?v=3') }}" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon-32.png?v=3') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon-180.png?v=3') }}">
    <link rel="alternate" type="application/xml" title="Sitemap" href="{{ route('aiku-public.sitemap') }}">
    <link rel="alternate" type="application/rss+xml" title="aiku — engineering notes" href="{{ route('aiku-public.feed') }}">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=newsreader:400,400i,600|inter:400,500,600|jetbrains-mono:400&display=swap">
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
        header.site .brand svg { width: 34px; height: 37px; color: var(--ink); }
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
        .tease { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin: 20px 0 8px; }
        .tease img { width: 100%; height: auto; border: 1px solid var(--rule); border-radius: 4px; filter: saturate(0.8); }
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
        article.post .body img { max-width: 100%; height: auto; border: 1px solid var(--rule); border-radius: 8px; }
        footer.site { margin-top: 96px; padding: 40px 0 64px; border-top: 1px solid var(--rule); color: var(--muted); font-size: 14px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        footer.site a { color: var(--muted); margin-right: 20px; }
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
            <a href="{{ route('aiku-public.home') }}" @if(request()->routeIs('aiku-public.home')) aria-current="page" @endif>What it is</a>
            <a href="{{ route('aiku-public.blog.index') }}" @if(request()->routeIs('aiku-public.blog.*')) aria-current="page" @endif>Engineering notes</a>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener"><svg viewBox="0 0 16 16" width="15" height="15" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>Source</a>
        </nav>
        <form class="search-header" method="get" action="{{ route('aiku-public.blog.index') }}" role="search">
            <input type="search" name="q" placeholder="Search notes…" aria-label="Search the engineering notes" id="header-search-input">
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
        <div>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener"><svg viewBox="0 0 16 16" width="15" height="15" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>GitHub</a>
            <a href="{{ route('aiku-public.blog.index') }}">Engineering notes</a>
            <a href="{{ route('aiku-public.feed') }}">RSS</a>
            <a href="{{ route('aiku-public.sitemap') }}">Sitemap</a>
        </div>
        <div>aiku is open source software (<a href="https://github.com/Inikoo-Ltd/aiku/blob/main/LICENSE" rel="noopener" style="margin:0">AGPL-3.0</a>).</div>
    </footer>
</div>
</body>
</html>
