<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    <style>
        :root {
            --bg: #ffffff; --fg: #0a0a0a; --card: #ffffff; --muted: #737373;
            --border: #e5e5e5; --accent: #f5f5f5; --primary: #0a0a0a; --on-primary: #fafafa;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0a0a0a; --fg: #fafafa; --card: #171717; --muted: #a3a3a3;
                --border: #2e2e2e; --accent: #262626; --primary: #fafafa; --on-primary: #0a0a0a;
            }
        }
        html.dark {
            --bg: #0a0a0a; --fg: #fafafa; --card: #171717; --muted: #a3a3a3;
            --border: #2e2e2e; --accent: #262626; --primary: #fafafa; --on-primary: #0a0a0a;
        }
        html.light {
            --bg: #ffffff; --fg: #0a0a0a; --card: #ffffff; --muted: #737373;
            --border: #e5e5e5; --accent: #f5f5f5; --primary: #0a0a0a; --on-primary: #fafafa;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--fg);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .card {
            width: 100%; max-width: 28rem; background: var(--card); color: var(--fg);
            border: 1px solid var(--border); border-radius: 0.5rem; padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        .icon { display: block; margin: 0 auto 1rem; height: 3rem; width: 3rem; fill: none; }
        h1 { font-size: 1.5rem; font-weight: 600; text-align: center; margin: 0 0 .5rem; }
        .muted { color: var(--muted); font-size: .875rem; }
        .center { text-align: center; }
        .panel { border: 1px solid var(--border); border-radius: .5rem; padding: 1rem; margin: 1.25rem 0; }
        ul { list-style: disc; padding-left: 1.25rem; margin: .5rem 0 0; }
        li { font-size: .875rem; color: var(--muted); margin-bottom: .25rem; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .actions form { flex: 1; margin: 0; }
        button {
            width: 100%; height: 2.5rem; border-radius: .375rem; font-size: .875rem;
            font-weight: 500; cursor: pointer; font-family: inherit;
            border: 1px solid var(--border); background: transparent; color: var(--fg);
        }
        button:hover { background: var(--accent); }
        button.primary { background: var(--primary); color: var(--on-primary); border-color: var(--primary); }
        button.primary:hover { opacity: .9; }
        button:disabled { opacity: .5; cursor: default; }
    </style>

    <title>Authorize Application - {{ config('app.name', 'MCP Server') }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Authorize MCP" />
    <link rel="manifest" href="/site.webmanifest" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
</head>
<body>
<div class="card">
    <svg class="icon" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
    </svg>

    <h1>Authorize {{ $client->name }}</h1>

    <p class="muted center">This application will be able to use available MCP functionality.</p>

    <div class="panel">
        <p class="muted" style="margin:0 0 .25rem">Logged in as</p>
        <p style="margin:0;font-weight:500">{{ $user->email }}</p>

        @if(count($scopes) > 0)
            <p class="muted" style="margin:1rem 0 0">Permissions</p>
            <ul>
                @foreach($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Cancel</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary" id="authorizeButton">Authorize</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('authorizeForm');
        const button = document.getElementById('authorizeButton');

        form.addEventListener('submit', function(e) {
            // Show loading state...
            button.disabled = true;
            button.textContent = 'Authorizing...';

            // After form submission, watch for redirect and close window...
            setTimeout(function() {
                const checkRedirect = setInterval(function() {
                    // If URL changed or we have OAuth params, redirect happened...
                    if (!window.location.href.includes('/oauth/authorize') ||
                        window.location.search.includes('code=') ||
                        window.location.search.includes('error=')) {
                        clearInterval(checkRedirect);
                        window.close();
                    }
                }, 100);

                // Fallback: Close after five seconds...
                setTimeout(function() {
                    clearInterval(checkRedirect);
                    window.close();
                }, 5000);
            }, 200);
        });

        // Handle cancel button...
        const cancelForm = document.querySelector('form[method="POST"]:has(input[name="_method"][value="DELETE"])');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                setTimeout(function() {
                    window.close();
                }, 200);
            });
        }
    });
</script>
</body>
</html>
