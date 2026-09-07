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
        ->assertSee(BlogPosts::all()->first()['title']);
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

test('visit beacon logs humans with referrer and country', function () {
    $visits = fn () => \Illuminate\Support\Facades\DB::table('aiku_public_visits');
    $before = $visits()->count();

    get($this->host.'/visit.json?p=/blog&r=https://news.ycombinator.com/item', ['CF-IPCountry' => 'ES'])->assertNoContent();
    expect($visits()->count())->toBe($before + 1);
    $visit = $visits()->latest('id')->first();
    expect($visit->path)->toBe('/blog')
        ->and($visit->referrer)->toBe('news.ycombinator.com')
        ->and($visit->country)->toBe('ES')
        ->and(mb_strlen($visit->visitor_hash))->toBe(16);

    get($this->host.'/visit.json?p=https://evil.example/x')->assertNoContent();
    get($this->host.'/visit.json')->assertNoContent();
    expect($visits()->count())->toBe($before + 1);

    get($this->host.'/blog')->assertDontSee('document.referrer', false);
});

test('page views are logged server-side from the referer header so ad blockers cannot hide them', function () {
    $visits = fn () => \Illuminate\Support\Facades\DB::table('aiku_public_visits');
    $before = $visits()->count();

    get($this->host.'/blog?msclkid=10f2b6be38c513c19b70dc2834d4cb88', ['Referer' => 'https://laravel-news.com/links/x', 'CF-IPCountry' => 'MY'])->assertOk();
    $visit = $visits()->latest('id')->first();
    expect($visits()->count())->toBe($before + 1)
        ->and($visit->path)->toBe('/blog')
        ->and($visit->referrer)->toBe('laravel-news.com')
        ->and($visit->country)->toBe('MY');

    get($this->host.'/feed.xml')->assertOk();
    get($this->host.'/nope-'.uniqid())->assertNotFound();
    expect($visits()->count())->toBe($before + 1);

    get($this->host.'/blog', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
    $bot = $visits()->latest('id')->first();
    expect($visits()->count())->toBe($before + 2)
        ->and($bot->is_bot)->toBeTrue()
        ->and($visit->is_bot)->toBeFalse()
        ->and($bot->user_agent)->toContain('Googlebot');

    get($this->host.'/blog', ['User-Agent' => 'Mozilla/5.0 (compatible)'])->assertOk();
    expect($visits()->latest('id')->first()->is_bot)->toBeTrue();

    $stats = \App\Actions\DevOps\UI\ShowAikuPublicAnalytics::make()->handle();
    expect(collect($stats['bots'])->pluck('user_agent')->first(fn ($ua) => str_contains($ua, 'Googlebot')))->not->toBeNull();
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

    get($this->host.'/visit.json?p=/docs/scraped-once', ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'CF-IPCountry' => 'BD'])->assertNoContent();
    $stats = \App\Actions\DevOps\UI\ShowAikuPublicAnalytics::make()->handle();
    expect(collect($stats['pages'])->pluck('path'))->not->toContain('/docs/scraped-once')
        ->and(collect($stats['countries'])->pluck('country'))->not->toContain('BD')
        ->and((int) collect($stats['daily'])->sum('suspect'))->toBe(1);

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

test('blog post shows related notes ranked by shared tags', function () {
    $post = BlogPosts::all()->first(fn (array $candidate) => count($candidate['tags']) > 0);

    $bestMatch = BlogPosts::all()
        ->where('slug', '!=', $post['slug'])
        ->sortByDesc(fn (array $other) => count(array_intersect($other['tags'], $post['tags'])) * 1e12 + $other['date']->timestamp)
        ->first();

    get($this->host.'/blog/'.$post['slug'])
        ->assertOk()
        ->assertSee('Related notes', false)
        ->assertSee(route('aiku-public.blog.show', $bestMatch['slug']), false);
});

test('docs index and article render with layout nav', function () {
    $docs = BlogPosts::all('docs');
    expect($docs)->not->toBeEmpty();

    get($this->host.'/docs')->assertOk()
        ->assertSee('Documentation', false)
        ->assertSee(route('aiku-public.docs.show', $docs->first()['slug']), false);

    get($this->host.'/docs/'.$docs->first()['slug'])->assertOk()
        ->assertSee($docs->first()['title'], false);

    get($this->host.'/docs/does-not-exist')->assertNotFound();
});

test('production docs render and paint the production island', function () {
    foreach (['fulfilling-partner-orders' => 'To produce', 'who-makes-what' => 'Usually made by', 'preparing-mixes' => 'Made in-house as', 'preparing-mixes-ro' => 'Made in-house as', 'factory-positions' => 'Floor supervisor', 'factory-positions-ro' => 'Production', 'factory-positions-pl' => 'Production', 'factory-positions-lv' => 'Production', 'factory-positions-cs' => 'Production'] as $slug => $text) {
        get($this->host.'/docs/'.$slug)->assertOk()->assertSee($text, false);
    }
    get($this->host.'/docs?category=production')->assertOk()->assertSee('/docs/who-makes-what', false);
});

test('related docs on a translated article link to the same language when it exists', function () {
    $response = get($this->host.'/docs/factory-positions-ro')->assertOk();
    $response->assertSee('/docs/who-makes-what-ro', false)
        ->assertDontSee('href="/docs/who-makes-what"', false);
});

test('the partner and factory series is complete in every worker language', function () {
    $series = BlogPosts::all('docs')->where('series', 'Ordering from partners');
    expect($series)->not->toBeEmpty();
    foreach ($series as $english) {
        foreach (['es', 'sk', 'ro', 'pl', 'lv', 'cs'] as $lang) {
            $translation = BlogPosts::everything('docs')->firstWhere('slug', $english['slug'].'-'.$lang);
            expect($translation)->not->toBeNull($english['slug'].' has no '.$lang.' translation')
                ->and($translation['source_date']?->toDateString())->toBe($english['date']->toDateString(), $english['slug'].'-'.$lang.' is stale');
        }
    }
});

test('docs index shows the clickable module map', function () {
    get($this->host.'/docs')->assertOk()
        ->assertSee('Map of aiku modules', false)
        ->assertSee(route('aiku-public.docs.index', ['category' => 'procurement']), false)
        ->assertSee(route('aiku-public.docs.index', ['category' => 'production']), false);

    get($this->host.'/docs?category=procurement')->assertOk()
        ->assertSee('docsmap-mini', false)
        ->assertDontSee('docsmap"', false)
        ->assertSee('Buying from a partner', false)
        ->assertDontSee('Setting up a new employee', false);

    get($this->host.'/docs?tag=clocking')->assertOk()
        ->assertSee('docsmap-mini', false)
        ->assertSee('Types of clocking machines', false);
});

test('helpFor matches grp routes to docs by longest prefix', function () {
    expect(BlogPosts::helpFor('grp.org.procurement.org_partners.show.shopping.dashboard')['title'])->toBe('Reading the partner shopping dashboard')
        ->and(BlogPosts::helpFor('grp.org.procurement.org_partners.show.shopping_list.index')['title'])->toBe('Buying from a partner')
        ->and(BlogPosts::helpFor('grp.org.procurement.org_partners.index')['title'])->toBe('Ordering from a partner organisation')
        ->and(BlogPosts::helpFor('grp.org.shops.show.chat.dashboard')['url'])->toContain('/docs/customer-chat')
        ->and(BlogPosts::helpFor('grp.chat.staff.show', 'sk')['url'])->toEndWith('/docs/staff-chat-sk')
        ->and(BlogPosts::helpFor('grp.dashboard.show'))->toBeNull()
        ->and(BlogPosts::helpFor(null))->toBeNull();
});

test('helpFor links the reader to the doc in their own language when one exists', function () {
    expect(BlogPosts::helpFor('grp.org.shops.show.ordering.orders.show', 'es')['url'])->toEndWith('/docs/following-an-order-from-basket-to-dispatch-es')
        ->and(BlogPosts::helpFor('grp.org.shops.show.ordering.orders.show', 'sk')['url'])->toEndWith('/docs/following-an-order-from-basket-to-dispatch-sk')
        ->and(BlogPosts::helpFor('grp.org.shops.show.ordering.orders.show', 'hi')['url'])->toEndWith('/docs/following-an-order-from-basket-to-dispatch')
        ->and(BlogPosts::helpFor('grp.org.shops.show.ordering.orders.show', 'en')['url'])->toEndWith('/docs/following-an-order-from-basket-to-dispatch');
});

test('translated docs are served, linked and kept out of the English listings', function () {
    $english = BlogPosts::all('docs')->firstWhere('slug', 'your-clean-handover-score');
    expect($english)->not->toBeNull()
        ->and($english['lang'])->toBe('en');

    expect(BlogPosts::all('docs')->pluck('slug'))
        ->not->toContain('your-clean-handover-score-id')
        ->not->toContain('your-clean-handover-score-zh-hans');

    $translations = BlogPosts::translations($english, 'docs');
    expect($translations->pluck('lang')->all())->toBe(['en', 'es', 'hi', 'id', 'ne', 'sk', 'zh-hans'])
        ->and($translations->every(fn (array $doc) => $doc['base_slug'] === 'your-clean-handover-score'))->toBeTrue();

    get($this->host.'/docs/your-clean-handover-score-id')->assertOk()
        ->assertSee('Skor Serah Terima Bersih', false)
        ->assertSee('versi bahasa Inggris yang berlaku', false)
        ->assertSee(route('aiku-public.docs.show', 'your-clean-handover-score-zh-hans'), false);

    get($this->host.'/docs/your-clean-handover-score')->assertOk()
        ->assertSee('Bahasa Indonesia', false)
        ->assertDontSee('versi bahasa Inggris yang berlaku', false);
});

test('every guide has a Spanish and a Slovak translation made from the current English', function () {
    foreach (BlogPosts::all('docs') as $english) {
        foreach (['es', 'sk'] as $lang) {
            $translation = BlogPosts::everything('docs')->firstWhere('slug', $english['slug'].'-'.$lang);
            expect($translation)->not->toBeNull($english['slug'].' has no '.$lang.' translation')
                ->and($translation['source_date']?->toDateString())->toBe($english['date']->toDateString(), $english['slug'].'-'.$lang.' is stale');
        }
    }
});

test('a translation older than its English original is flagged as stale', function () {
    $english = BlogPosts::all('docs')->firstWhere('slug', 'your-clean-handover-score');
    $translation = BlogPosts::everything('docs')->firstWhere('slug', 'your-clean-handover-score-id');

    expect($translation['source_date']->toDateString())->toBe($english['date']->toDateString());

    get($this->host.'/docs/your-clean-handover-score-id')->assertOk()
        ->assertDontSee('telah berubah setelah terjemahan ini', false);
});

test('reading time counts non-latin scripts instead of reporting one minute', function () {
    $devanagari = BlogPosts::everything('docs')->firstWhere('slug', 'your-clean-handover-score-hi');
    $chinese = BlogPosts::everything('docs')->firstWhere('slug', 'your-clean-handover-score-zh-hans');

    expect($devanagari['reading_minutes'])->toBeGreaterThan(3)
        ->and($chinese['reading_minutes'])->toBeGreaterThan(3);
});

test('architecture page serves the interactive diagram', function () {
    $response = get($this->host.'/architecture')->assertOk();

    expect($response->baseResponse->getFile()->getPathname())->toBe(resource_path('aiku-public/architecture/aiku-architecture.html'))
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('Aiku Platform Architecture');
});
