<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --bg: #ffffff; --fg: #0a0a0a; --card: #ffffff; --muted: #737373; --border: #e5e5e5; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0a0a0a; --fg: #fafafa; --card: #171717; --muted: #a3a3a3; --border: #2e2e2e; }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--fg);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .card {
            width: 100%; max-width: 28rem; background: var(--card);
            border: 1px solid var(--border); border-radius: 0.5rem; padding: 1.5rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; margin: 0 0 .75rem; }
        p { color: var(--muted); font-size: .9rem; line-height: 1.5; margin: 0 0 .75rem; }
    </style>
    <title>{{ __('Connect your AI assistant') }} - {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
</head>
<body>
<div class="card" id="mcpAuthorizeError">
    @if($reason === 'incomplete_link')
        <h1>{{ __('This link is not complete') }}</h1>
        <p>{{ __('This page only works when you open it from your AI assistant, such as ChatGPT or Claude.') }}</p>
    @else
        <h1>{{ __('This connect page has expired') }}</h1>
        <p>{{ __('It was already used, or it was left open too long.') }}</p>
    @endif
    <p>{{ __('Close this page, go back to your AI assistant and click Connect again.') }}</p>
</div>
</body>
</html>
