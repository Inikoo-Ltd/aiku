<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>aiku — engineering notes</title>
    <link>{{ route('aiku-public.blog.index') }}</link>
    <description>Notes from building and running aiku, the open source operating system for commerce.</description>
    <language>en</language>
    <lastBuildDate>{{ ($posts->first()['date'] ?? now())->toRssString() }}</lastBuildDate>
    <atom:link href="{{ route('aiku-public.feed') }}" rel="self" type="application/rss+xml"/>
@foreach ($posts as $post)
    <item>
        <title>{{ $post['title'] }}</title>
        <link>{{ route('aiku-public.blog.show', $post['slug']) }}</link>
        <guid isPermaLink="true">{{ route('aiku-public.blog.show', $post['slug']) }}</guid>
        <pubDate>{{ $post['date']->toRssString() }}</pubDate>
        <description>{{ $post['summary'] }}</description>
@foreach (array_filter($post['tags']) as $tag)
        <category>{{ $tag }}</category>
@endforeach
    </item>
@endforeach
</channel>
</rss>
