<x-aiku-public.layout :title="($tag ? '#'.$tag.' — ' : '').'Engineering notes — aiku'" description="Notes from building aiku: how the warehouse, marketing attribution, the MCP server, stock valuation and the deploy pipeline came to be the way they are." :canonical="route('aiku-public.blog.index', array_filter(['tag' => $tag, 'page' => request()->integer('page') > 1 ? request()->integer('page') : null]))">
    <div class="wrap">
        <section class="hero" style="padding-bottom:0">
            <div class="eyebrow">Engineering notes</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">How parts of aiku came to be the way they are.</h1>
            <p class="lede">Short write‑ups of problems we hit running a real commerce operation and what we changed in the code because of them. No product announcements; just the reasoning.</p>
        </section>
        <nav class="tagbar" aria-label="Filter by tag">
            <a href="{{ route('aiku-public.blog.index') }}" @if(!$tag) aria-current="true" @endif>all <span>{{ \App\Actions\UI\AikuPublic\BlogPosts::all()->count() }}</span></a>
            @foreach ($tags->filter(fn ($count) => $count >= 2) as $name => $count)
                <a href="{{ route('aiku-public.blog.index', ['tag' => $name]) }}" @if($tag === $name) aria-current="true" @endif>#{{ $name }} <span>{{ $count }}</span></a>
            @endforeach
        </nav>
        <ul class="posts">
            @foreach ($posts as $post)
                <li>
                    <time datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>
                    <div>
                        <h3><a href="{{ route('aiku-public.blog.show', $post['slug']) }}">{{ $post['title'] }}</a></h3>
                        <p>{{ $post['summary'] }}</p>
                        <div class="tags">@foreach ($post['tags'] as $postTag)<a href="{{ route('aiku-public.blog.index', ['tag' => $postTag]) }}">#{{ $postTag }}</a>@endforeach</div>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($lastPage > 1)
            <nav class="pager" aria-label="Pages">
                @if ($page > 1)
                    <a href="{{ route('aiku-public.blog.index', array_filter(['tag' => $tag, 'page' => $page - 1 > 1 ? $page - 1 : null])) }}">← Newer notes</a>
                @else
                    <span></span>
                @endif
                <span class="muted">Page {{ $page }} of {{ $lastPage }}</span>
                @if ($page < $lastPage)
                    <a href="{{ route('aiku-public.blog.index', array_filter(['tag' => $tag, 'page' => $page + 1])) }}">Older notes →</a>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    </div>
</x-aiku-public.layout>
