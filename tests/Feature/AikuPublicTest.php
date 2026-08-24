<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\UI\AikuPublic\IndexNotesInTypesense;
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

test('renamed slugs redirect permanently', function () {
    get($this->host.'/blog/an-mcp-server-for-a-whole-business')->assertRedirect(route('aiku-public.blog.show', 'rag-is-dead-give-the-model-the-tools'))->assertStatus(301);
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
    try {
        (new IndexNotesInTypesense())->handle();
    } catch (Throwable) {
    }

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

test('visit beacon logs humans with referrer and country, skips bots', function () {
    $visits = fn () => \Illuminate\Support\Facades\DB::table('aiku_public_visits');
    $before = $visits()->count();

    get($this->host.'/visit.json?p=/blog&r=https://news.ycombinator.com/item', ['CF-IPCountry' => 'ES'])->assertNoContent();
    expect($visits()->count())->toBe($before + 1);
    $visit = $visits()->latest('id')->first();
    expect($visit->path)->toBe('/blog')
        ->and($visit->referrer)->toBe('news.ycombinator.com')
        ->and($visit->country)->toBe('ES')
        ->and(mb_strlen($visit->visitor_hash))->toBe(16);

    get($this->host.'/visit.json?p=/blog', ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();
    get($this->host.'/visit.json?p=https://evil.example/x')->assertNoContent();
    get($this->host.'/visit.json')->assertNoContent();
    expect($visits()->count())->toBe($before + 1);

    get($this->host.'/blog')->assertSee(route('aiku-public.visit'), false);
});

test('visit stats aggregate for devops dashboard widget and analytics page', function () {
    get($this->host.'/visit.json?p=/~search/warehouse%20layout')->assertNoContent();
    get($this->host.'/visit.json?p=/blog/anatomy-of-a-deploy&r=https://lobste.rs/s/abc', ['CF-IPCountry' => 'SK'])->assertNoContent();

    $stats = \App\Actions\DevOps\UI\ShowAikuPublicAnalytics::make()->handle();
    expect(collect($stats['searches'])->pluck('query'))->toContain('warehouse layout')
        ->and(collect($stats['pages'])->pluck('path'))->toContain('/blog/anatomy-of-a-deploy')
        ->and(collect($stats['pages'])->pluck('path'))->not->toContain('/~search/warehouse%20layout')
        ->and(collect($stats['referrers'])->pluck('referrer'))->toContain('lobste.rs')
        ->and(collect($stats['page_referrers'])->first(fn ($row) => $row->path === '/blog/anatomy-of-a-deploy')->referrer)->toBe('lobste.rs')
        ->and(collect($stats['countries'])->pluck('country'))->toContain('SK')
        ->and(collect($stats['pages'])->first(fn ($row) => $row->path === '/blog/anatomy-of-a-deploy')->last_visited_at)->not->toBeNull();

    $widget = \App\Actions\DevOps\UI\ShowDevopsDashboard::make()->getPublicSiteVisits();
    expect($widget['views'])->toBeGreaterThanOrEqual(2)
        ->and($widget['visitors'])->toBeGreaterThanOrEqual(1)
        ->and($widget['daily'])->not->toBeEmpty();
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

test('future-dated posts stay hidden until their date', function () {
    $slug = 'test-scheduled-post-'.uniqid();
    $path = resource_path("markdown/aiku-public/blog/{$slug}.md");
    file_put_contents($path, "---\ntitle: Scheduled\nsummary: Not yet\ndate: ".now()->addDays(3)->toDateString()."\ntags: test\n---\n\nSoon.\n");

    try {
        expect(BlogPosts::all()->pluck('slug'))->not->toContain($slug)
            ->and(BlogPosts::find($slug))->toBeNull();
        get($this->host.'/blog/'.$slug)->assertNotFound();
    } finally {
        unlink($path);
    }
});

test('analytics articles tab lists every note with real commit date and visit stats', function () {
    get($this->host.'/visit.json?p=/blog/anatomy-of-a-deploy')->assertNoContent();

    $articles = collect(\App\Actions\DevOps\UI\ShowAikuPublicAnalytics::make()->getArticleStats());
    expect($articles)->toHaveCount(BlogPosts::all()->count());

    $row = $articles->firstWhere('slug', 'anatomy-of-a-deploy');
    expect($row['views'])->toBeGreaterThanOrEqual(1)
        ->and($row['url'])->toBe('https://aiku.io/blog/anatomy-of-a-deploy')
        ->and($row['date'])->toBe('2026-08-19')
        ->and($row['committed_at'])->toStartWith('2026-08-');
});
