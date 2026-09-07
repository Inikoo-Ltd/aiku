<x-aiku-public.layout :title="$doc['title'].' — aiku'" :description="$doc['summary']" og-type="article">
    <x-slot:head>
        @foreach ($translations as $version)
            <link rel="alternate" hreflang="{{ $version['lang'] === 'zh-hans' ? 'zh-Hans' : $version['lang'] }}" href="{{ route('aiku-public.docs.show', $version['slug']) }}">
        @endforeach
        @if ($doc['lang'] !== 'en' && $english)
            <link rel="canonical" href="{{ route('aiku-public.docs.show', $english['slug']) }}">
        @endif
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
                {{ $doc['reading_minutes'] }} min read
                @foreach ($doc['tags'] as $tag) · <a href="{{ route('aiku-public.docs.index', ['tag' => $tag]) }}">#{{ $tag }}</a> @endforeach
            </div>
            @if ($translations->isNotEmpty())
                <nav aria-label="Languages" style="margin:14px 0 0;font-size:14px;color:var(--muted)">
                    @foreach ($translations as $version)
                        @if (!$loop->first) · @endif
                        @php $label = $version['lang'] === 'en' ? 'English' : \App\Actions\UI\AikuPublic\BlogPosts::LANGUAGES[$version['lang']]['name']; @endphp
                        @if ($version['slug'] === $doc['slug'])
                            <span aria-current="page" style="font-weight:600;color:var(--ink)">{{ $label }}</span>
                        @else
                            <a href="{{ route('aiku-public.docs.show', $version['slug']) }}" hreflang="{{ $version['lang'] }}">{{ $label }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif
            @if ($doc['lang'] !== 'en' && $english)
                @php $strings = \App\Actions\UI\AikuPublic\BlogPosts::LANGUAGES[$doc['lang']]; @endphp
                <aside style="margin:18px 0 0;padding:12px 18px;border:1px solid var(--rule);border-left-width:3px;border-radius:6px;font-size:14px;color:var(--muted)">
                    {{ $isStale ? $strings['stale'] : $strings['notice'] }}
                    <a href="{{ route('aiku-public.docs.show', $english['slug']) }}" hreflang="en">English</a>
                </aside>
            @endif
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
                <ul class="posts no-date">
                    @foreach ($more as $other)
                        <li>
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
