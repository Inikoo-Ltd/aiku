<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Crawl\AuditWebsite;
use App\Actions\Web\Crawl\UI\CompareCrawls;
use App\Actions\Web\Crawl\UI\ExportCrawlReport;
use App\Actions\Web\Crawl\UI\ShowCrawlStructure;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Crawl;
use App\Services\SiteAudit\AuditedHtml;
use App\Services\SiteAudit\CustomExtraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    [, $this->user, $this->shop] = createShop($this->organisation);
    $this->website      = createWebsite($this->shop);
});

function makeAuditCrawl($website, bool $isFullAudit, array $attributes = []): Crawl
{
    return $website->crawls()->create([
        'type'          => CrawlTypeEnum::AUDIT,
        'state'         => CrawlStateEnum::FINISH,
        'trigger'       => CrawlTriggerEnum::USER,
        'depth'         => 0,
        'concurrency'   => 1,
        'running'       => false,
        'is_full_audit' => $isFullAudit,
        ...$attributes,
    ]);
}

test('a crawl limited by depth, pages or an untouched dialog is told apart from a full audit', function () {
    expect(AuditWebsite::isFullAudit(null, ['max_depth' => 0]))->toBeFalse()
        ->and(AuditWebsite::isFullAudit(null, null))->toBeTrue()
        ->and(AuditWebsite::isFullAudit(100, null))->toBeFalse()
        ->and(AuditWebsite::isFullAudit(null, [
            'max_depth'            => null,
            'include'              => [],
            'exclude'              => [],
            'respect_robots_txt'   => true,
            'crawl_subdomains'     => false,
            'check_external_links' => true,
            'check_images'         => true,
            'mode'                 => 'spider',
            'urls'                 => [],
            'custom_search'        => [['name' => 'x', 'mode' => 'contains', 'type' => 'text', 'value' => 'y']],
            'extractions'          => [],
        ]))->toBeTrue()
        ->and(AuditWebsite::isFullAudit(null, ['respect_robots_txt' => false]))->toBeFalse();
});

test('pruning keeps the links of the latest full audit when limited crawls follow it', function () {
    $full = makeAuditCrawl($this->website, true);
    DB::table('crawl_links')->insert([
        'crawl_id' => $full->id, 'from_url_hash' => md5('a'), 'to_url' => 'https://x.test/b', 'to_url_hash' => md5('b'), 'kind' => 'anchor',
    ]);

    foreach (range(1, 3) as $ignored) {
        makeAuditCrawl($this->website, false);
    }

    $prune = new ReflectionMethod(AuditWebsite::class, 'pruneOldAudits');
    $prune->invoke(AuditWebsite::make(), $this->website);

    expect(DB::table('crawl_links')->where('crawl_id', $full->id)->count())->toBe(1);
});

test('a regex search on a page it cannot read reports an error, not a missing text', function () {
    $result = CustomExtraction::run("caf\xE9 \xFF", [
        ['name' => 'a', 'mode' => 'not_contains', 'type' => 'regex', 'value' => 'caf'],
        ['name' => 'b', 'mode' => 'contains', 'type' => 'regex', 'value' => 'zzz'],
    ], [
        ['name' => 'e', 'type' => 'regex', 'selector' => 'caf', 'output' => 'text', 'attribute' => null],
    ]);

    expect($result['search'][0])->toBeNull()
        ->and($result['extract'][0])->toBeNull();

    $clean = CustomExtraction::run('<p>cafe</p>', [['name' => 'b', 'mode' => 'contains', 'type' => 'regex', 'value' => 'zzz']], []);

    expect($clean['search'][0])->toBe(0);
});

test('the comparison defaults to the previous crawl of the same kind', function () {
    $fullBefore = makeAuditCrawl($this->website, true, ['health_score' => 80]);
    makeAuditCrawl($this->website, false, ['health_score' => 50]);
    $fullNow = makeAuditCrawl($this->website, true, ['health_score' => 90]);

    $previous = new ReflectionMethod(CompareCrawls::class, 'defaultPrevious');

    expect($previous->invoke(CompareCrawls::make(), $fullNow)->id)->toBe($fullBefore->id);
});

