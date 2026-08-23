<x-aiku-public.layout title="Engineering notes — aiku" description="Notes from building aiku: how the warehouse, marketing attribution, the MCP server, stock valuation and the deploy pipeline came to be the way they are.">
    <div class="wrap">
        <section class="hero" style="padding-bottom:0">
            <div class="eyebrow">Engineering notes</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">How parts of aiku came to be the way they are.</h1>
            <p class="lede">Short write‑ups of problems we hit running a real commerce operation and what we changed in the code because of them. No product announcements; just the reasoning.</p>
        </section>
        <ul class="posts">
            @foreach ($posts as $post)
                <li>
                    <time datetime="{{ $post['date']->toDateString() }}">{{ $post['date']->format('j M Y') }}</time>
                    <div>
                        <h3><a href="{{ route('aiku-public.blog.show', $post['slug']) }}">{{ $post['title'] }}</a></h3>
                        <p>{{ $post['summary'] }}</p>
                        <div class="tags">@foreach ($post['tags'] as $tag)<span>#{{ $tag }}</span>@endforeach</div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-aiku-public.layout>
