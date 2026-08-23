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
    <meta property="og:image" content="{{ url('favicon.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    @if (config('app.env') === 'staging')
        <meta name="robots" content="noindex">
    @endif
    <link rel="icon" href="{{ url('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('favicon-180.png') }}">
    <link rel="alternate" type="application/xml" title="Sitemap" href="{{ route('aiku-public.sitemap') }}">
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
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 24px; }
        .narrow { max-width: 720px; margin: 0 auto; padding: 0 24px; }
        header.site { display: flex; align-items: center; justify-content: space-between; padding: 28px 0; border-bottom: 1px solid var(--rule); }
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
        .eyebrow { font-size: 13px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); }
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
        .posts p { margin: 0; color: var(--muted); }
        .tags { margin-top: 10px; font-size: 13px; color: var(--muted); }
        .tags span { margin-right: 12px; }
        article.post { padding: 64px 0; }
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
        <a class="brand" href="{{ route('aiku-public.home') }}">{!! str_replace('style="color:#1f1e2a" ', '', file_get_contents(public_path('art/logo-sketch.svg'))) !!}aiku</a>
        <nav>
            <a href="{{ route('aiku-public.home') }}" @if(request()->routeIs('aiku-public.home')) aria-current="page" @endif>What it is</a>
            <a href="{{ route('aiku-public.blog.index') }}" @if(request()->routeIs('aiku-public.blog.*')) aria-current="page" @endif>Notes</a>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener">Source</a>
        </nav>
    </header>
</div>

{{ $slot }}

<div class="wrap">
    <footer class="site">
        <div>
            <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener">GitHub</a>
            <a href="{{ route('aiku-public.blog.index') }}">Notes</a>
            <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/LICENSE" rel="noopener">AGPL-3.0</a>
            <a href="{{ route('aiku-public.sitemap') }}">Sitemap</a>
        </div>
        <div>aiku is free software. Screens shown are from a demo group with generated data.</div>
    </footer>
</div>
</body>
</html>
