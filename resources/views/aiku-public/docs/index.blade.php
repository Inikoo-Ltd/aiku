<x-aiku-public.layout :title="($tag ? '#'.$tag.' — ' : '').'Documentation — aiku'" description="How to use aiku, feature by feature: what each part is for, why it works the way it does, and how to run it day to day." :canonical="route('aiku-public.docs.index', array_filter(['tag' => $tag]))">
    <div class="wrap">
        <section class="hero" style="padding-bottom:0">
            <div class="eyebrow">Documentation</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">How to use aiku, and why it works that way.</h1>
            <p class="lede">Guides for the people who run the system every day: what each feature is for, the reasoning behind it, and the steps to use it well.</p>
        </section>
        @include('aiku-public.docs.map')
        @if ($tags->isNotEmpty())
            <nav class="tagbar" aria-label="Filter by tag">
                <a href="{{ route('aiku-public.docs.index') }}" @if(!$tag) aria-current="true" @endif>all <span>{{ \App\Actions\UI\AikuPublic\BlogPosts::all('docs')->count() }}</span></a>
                @foreach ($tags as $name => $count)
                    <a href="{{ $tag === $name ? route('aiku-public.docs.index') : route('aiku-public.docs.index', ['tag' => $name]) }}" @if($tag === $name) aria-current="true" title="Clear filter" @endif>#{{ $name }} <span>{{ $count }}</span></a>
                @endforeach
            </nav>
        @endif
        <ul class="posts">
            @foreach ($docs as $doc)
                <li>
                    <time datetime="{{ $doc['date']->toDateString() }}" title="Last reviewed">{{ $doc['date']->format('j M Y') }}</time>
                    <div>
                        <h3><a href="{{ route('aiku-public.docs.show', $doc['slug']) }}">{{ $doc['title'] }}</a></h3>
                        <p>{{ $doc['summary'] }} <span style="color:var(--muted);white-space:nowrap">· {{ $doc['reading_minutes'] }} min read</span></p>
                        <div class="tags">@foreach ($doc['tags'] as $docTag)<a href="{{ route('aiku-public.docs.index', ['tag' => $docTag]) }}">#{{ $docTag }}</a>@endforeach</div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-aiku-public.layout>
