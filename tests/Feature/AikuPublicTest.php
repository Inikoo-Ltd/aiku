<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\UI\AikuPublic\PingIndexNow;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->host = 'http://'.config('app.domain');
});

test('home renders server side with drawings and latest notes', function () {
    $response = get($this->host.'/');

    $response->assertOk()
        ->assertSee('open source operating system for commerce', false)
        ->assertSee('draw-dashboard.svg', false)
        ->assertSee(BlogPosts::all()->first()['title'], false);
});

test('blog index paginates twenty per page, newest first', function () {
    $all = BlogPosts::all();
    $first = get($this->host.'/blog')->assertOk();
    $all->take(20)->each(fn (array $post) => $first->assertSee(route('aiku-public.blog.show', $post['slug']), false));

    if ($all->count() > 20) {
        $first->assertSee('Older notes', false)->assertDontSee(route('aiku-public.blog.show', $all->last()['slug']), false);
        get($this->host.'/blog?page=2')->assertOk()->assertSee(route('aiku-public.blog.show', $all[20]['slug']), false);
    }

    get($this->host.'/blog?page=999')->assertOk()->assertSee(route('aiku-public.blog.show', $all->last()['slug']), false);
});

test('blog index filters by tag and ignores unknown tags', function () {
    $first = BlogPosts::all()->first();
    $tag = $first['tags'][0];
    $count = BlogPosts::all()->filter(fn (array $post) => in_array($tag, $post['tags'], true))->count();

    get($this->host.'/blog?tag='.$tag)
        ->assertOk()
        ->assertSee(route('aiku-public.blog.show', $first['slug']), false)
        ->assertSee('#'.$tag.' <span>'.$count.'</span>', false);

    $response = get($this->host.'/blog?tag=nope');
    $response->assertOk();
    BlogPosts::all()->take(20)->each(fn (array $post) => $response->assertSee(route('aiku-public.blog.show', $post['slug']), false));
});

test('blog index searches title, summary and body', function () {
    $first = BlogPosts::all()->first();
    $word = collect(preg_split('/\W+/', $first['title']))->filter(fn ($w) => mb_strlen($w) > 5)->first();

    get($this->host.'/blog?q='.urlencode($word))->assertOk()->assertSee(route('aiku-public.blog.show', $first['slug']), false);
    get($this->host.'/blog?q=zzqqxxnothing')->assertOk()->assertSee('Nothing matches', false);
});

test('blog post renders markdown and structured data', function () {
    $post = BlogPosts::all()->first();

    get($this->host.'/blog/'.$post['slug'])
        ->assertOk()
        ->assertSee('<h2>', false)
        ->assertSee('"@type":"BlogPosting"', false)
        ->assertSee($post['title'], false);
});

test('unknown post is 404 and slug is validated', function () {
    get($this->host.'/blog/does-not-exist')->assertNotFound();
    expect(BlogPosts::find('../../.env'))->toBeNull();
});

test('sitemap and robots are served', function () {
    get($this->host.'/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('aiku-public.blog.index').'</loc>', false);

    get($this->host.'/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('aiku-public.sitemap'), false);
});

test('search.json returns hits for a word from the first post title', function () {
    $first = BlogPosts::all()->first();
    $word = collect(preg_split('/\W+/', $first['title']))->filter(fn ($w) => mb_strlen($w) > 5)->first();

    $response = get($this->host.'/blog/search.json?q='.urlencode($word))->assertOk();
    $response->assertJsonStructure(['hits', 'found', 'engine']);
    expect($response->json('hits'))->not->toBeEmpty();
    expect(collect($response->json('hits'))->pluck('slug'))->toContain($first['slug']);
    expect(collect($response->json('hits'))->firstWhere('slug', $first['slug'])['url'])
        ->toBe(route('aiku-public.blog.show', $first['slug']));
});

test('search.json returns empty for a query shorter than 2 characters', function () {
    get($this->host.'/blog/search.json?q=a')
        ->assertOk()
        ->assertJson(['hits' => [], 'found' => 0]);
});

test('feed.xml lists every note with link, date and categories', function () {
    $all = BlogPosts::all();
    $response = get($this->host.'/feed.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<rss version="2.0"', false);

    $all->each(fn (array $post) => $response->assertSee('<link>'.route('aiku-public.blog.show', $post['slug']).'</link>', false));
    $first = $all->first();
    $response->assertSee('<pubDate>'.$first['date']->toRssString().'</pubDate>', false)
        ->assertSee('<category>'.$first['tags'][0].'</category>', false);
    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();

    get($this->host.'/')->assertSee('type="application/rss+xml"', false);
});

test('llms.txt and home JSON-LD are served', function () {
    get($this->host.'/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee(route('aiku-public.blog.index'), false);

    get($this->host.'/')
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false);
});

test('indexnow key file is served and the ping submits every public url', function () {
    expect(route('aiku-public.indexnow-key'))->toEndWith('.txt');
    get(route('aiku-public.indexnow-key'))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    config(['services.indexnow.key' => 'abc123']);

    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
    expect((new PingIndexNow())->handle())->toBe(BlogPosts::all()->count() + 2);
    Http::assertSent(fn ($request) => $request['key'] === 'abc123'
        && $request['host'] === config('app.domain')
        && in_array(route('aiku-public.blog.show', BlogPosts::all()->first()['slug']), $request['urlList'], true));

    config(['services.indexnow.key' => null]);
    expect((new PingIndexNow())->handle())->toBe(0);
});
