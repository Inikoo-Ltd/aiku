<x-aiku-public.layout :title="($tag ? '#'.$tag.' — ' : '').'Engineering notes — aiku'" description="Notes from building aiku: how the warehouse, marketing attribution, the MCP server, stock valuation and the deploy pipeline came to be the way they are." :canonical="route('aiku-public.blog.index', array_filter(['tag' => $tag, 'page' => request()->integer('page') > 1 ? request()->integer('page') : null]))">
    <div class="wrap">
        <section class="hero" style="padding-bottom:0">
            <div class="eyebrow">Engineering notes</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">How parts of aiku came to be the way they are.</h1>
            <p class="lede">Short write‑ups of problems we hit running a real commerce operation and what we changed in the code because of them. No product announcements; just the reasoning.</p>
        </section>
        <form class="search" method="get" action="{{ route('aiku-public.blog.index') }}" role="search" id="notes-search-form" autocomplete="off">
            @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
            <input type="search" name="q" value="{{ $query }}" placeholder="Search the notes…" aria-label="Search the notes" id="notes-search-input">
            @if ($query)<a href="{{ route('aiku-public.blog.index', array_filter(['tag' => $tag])) }}">clear</a>@endif
        </form>
        <ul class="search-results" id="notes-search-results" hidden></ul>
        @php
            $shown = $tags->filter(fn ($count) => $count >= 2);
            $top = $shown->sortByDesc(fn ($count, $name) => [$count, $name])->sortBy(fn ($count, $name) => -$count)->take(12);
            if ($tag && !$top->has($tag) && $shown->has($tag)) { $top = $top->put($tag, $shown[$tag]); }
            $rest = $shown->except($top->keys())->sortKeys();
        @endphp
        <nav class="tagbar" aria-label="Filter by tag">
            <a href="{{ route('aiku-public.blog.index') }}" @if(!$tag) aria-current="true" @endif>all <span>{{ \App\Actions\UI\AikuPublic\BlogPosts::all()->count() }}</span></a>
            @foreach ($top->sortKeys() as $name => $count)
                <a href="{{ $tag === $name ? route('aiku-public.blog.index') : route('aiku-public.blog.index', ['tag' => $name]) }}" @if($tag === $name) aria-current="true" title="Clear filter" @endif>#{{ $name }} <span>{{ $count }}</span></a>
            @endforeach
            @if ($rest->isNotEmpty())
                <details class="moretags">
                    <summary>more tags <span>{{ $rest->count() }}</span></summary>
                    <div>
                        @foreach ($rest as $name => $count)
                            <a href="{{ route('aiku-public.blog.index', ['tag' => $name]) }}">#{{ $name }} <span>{{ $count }}</span></a>
                        @endforeach
                    </div>
                </details>
            @endif
        </nav>
        @if ($posts->isEmpty())
            <p style="color:var(--muted);margin-top:32px">Nothing matches "{{ $query }}"@if($tag) in #{{ $tag }}@endif.</p>
        @endif
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
                    <a href="{{ route('aiku-public.blog.index', array_filter(['tag' => $tag, 'q' => $query, 'page' => $page - 1 > 1 ? $page - 1 : null])) }}">← Newer notes</a>
                @else
                    <span></span>
                @endif
                <span class="muted">Page {{ $page }} of {{ $lastPage }}</span>
                @if ($page < $lastPage)
                    <a href="{{ route('aiku-public.blog.index', array_filter(['tag' => $tag, 'q' => $query, 'page' => $page + 1])) }}">Older notes →</a>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    </div>
    <script>
        (function () {
            var input = document.getElementById('notes-search-input');
            var results = document.getElementById('notes-search-results');
            if (!input || !results) return;
            var timer = null;
            var lastQuery = '';

            function clearResults() {
                results.hidden = true;
                results.innerHTML = '';
            }

            function render(data, query) {
                lastQuery = query;
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
                        .then(function (data) { render(data, query); })
                        .catch(clearResults);
                }, 150);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    input.value = '';
                    clearResults();
                }
            });
        })();
    </script>
</x-aiku-public.layout>
