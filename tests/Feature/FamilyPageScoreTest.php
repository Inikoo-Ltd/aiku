<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\CheckProductWebpage;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\UI\GetProductPageScore;
use App\Actions\Catalogue\Product\UI\GetProductWebpageAudit;
use App\Actions\Catalogue\ProductCategory\CheckFamilyWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\UI\GetFamilyPageScore;
use App\Actions\Catalogue\ProductCategory\UI\GetFamilySeoIssues;
use App\Actions\Web\Webpage\RenderWebpageHtml;
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
    $this->product = $product;

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
    RenderWebpageHtml::shouldRun()->andReturnNull()->byDefault();
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
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === null)))->toBe(['crawlable_content', 'h1', 'structured_data', 'sitemap', 'alt_image', 'broken_links', 'internal_links', 'robots_txt', 'page_speed'])
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === false)))->toBe(['extra_description', 'image'])
        ->and(collect($pageScore['areas'])->pluck('key')->all())->toBe(['content', 'structure', 'links', 'indexing', 'performance'])
        ->and(collect($pageScore['scores'])->pluck('score', 'key')->all())->toBe([
            'ai_discoverability' => 3,
            'seo'                => 6,
            'product_data'       => 5,
            'content_quality'    => 5,
        ])
        ->and($pageScore['passed'])->toBe(11)
        ->and($pageScore['score'])->toBe(50)
        ->and($pageScore['impact']['level'])->toBe('high')
        ->and($pageScore['last_crawl_at'])->toBeNull()
        ->and(route($pageScore['setting_route']['name'], $pageScore['setting_route']['parameters'], false))
        ->toBe("/org/{$this->organisation->slug}/shops/{$this->shop->slug}/catalogue/families/{$this->family->slug}?tab=edit_page&section=page_setting")
        ->and(route($pageScore['issues_route']['name'], $pageScore['issues_route']['parameters'], false))
        ->toBe("/org/{$this->organisation->slug}/shops/{$this->shop->slug}/catalogue/families/{$this->family->slug}?tab=edit_page&section=issues")
        ->and(collect($pageScore['areas'])->flatMap(fn (array $area) => $area['checks'])->where('is_crawl', true)->pluck('key')->all())
        ->toBe(['crawlable_content', 'h1', 'canonical_url', 'structured_data', 'sitemap', 'alt_image', 'broken_links', 'internal_links', 'robots_txt', 'page_speed'])
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

    expect(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === null)))->toBe(['crawlable_content', 'structured_data', 'internal_links', 'robots_txt'])
        ->and(array_keys(array_filter($checks, fn ($isPassed) => $isPassed === false)))->toBe(['extra_description', 'h1', 'broken_links', 'image'])
        ->and($pageScore['passed'])->toBe(14)
        ->and($pageScore['score'])->toBe(64)
        ->and($pageScore['impact']['level'])->toBe('medium')
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

    expect(collect($check['issues'])->pluck('type')->all())->toBe(['images_without_alt', 'missing_canonical', 'title_too_short', 'missing_meta_description', 'multiple_h1', 'invalid_structured_data'])
        ->and(CheckFamilyWebpage::cached($this->webpage))->toBe($check);

    $pageScore = GetFamilyPageScore::run($this->family->fresh());
    $checks    = pageScoreChecks($pageScore);

    $notes = collect($pageScore['areas'])->flatMap(fn (array $area) => $area['checks'])->pluck('note', 'key');

    expect($pageScore['last_crawl_at'])->not->toBeNull()
        ->and($notes['meta_title'])->toBe('The title is shorter than 20 characters. It is 5 characters now.')
        ->and($notes['meta_description'])->toBe(CrawlIssueTypeEnum::descriptions()['missing_meta_description'])
        ->and($checks)->toMatchArray([
            'meta_title'       => true,
            'meta_description' => true,
            'h1'               => false,
            'canonical_url'    => false,
            'alt_image'        => false,
            'sitemap'          => true,
            'page_speed'       => true,
            'broken_links'     => null,
            'structured_data'  => false,
        ]);

    $issues = GetFamilySeoIssues::run($this->family->fresh());

    expect($issues['total'])->toBe(6)
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
});

test('crawl page passes robots.txt and flags content only rendered by javascript', function () {
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

test('crawl page ignores robots.txt groups of other bots whose name is part of ours', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /basket\n\nUser-agent: SemrushBot\nUser-agent: SiteAuditBot\nDisallow: /\n\nUser-agent:\nDisallow: /"),
        '*'            => Http::response('<html><body></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']),
    ]);

    CheckFamilyWebpage::run($this->webpage->fresh());

    expect(pageScoreChecks(GetFamilyPageScore::run($this->family->fresh()))['robots_txt'])->toBeTrue();
});

