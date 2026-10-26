<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\ProductCategory\CheckFamilyWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\UI\GetFamilyPageScore;
use App\Actions\Catalogue\ProductCategory\UI\GetFamilySeoIssues;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use App\Models\Web\SeoContentSuggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
    $this->website      = createWebsite($this->shop);

    [, $product] = createProduct($this->shop);
    $product->updateQuietly(['is_for_sale' => true, 'price' => 10]);

    $this->family = $product->family;
    $this->family->updateQuietly([
        'description'       => '<p>Top description</p>',
        'description_extra' => '<p> </p>',
        'faq'               => [['question' => 'Why?', 'answer' => 'Because']],
        'image_id'          => null,
    ]);

    $this->webpage = $this->family->webpage ?? StoreProductCategoryWebpage::make()->action($this->family);
    $this->webpage->updateQuietly([
        'title'            => 'Family title',
        'description'      => 'Family description',
        'breadcrumb_label' => 'Family',
        'canonical_url'    => 'https://ecom.test/page-score-family',
        'url'              => 'page-score-family',
        'state'            => WebpageStateEnum::LIVE,
        'index_page'       => true,
        'follow_link'      => true,
        'structured_data'  => null,
        'seo_data'         => [],
    ]);

    CrawlPage::where('webpage_id', $this->webpage->id)->delete();
    SeoContentSuggestion::where('webpage_id', $this->webpage->id)->delete();
    Cache::forget(CheckFamilyWebpage::cacheKey($this->webpage));
});

function pageScoreChecks(array $pageScore): array
{
    return collect($pageScore['areas'])->flatMap(fn (array $area) => $area['checks'])->pluck('is_passed', 'key')->all();
}

test('checks read from the site audit are not checked before the page is crawled', function () {
    SeoContentSuggestion::create([
        'website_id'    => $this->webpage->website_id,
        'webpage_id'    => $this->webpage->id,
        'field'         => 'title',
        'current_value' => 'Family title',
        'suggestion'    => 'Better family title',
        'reason'        => SeoContentSuggestion::REASON_SEO_REVIEW,
        'state'         => SeoContentSuggestionStateEnum::PENDING,
    ]);

    $pageScore = GetFamilyPageScore::run($this->family->fresh());
    $checks    = pageScoreChecks($pageScore);

    expect($checks)->toHaveCount(22)
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === null)))->toBe(['crawlable_content', 'h1', 'sitemap', 'alt_image', 'broken_links', 'internal_links', 'robots_txt', 'page_speed'])
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === false)))->toBe(['extra_description', 'structured_data', 'image'])
        ->and(collect($pageScore['areas'])->pluck('key')->all())->toBe(['content', 'structure', 'links', 'indexing', 'performance'])
        ->and(collect($pageScore['scores'])->pluck('score', 'key')->all())->toBe([
            'ai_discoverability' => 3,
            'seo'                => 6,
            'product_data'       => 5,
            'content_quality'    => 5,
        ])
        ->and($pageScore['passed'])->toBe(11)
        ->and($pageScore['score'])->toBe(50)
        ->and($pageScore['last_crawl_at'])->toBeNull()
        ->and(route($pageScore['setting_route']['name'], $pageScore['setting_route']['parameters'], false))
        ->toBe("/org/{$this->organisation->slug}/shops/{$this->shop->slug}/catalogue/families/{$this->family->slug}?tab=edit_page&section=page_setting")
        ->and(route($pageScore['issues_route']['name'], $pageScore['issues_route']['parameters'], false))
        ->toBe("/org/{$this->organisation->slug}/shops/{$this->shop->slug}/catalogue/families/{$this->family->slug}?tab=edit_page&section=issues")
        ->and(collect($pageScore['areas'])->flatMap(fn (array $area) => $area['checks'])->where('is_crawl', true)->pluck('key')->all())
        ->toBe(['crawlable_content', 'h1', 'canonical_url', 'sitemap', 'alt_image', 'broken_links', 'internal_links', 'robots_txt', 'page_speed'])
        ->and(collect($pageScore['steps'])->pluck('is_done', 'key')->all())->toBe([
            'created'     => true,
            'published'   => true,
            'seo_review'  => true,
            'implemented' => false,
        ]);
});