function insertCrawlPage(Crawl $crawl, string $url, array $attributes = []): int
{
    return DB::table('crawl_pages')->insertGetId([
        'crawl_id'     => $crawl->id,
        'url'          => $url,
        'url_hash'     => md5($url),
        'status_code'  => 200,
        'content_type' => 'text/html; charset=utf-8',
        'link_depth'   => 0,
        'is_indexable' => true,
        ...$attributes,
    ]);
}

test('the structure of a crawl is counted in the database by clicks deep and by folder', function () {
    $crawl = makeAuditCrawl($this->website, true);
    $base  = 'https://shop.test';

    insertCrawlPage($crawl, $base.'/');
    insertCrawlPage($crawl, $base.'/shoes', ['link_depth' => 1]);
    $broken = insertCrawlPage($crawl, $base.'/shoes/red', ['link_depth' => 2, 'is_indexable' => false]);
    insertCrawlPage($crawl, $base.'/shoes/blue/large/extra', ['link_depth' => 25]);
    insertCrawlPage($crawl, $base.'/bags', ['link_depth' => 1]);
    insertCrawlPage($crawl, $base.'/orphan', ['link_depth' => null]);
    insertCrawlPage($crawl, $base.'/logo.png', ['content_type' => 'image/png']);
    insertCrawlPage($crawl, $base.'/gone', ['status_code' => 404]);

    DB::table('crawl_issues')->insert(['crawl_id' => $crawl->id, 'crawl_page_id' => $broken, 'type' => 'x', 'severity' => 'error']);

    $structure = ShowCrawlStructure::make()->handle($crawl);

    expect($structure['pages'])->toBe(6)
        ->and(collect($structure['depths'])->pluck('pages', 'depth')->all())->toBe([
            0 => 1, 1 => 2, 2 => 1, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 1, '' => 1,
        ]);

    $shoes = $structure['tree'][0];

    expect($shoes['name'])->toBe('shoes')
        ->and($shoes['path'])->toBe('/shoes')
        ->and([$shoes['pages'], $shoes['indexable'], $shoes['errors']])->toBe([3, 2, 1])
        ->and(collect($structure['tree'])->pluck('name')->all())->toBe(['shoes', 'bags', 'orphan'])
        ->and(collect($shoes['children'])->pluck('pages', 'name')->all())->toBe(['blue' => 1, 'red' => 1])
        ->and($shoes['children'][1]['errors'])->toBe(1)
        ->and($shoes['children'][0]['children'][0]['name'])->toBe('large')
        ->and($shoes['children'][0]['children'][0]['children'])->toBe([]);
});

