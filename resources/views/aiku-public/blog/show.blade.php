<x-aiku-public.layout :title="$post['title'].' — aiku'" :description="$post['summary']" og-type="article">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'description' => $post['summary'],
            'datePublished' => $post['date']->toDateString(),
            'author' => ['@type' => 'Organization', 'name' => 'aiku'],
            'mainEntityOfPage' => url()->current(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>
    <div class="narrow">
        <article class="post">
            <div class="eyebrow">Engineering notes</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">{{ $post['title'] }}</h1>
            <div class="meta">
                <time datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j F Y') }}</time>
                @foreach ($post['tags'] as $tag) · #{{ $tag }} @endforeach
            </div>
            <div class="body">{!! $post['html'] !!}</div>
        </article>
        @if ($more->isNotEmpty())
            <section class="chapter">
                <h2 style="font-size:26px">More notes</h2>
                <ul class="posts">
                    @foreach ($more as $other)
                        <li>
                            <time datetime="{{ $other['date']->toDateString() }}">{{ $other['date']->format('j M Y') }}</time>
                            <div>
                                <h3 style="font-size:20px"><a href="{{ route('aiku-public.blog.show', $other['slug']) }}">{{ $other['title'] }}</a></h3>
                                <p>{{ $other['summary'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-aiku-public.layout>
