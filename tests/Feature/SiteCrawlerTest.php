<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Crawl\AuditWebsite;
use App\Actions\Web\Crawl\UI\CompareCrawls;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Services\SiteAudit\CustomExtraction;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop         = createShop($this->organisation)[2];
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