test('the latest finished site audit fails the checks it found issues for, broken links included', function () {
    $crawl = AuditWebsite::startFor($this->website, CrawlTriggerEnum::USER);
    $crawl->update(['state' => CrawlStateEnum::FINISH, 'end_at' => now()]);

    $pageUrl   = 'https://ecom.test/page-score-family';
    $crawlPage = CrawlPage::create([
        'crawl_id'      => $crawl->id,
        'webpage_id'    => $this->webpage->id,
        'url'           => $pageUrl,
        'url_hash'      => md5($pageUrl),
        'status_code'   => 200,
        'is_in_sitemap' => true,
        'is_indexable'  => true,
    ]);
    $brokenPage = CrawlPage::create([
        'crawl_id'    => $crawl->id,
        'url'         => 'https://ecom.test/gone',
        'url_hash'    => md5('https://ecom.test/gone'),
        'status_code' => 404,
    ]);

    CrawlIssue::create(['crawl_id' => $crawl->id, 'crawl_page_id' => $crawlPage->id, 'type' => CrawlIssueTypeEnum::MISSING_H1, 'severity' => CrawlIssueTypeEnum::MISSING_H1->severity()]);
    CrawlIssue::create(['crawl_id' => $crawl->id, 'crawl_page_id' => $brokenPage->id, 'type' => CrawlIssueTypeEnum::HTTP_4XX, 'severity' => CrawlIssueTypeEnum::HTTP_4XX->severity(), 'details' => ['linked_from' => [$pageUrl]]]);

    $pageScore = GetFamilyPageScore::run($this->family->fresh());
    $checks    = pageScoreChecks($pageScore);

    expect(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === null)))->toBe(['crawlable_content', 'internal_links', 'robots_txt'])
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === false)))->toBe(['extra_description', 'h1', 'structured_data', 'broken_links', 'image'])
        ->and($pageScore['passed'])->toBe(14)
        ->and($pageScore['score'])->toBe(64)
        ->and($pageScore['last_crawl_at'])->not->toBeNull()
        ->and($pageScore['steps'][2]['is_done'])->toBeFalse();
});

test('the issues tab groups the latest site audit findings by kind, most severe first, with the broken links from the page', function () {
    expect(GetFamilySeoIssues::run($this->family->fresh()))->toBe(['last_crawl_at' => null, 'total' => 0, 'groups' => []]);

    $crawl = AuditWebsite::startFor($this->website, CrawlTriggerEnum::USER);
    $crawl->update(['state' => CrawlStateEnum::FINISH, 'end_at' => now()]);

    $pageUrl    = 'https://ecom.test/page-score-family';
    $crawlPage  = CrawlPage::create([
        'crawl_id'      => $crawl->id,
        'webpage_id'    => $this->webpage->id,
        'url'           => $pageUrl,
        'url_hash'      => md5($pageUrl),
        'status_code'   => 200,
        'is_in_sitemap' => true,
        'is_indexable'  => true,
    ]);
    $brokenPage = CrawlPage::create([
        'crawl_id'    => $crawl->id,
        'url'         => 'https://ecom.test/gone',
        'url_hash'    => md5('https://ecom.test/gone'),
        'status_code' => 404,
    ]);

    CrawlIssue::create(['crawl_id' => $crawl->id, 'crawl_page_id' => $crawlPage->id, 'type' => CrawlIssueTypeEnum::TITLE_TOO_SHORT, 'severity' => CrawlIssueTypeEnum::TITLE_TOO_SHORT->severity(), 'details' => ['title' => 'Family title', 'length' => 12]]);
    CrawlIssue::create(['crawl_id' => $crawl->id, 'crawl_page_id' => $crawlPage->id, 'type' => CrawlIssueTypeEnum::MULTIPLE_H1, 'severity' => CrawlIssueTypeEnum::MULTIPLE_H1->severity(), 'details' => ['h1_count' => 2]]);
    CrawlIssue::create(['crawl_id' => $crawl->id, 'crawl_page_id' => $brokenPage->id, 'type' => CrawlIssueTypeEnum::HTTP_4XX, 'severity' => CrawlIssueTypeEnum::HTTP_4XX->severity(), 'details' => ['linked_from' => [$pageUrl], 'status_code' => 404]]);

    $issues = GetFamilySeoIssues::run($this->family->fresh());

    expect($issues['total'])->toBe(3)
        ->and($issues['last_crawl_at'])->not->toBeNull()
        ->and(collect($issues['groups'])->pluck('key')->all())->toBe([GetFamilySeoIssues::BROKEN_LINKS, 'multiple_h1', 'title_too_short'])
        ->and($issues['groups'][0]['items'][0])->toMatchArray(['title' => 'Broken link', 'type' => 'http_4xx', 'severity' => 'error', 'url' => 'https://ecom.test/gone'])
        ->and($issues['groups'][1]['items'][0])->toMatchArray(['severity' => 'warning', 'details' => ['h1_count' => 2], 'url' => null]);
});

