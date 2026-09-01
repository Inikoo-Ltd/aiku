<x-aiku-public.layout :title="$doc['title'].' — aiku'" :description="$doc['summary']" og-type="article">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => $doc['title'],
            'description' => $doc['summary'],
            'dateModified' => $doc['date']->toDateString(),
            'author' => ['@type' => 'Organization', 'name' => 'aiku'],
            'mainEntityOfPage' => url()->current(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>
    <div class="narrow">
        <article class="post">
            <div class="eyebrow">Documentation</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">{{ $doc['title'] }}</h1>
            <div class="meta">
                <time datetime="{{ $doc['date']->toDateString() }}">Last reviewed {{ $doc['date']->format('j F Y') }}</time> · {{ $doc['reading_minutes'] }} min read
                @foreach ($doc['tags'] as $tag) · <a href="{{ route('aiku-public.docs.index', ['tag' => $tag]) }}">#{{ $tag }}</a> @endforeach
            </div>
            @if ($series->count() > 1)
                <nav class="series" aria-label="Articles in this series" style="margin:18px 0 0;padding:14px 20px;border:1px solid var(--rule);border-radius:8px;font-size:15px">
                    <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:6px">{{ $doc['series'] }}</div>
                    <ol style="margin:0;padding-left:1.3em">
                        @foreach ($series as $part)
                            <li style="margin:2px 0">
                                @if ($part['slug'] === $doc['slug'])
                                    <span aria-current="page" style="font-weight:600">{{ $part['title'] }}</span>
                                @else
                                    <a href="{{ route('aiku-public.docs.show', $part['slug']) }}">{{ $part['title'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <div class="body">{!! $doc['html'] !!}</div>
        </article>
        @if ($more->isNotEmpty())
            <section class="chapter">
                <h2 style="font-size:26px">Related documentation</h2>
                <ul class="posts">
                    @foreach ($more as $other)
                        <li>
                            <time datetime="{{ $other['date']->toDateString() }}" title="Last reviewed">{{ $other['date']->format('j M Y') }}</time>
                            <div>
                                <h3 style="font-size:20px"><a href="{{ route('aiku-public.docs.show', $other['slug']) }}">{{ $other['title'] }}</a></h3>
                                <p>{{ $other['summary'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-aiku-public.layout>