test('folders beyond the fifty shown are folded into one row', function () {
    $crawl = makeAuditCrawl($this->website, true);

    foreach (range(1, 53) as $number) {
        insertCrawlPage($crawl, 'https://shop.test/folder-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
    }

    $tree = ShowCrawlStructure::make()->handle($crawl)['tree'];

    expect($tree)->toHaveCount(51)
        ->and($tree[50]['path'])->toBeNull()
        ->and($tree[50]['pages'])->toBe(3)
        ->and($tree[0]['name'])->toBe('folder-01');
});

test('the redirect and canonical chain reports follow chains without loading every page', function () {
    $crawl = makeAuditCrawl($this->website, true);
    $base  = 'https://shop.test';

    insertCrawlPage($crawl, $base.'/a', ['status_code' => 301, 'redirect_to' => $base.'/b', 'inlinks' => 4]);
    insertCrawlPage($crawl, $base.'/b', ['status_code' => 302, 'redirect_to' => $base.'/c']);
    insertCrawlPage($crawl, $base.'/c');
    insertCrawlPage($crawl, $base.'/loop1', ['status_code' => 301, 'redirect_to' => $base.'/loop2']);
    insertCrawlPage($crawl, $base.'/loop2', ['status_code' => 301, 'redirect_to' => $base.'/loop1']);
    insertCrawlPage($crawl, $base.'/p1', ['canonical' => $base.'/p2']);
    insertCrawlPage($crawl, $base.'/p2', ['canonical' => $base.'/p3']);
    insertCrawlPage($crawl, $base.'/p3', ['canonical' => $base.'/p4']);
    insertCrawlPage($crawl, $base.'/p4', ['canonical' => $base.'/p4']);
    insertCrawlPage($crawl, $base.'/q1', ['canonical' => $base.'/p4']);

    $export  = ExportCrawlReport::make();
    $method  = fn (string $name) => (new ReflectionMethod(ExportCrawlReport::class, $name))->invoke($export, $crawl);
    $redirects = collect($method('redirectChains'))->keyBy(0);

    expect($redirects)->toHaveCount(4)
        ->and($redirects[$base.'/a'])->toBe([$base.'/a', 2, $base.'/a > '.$base.'/b > '.$base.'/c', $base.'/c', 200, 'No', 4])
        ->and($redirects[$base.'/b'][1])->toBe(1)
        ->and($redirects[$base.'/loop1'][5])->toBe('Yes');

    $canonicals = collect($method('canonicalChains'))->keyBy(0);

    expect($canonicals->keys()->sort()->values()->all())->toBe([$base.'/p1', $base.'/p2'])
        ->and($canonicals[$base.'/p1'][1])->toBe(3)
        ->and($canonicals[$base.'/p1'][3])->toBe($base.'/p4');
});

function crawlerForLinks(Crawl $crawl): AuditWebsite
{
    $action = AuditWebsite::make();

    foreach (['crawl' => $crawl, 'host' => 'shop.test', 'config' => ['crawl_subdomains' => false]] as $property => $value) {
        (new ReflectionProperty(AuditWebsite::class, $property))->setValue($action, $value);
    }

    return $action;
}

test('a link repeated on a page is stored once and a page keeps at most five hundred links', function () {
    $crawl  = makeAuditCrawl($this->website, true);
    $action = crawlerForLinks($crawl);
    $collect = new ReflectionMethod(AuditWebsite::class, 'collectLinks');
    $pending = new ReflectionProperty(AuditWebsite::class, 'pendingLinks');

    $anchors = str_repeat('<a href="/same">first</a><a href="/same">again</a>', 5);
    $collect->invoke($action, 'https://shop.test/page', AuditedHtml::parse('<html><body>'.$anchors.'</body></html>'), null);

    expect($pending->getValue($action))->toHaveCount(1)
        ->and($pending->getValue($action)[0]['anchor'])->toBe('first');

    $pending->setValue($action, []);
    $many = implode('', array_map(fn (int $number) => '<a href="/item-'.$number.'">item</a>', range(1, 600)));
    $collect->invoke($action, 'https://shop.test/big', AuditedHtml::parse('<html><body>'.$many.'</body></html>'), 'https://shop.test/big');

    $links = collect($pending->getValue($action));

    expect($links)->toHaveCount(500)
        ->and($links->where('kind', 'canonical'))->toHaveCount(1);
});

test('an external link whose address cannot be a url is recorded as an error instead of ending the audit', function () {
    Http::fake(['https://partner.test/*' => Http::response('', 200)]);

    $crawl  = makeAuditCrawl($this->website, true);
    $action = crawlerForLinks($crawl);
    $check  = new ReflectionMethod(AuditWebsite::class, 'checkResources');

    $checked = $check->invoke($action, [
        ['url' => 'https://partner.test/ok', 'kind' => 'link'],
        ['url' => 'https://almofada interna 60x60 cm 700gm', 'kind' => 'link'],
    ]);

    $rows = DB::table('crawl_resources')->where('crawl_id', $crawl->id)->get()->keyBy('url');

    expect($checked)->toBe(2)
        ->and($rows['https://partner.test/ok']->status_code)->toBe(200)
        ->and($rows['https://almofada interna 60x60 cm 700gm']->status_code)->toBeNull()
        ->and($rows['https://almofada interna 60x60 cm 700gm']->fetch_error)->toContain('Invalid host');
    Http::assertSentCount(1);
});

test('the site crawler refuses a website that is not live', function () {
    $this->website->update(['state' => WebsiteStateEnum::CLOSED]);

    actingAs($this->user);

    $this->postJson(route('grp.models.website.crawl.store', $this->website->id), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('website');

    expect(Crawl::where('website_id', $this->website->id)->where('state', '!=', CrawlStateEnum::FINISH->value)->exists())->toBeFalse();
});