test('crawl page checks the family webpage alone and its findings stand in for the audit in the page score and issues', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>Short</title></head><body><h1>One</h1><h1>Two</h1><img src="a.png"></body></html>',
        200,
        ['Content-Type' => 'text/html; charset=UTF-8']
    )]);

    $check = CheckFamilyWebpage::run($this->webpage->fresh());

    expect(collect($check['issues'])->pluck('type')->all())->toBe(['images_without_alt', 'missing_canonical', 'title_too_short', 'missing_meta_description', 'multiple_h1'])
        ->and(CheckFamilyWebpage::cached($this->webpage))->toBe($check);

    $pageScore = GetFamilyPageScore::run($this->family->fresh());
    $checks    = pageScoreChecks($pageScore);

    expect($pageScore['last_crawl_at'])->not->toBeNull()
        ->and($checks)->toMatchArray([
            'meta_title'       => false,
            'meta_description' => false,
            'h1'               => false,
            'canonical_url'    => false,
            'alt_image'        => false,
            'sitemap'          => true,
            'page_speed'       => true,
            'broken_links'     => null,
        ]);

    $issues = GetFamilySeoIssues::run($this->family->fresh());

    expect($issues['total'])->toBe(5)
        ->and($issues['last_crawl_at'])->toBe($pageScore['last_crawl_at'])
        ->and(collect($issues['groups'])->pluck('key')->all())->toContain('multiple_h1', 'title_too_short');
});

test('crawl page reads robots.txt, internal links and whether the description is in the html', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /page-score-family"),
        '*'            => Http::response(
            '<html><head><title>Family</title></head><body><h1>Family</h1><div><p>Top description</p></div><a href="/other-page">Other</a><a href="mailto:a@b.c">Mail</a></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        ),
    ]);

    CheckFamilyWebpage::run($this->webpage->fresh());

    expect(pageScoreChecks(GetFamilyPageScore::run($this->family->fresh())))->toMatchArray([
        'robots_txt'        => false,
        'internal_links'    => true,
        'crawlable_content' => true,
    ]);

    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /basket"),
        '*'            => Http::response(
            '<html><body><div id="app" data-page="{&quot;description&quot;:&quot;Top description&quot;}"></div><script>render("Top description")</script></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        ),
    ]);

    CheckFamilyWebpage::run($this->webpage->fresh());

    expect(pageScoreChecks(GetFamilyPageScore::run($this->family->fresh())))->toMatchArray([
        'robots_txt'        => true,
        'internal_links'    => false,
        'crawlable_content' => false,
    ]);
});