test('crawl page on a product webpage passes robots.txt when only other bots are disallowed', function () {
    $webpage = $this->product->webpage ?? StoreProductWebpage::make()->action($this->product);

    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /app/basket\n\nUser-agent: SiteAuditBot\nDisallow: /"),
        '*'            => Http::response('<html><body></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    CheckProductWebpage::run($webpage->fresh());

    expect(CheckProductWebpage::cached($webpage)['is_allowed_by_robots_txt'])->toBeTrue();
});

test('a product meta title and description pass once filled in, with the length the crawl found in their note', function () {
    $webpage = $this->product->webpage ?? StoreProductWebpage::make()->action($this->product);
    $webpage->updateQuietly(['title' => 'Product title', 'description' => 'Product description']);
    Cache::forget(CheckProductWebpage::cacheKey($webpage));

    Http::fake(['*' => Http::response('<html><head><title>Short</title></head><body></body></html>', 200, ['Content-Type' => 'text/html'])]);
    CheckProductWebpage::run($webpage->fresh());

    $checks = collect(GetProductPageScore::run($this->product->fresh(), GetProductWebpageAudit::run($webpage->fresh()))['areas'])
        ->flatMap(fn (array $area) => $area['checks'])
        ->keyBy('key');

    expect($checks['meta_title'])->toMatchArray(['is_passed' => true, 'note' => 'The title is shorter than 20 characters. It is 5 characters now.'])
        ->and($checks['meta_description'])->toMatchArray(['is_passed' => true, 'note' => CrawlIssueTypeEnum::descriptions()['missing_meta_description']]);

    $webpage->updateQuietly(['title' => null]);

    expect(collect(GetProductPageScore::run($this->product->fresh(), GetProductWebpageAudit::run($webpage->fresh()))['areas'])
        ->flatMap(fn (array $area) => $area['checks'])
        ->firstWhere('key', 'meta_title')['is_passed'])->toBeFalse();
});

test('crawl page checks the structured data like the rich results test, rendering the page when the html has none', function () {
    $html = fn (array ...$jsonLd) => '<html><head><title>Family</title></head><body>'.implode('', array_map(fn (array $data) => '<script type="application/ld+json">'.json_encode($data).'</script>', $jsonLd)).'</body></html>';

    $breadcrumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://ecom.test/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Family'],
    ]];
    $collection = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'CollectionPage', 'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'item' => ['@type' => 'Product', 'name' => 'Candle', 'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'GBP', 'availability' => 'https://schema.org/InStock']]],
    ]]]]];

    $body = $html($breadcrumbs, $collection);
    Http::fake(function () use (&$body) {
        return Http::response($body, 200, ['Content-Type' => 'text/html']);
    });

    $check = CheckFamilyWebpage::run($this->webpage->fresh());

    expect($check['structured_data']['is_valid'])->toBeTrue()
        ->and(collect($check['structured_data']['items'])->pluck('type')->all())->toBe(['BreadcrumbList', 'Product'])
        ->and($check['structured_data']['items'][1]['warnings'])->toContain('Missing field "brand" in Product (optional)')
        ->and(collect($check['issues'])->pluck('type'))->not->toContain('invalid_structured_data')
        ->and(pageScoreChecks(GetFamilyPageScore::run($this->family->fresh()))['structured_data'])->toBeTrue();

    $collection['@graph'][0]['mainEntity']['itemListElement'][0]['item']['offers']['price'] = '10,00';
    unset($breadcrumbs['itemListElement'][0]['item']);
    $body = $html($breadcrumbs, $collection).'<script type="application/ld+json">{oops</script>';

    $check = CheckFamilyWebpage::run($this->webpage->fresh());

    expect($check['structured_data']['is_valid'])->toBeFalse()
        ->and(collect($check['issues'])->firstWhere('type', 'invalid_structured_data')['details']['errors'])->toBe([
            'Unparsable structured data in 1 block(s)',
            'Missing field "item" in BreadcrumbList.itemListElement',
            'Invalid price format in Product.offers',
        ])
        ->and(pageScoreChecks(GetFamilyPageScore::run($this->family->fresh()))['structured_data'])->toBeFalse();

    RenderWebpageHtml::shouldRun()->with('https://ecom.test/page-score-family')->once()->andReturn($html($breadcrumbs + ['itemListElement' => []], ['@type' => 'FAQPage', 'mainEntity' => [['@type' => 'Question', 'name' => 'Why?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Because']]]]));
    $body = '<html><body><div id="app"></div></body></html>';

    expect(collect(CheckFamilyWebpage::run($this->webpage->fresh())['structured_data']['items'])->pluck('type')->all())->toBe(['BreadcrumbList', 'FAQPage']);
});

test('crawl page on a product webpage feeds its structured data check and lists what fails', function () {
    $webpage = $this->product->webpage ?? StoreProductWebpage::make()->action($this->product);
    Cache::forget(CheckProductWebpage::cacheKey($webpage));

    expect(GetProductWebpageAudit::run($webpage->fresh())['checks']['structured_data']['is_passed'])->toBeNull();

    $page = fn (array $product) => '<html><head><title>Product</title></head><body><script type="application/ld+json">'.json_encode(['@context' => 'https://schema.org', '@type' => 'Product'] + $product).'</script></body></html>';

    $body = $page(['name' => 'Candle', 'offers' => ['@type' => 'Offer', 'price' => 10, 'priceCurrency' => 'GBP']]);
    Http::fake(function () use (&$body) {
        return Http::response($body, 200, ['Content-Type' => 'text/html']);
    });
    CheckProductWebpage::run($webpage->fresh());

    expect(GetProductWebpageAudit::run($webpage->fresh())['checks']['structured_data']['is_passed'])->toBeTrue();

    $body = $page(['name' => 'Candle']);
    CheckProductWebpage::run($webpage->fresh());
    $audit = GetProductWebpageAudit::run($webpage->fresh());

    expect($audit['checks']['structured_data']['is_passed'])->toBeFalse()
        ->and(collect($audit['issue_groups'])->firstWhere('key', 'other')['issues'])->toContain([
            'id'          => 'check-'.(count(CheckProductWebpage::cached($webpage)['issues']) - 1),
            'title'       => 'Structured data not eligible for rich results',
            'description' => CrawlIssueTypeEnum::descriptions()['invalid_structured_data'],
            'severity'    => 'warning',
            'found'       => 'Either "offers", "review", "aggregateRating" should be specified in Product',
        ]);
});
