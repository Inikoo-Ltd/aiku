<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 15:08:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Web\Banner\DeleteBanner;
use App\Actions\Web\Banner\Search\ReindexBannerSearch;
use App\Actions\Web\Banner\StoreBanner;
use App\Actions\Web\Banner\UpdateBanner;
use App\Actions\Web\ExternalLink\AttachExternalLinkToWebBlock;
use App\Actions\Web\ExternalLink\CheckExternalLinkStatus;
use App\Actions\Web\ExternalLink\StoreExternalLink;
use App\Actions\Web\ModelHasWebBlocks\DeleteModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Webpage\HydrateWebpage;
use App\Actions\Web\Webpage\Search\ReindexWebpageSearch;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Website\HydrateWebsite;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\Search\ReindexWebsiteSearch;
use App\Actions\Web\Website\StoreWebsite;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Helpers\Snapshot;
use App\Models\Helpers\SnapshotStats;
use App\Models\Helpers\UniversalSearch;
use App\Models\Web\Banner;
use App\Models\Web\ExternalLink;
use App\Models\Web\Redirect;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageStats;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});
beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();
    $this->warehouse  = createWarehouse();
    $this->fulfilment = createFulfilment($this->organisation);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});

test('create b2b website', function () {



    $website = StoreWebsite::make()->action(
        $this->shop,
        Website::factory()->definition()
    );


    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->storefront)->toBeInstanceOf(Webpage::class)
        ->and($website->webStats->number_webpages)->toBe(15);


    return $website;
});

test('launch website', function (Website $website) {
    $website = LaunchWebsite::make()->action($website);
    $website->refresh();

    expect($website->state)->toBe(WebsiteStateEnum::LIVE)
        ->and($website->status)->toBeTrue()
        ->and($website->launched_at)->toBeInstanceOf(Carbon::class);


    $home = $website->storefront;
    expect($home)->toBeInstanceOf(Webpage::class)
        ->and($home->state)->toBe(WebpageStateEnum::LIVE)
        ->and($home->live_at)->toBeInstanceOf(Carbon::class)
        ->and($home->stats->number_snapshots)->toBe(2)
        ->and($home->stats->number_deployments)->toBe(1);
})->depends('create b2b website');


test('update website', function (Website $website) {
    $updateData = [
        'name' => 'Test Website Updated',
    ];

    $shop = UpdateWebsite::make()->action($website, $updateData);
    $shop->refresh();

    expect($shop->name)->toBe('Test Website Updated');
})->depends('create b2b website');

test('create webpage', function (Website $website) {
    $webpage = StoreWebpage::make()->action($website->storefront, Webpage::factory()->definition());

    expect($webpage)->toBeInstanceOf(Webpage::class)
        ->and($webpage->level)->toBe(2)
        ->and($webpage->state)->toBe(WebpageStateEnum::IN_PROCESS)
        ->and($webpage->is_fixed)->toBeFalse()
        ->and($webpage->stats)->toBeInstanceOf(WebpageStats::class)
        ->and($webpage->unpublishedSnapshot)->toBeInstanceOf(Snapshot::class);

    $snapshot = $webpage->unpublishedSnapshot;


    expect($snapshot->layout)->toBeArray()
        ->and($snapshot->stats)->toBeInstanceOf(SnapshotStats::class)
        ->and($snapshot->layout['web_blocks'])->toBeArray()
        ->and($snapshot->checksum)->toBeString()
        ->and($snapshot->state)->toBe(SnapshotStateEnum::UNPUBLISHED);

    return $webpage;
})->depends('create b2b website');

test('create model has web block', function (Webpage $webpage) {
    /** @var WebBlockType $webBlockType */
    $webBlockType = $webpage->group->webBlockTypes()->where('code', 'text')->first();
    expect($webBlockType)->toBeInstanceOf(WebBlockType::class);

    $modelHasWebBlock = StoreModelHasWebBlock::make()->action(
        $webpage,
        [
            'web_block_type_id' => $webBlockType->id,
            'position'          => 0
        ]
    );

    expect($modelHasWebBlock)->toBeInstanceOf(ModelHasWebBlocks::class)
        ->and($modelHasWebBlock->webBlock)->toBeInstanceOf(WebBlock::class);

    $webpage->refresh();
    expect($webpage->is_dirty)->toBeTrue();

    return $modelHasWebBlock;
})->depends('create webpage');


test('model external link', function () {
    $externalLink = ExternalLink::class;
    expect($externalLink)->toBe(ExternalLink::class);

    return $externalLink;
});

test('store external link', function (ModelHasWebBlocks $modelHasWebBlock) {
    $group    = $modelHasWebBlock->group;
    $webpage  = $modelHasWebBlock->webpage;
    $webBlock = $modelHasWebBlock->webBlock;


    CheckExternalLinkStatus::shouldRun()->andReturn(200);

    $link   = 'https://www.google.com';
    $status = CheckExternalLinkStatus::run($link);


    $externalLink = StoreExternalLink::make()->action($group, [
        'url'    => $link,
        'status' => $status,
    ]);
    AttachExternalLinkToWebBlock::make()->action($webpage, $webBlock, $externalLink, [
        'show' => true
    ]);

    expect($externalLink)->toBeInstanceOf(ExternalLink::class)
        ->and($externalLink->group_id)->toBe($group->id)
        ->and($externalLink->number_websites_shown)->toBe(1)
        ->and($externalLink->number_webpages_shown)->toBe(1)
        ->and($externalLink->number_web_blocks_shown)->toBe(1)
        ->and($externalLink->number_websites_hidden)->toBe(0)
        ->and($externalLink->number_webpages_hidden)->toBe(0)
        ->and($externalLink->number_web_blocks_hidden)->toBe(0);


    return $externalLink;
})->depends("create model has web block");

test('model external link has web blocks', function (ExternalLink $externalLink) {
    $webBlocks = $externalLink->webBlocks;
    expect($webBlocks)->toBeInstanceOf(Collection::class)
        ->and(count($webBlocks->toArray()))->toBeGreaterThan(0)
        ->and($webBlocks[0])->toBeInstanceOf(WebBlock::class);
})->depends('store external link');

test('model external link has webpages', function (ExternalLink $externalLink) {
    $webpages = $externalLink->webpages;
    expect($webpages)->toBeInstanceOf(Collection::class)
        ->and(count($webpages->toArray()))->toBeGreaterThan(0)
        ->and($webpages[0])->toBeInstanceOf(Webpage::class);
})->depends('store external link');

test('model external link has websites', function (ExternalLink $externalLink) {
    $websites = $externalLink->websites;
    expect($websites)->toBeInstanceOf(Collection::class)
        ->and(count($websites->toArray()))->toBeGreaterThan(0)
        ->and($websites[0])->toBeInstanceOf(Website::class);
})->depends('store external link');

test('update model has web block', function (ModelHasWebBlocks $modelHasWebBlock) {
    $modelHasWebBlock = UpdateModelHasWebBlocks::make()->action($modelHasWebBlock, ['layout' => ['text' => 'Test Text']]);
    expect($modelHasWebBlock)->toBeInstanceOf(ModelHasWebBlocks::class);
})->depends('create model has web block');

test('delete model has web block', function (ModelHasWebBlocks $modelHasWebBlock) {
    // clean up external links
    DB::table('web_block_has_external_link')->where('group_id', $modelHasWebBlock->group_id)->delete();
    DB::table('model_has_web_blocks')->where('group_id', $modelHasWebBlock->group_id)->delete();

    $modelHasWebBlock = DeleteModelHasWebBlocks::make()->action($modelHasWebBlock, []);
    expect($modelHasWebBlock)->toBeInstanceOf(ModelHasWebBlocks::class);
})->depends('create model has web block');

// Fulfilment Website

test('create fulfilment website', function () {
    $website = StoreWebsite::make()->action(
        $this->fulfilment->shop,
        Website::factory()->definition()
    );


    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->type)->toBe(WebsiteTypeEnum::FULFILMENT)
        ->and($website->state)->toBe(WebsiteStateEnum::IN_PROCESS)
        ->and($website->storefront)->toBeInstanceOf(Webpage::class)
        ->and($website->webStats->number_webpages)->toBe(9);

    /** @var Webpage $homeWebpage */
    $homeWebpage = $website->webpages()->first();

    expect($homeWebpage->type)->toBe(WebpageTypeEnum::STOREFRONT)
        ->and($homeWebpage->state)->toBe(WebpageStateEnum::READY)
        ->and($homeWebpage->ready_at)->toBeInstanceOf(Carbon::class)
        ->and($homeWebpage->level)->toBe(1)
        ->and($homeWebpage->stats->number_child_webpages)->toBe(2)
        ->and($homeWebpage->stats->number_snapshots)->toBe(1)
        ->and($homeWebpage->stats->number_deployments)->toBe(0)
        ->and($homeWebpage->unpublishedSnapshot)->toBeInstanceOf(Snapshot::class)
        ->and($homeWebpage->unpublishedSnapshot->layout)->toBeArray();

    return $website;
});

test('launch fulfilment website from command', function (Website $website) {
    $this->artisan('website:launch', ['website' => $website->slug])
        ->expectsOutput('Website launched 🚀')
        ->assertExitCode(0);
    $website->refresh();

    expect($website->state)->toBe(WebsiteStateEnum::LIVE);

    /** @var Webpage $homeWebpage */
    $homeWebpage = $website->webpages()->first();
    expect($homeWebpage->type)->toBe(WebpageTypeEnum::STOREFRONT)
        ->and($homeWebpage->ready_at)->toBeInstanceOf(Carbon::class)
        ->and($homeWebpage->live_at)->toBeInstanceOf(Carbon::class)
        ->and($homeWebpage->stats->number_snapshots)->toBe(2)
        ->and($homeWebpage->stats->number_deployments)->toBe(1);

    return $website;
})->depends('create fulfilment website');


// Hydrator commands



test('store hello banner', function (Website $website) {
    $banner = StoreBanner::make()->action($website, [
        'name' => 'hello',
        'type' => BannerTypeEnum::LANDSCAPE,
    ]);

    expect($banner)->toBeInstanceOf(Banner::class)
        ->and($banner->name)->toBe('hello')
        ->and($banner->type)->toBe(BannerTypeEnum::LANDSCAPE);

    return $banner;
})->depends('create b2b website');

test('update hello banner', function (Banner $banner) {
    $banner = UpdateBanner::make()->action($banner, [
        'name' => 'hello2',
    ]);

    expect($banner)->toBeInstanceOf(Banner::class)
        ->and($banner->name)->toBe('hello2')
        ->and($banner->type)->toBe(BannerTypeEnum::LANDSCAPE);
    return $banner;
})->depends('store hello banner');

test('banners search', function ($banner) {

    $this->artisan('search:banners')->assertExitCode(0);
    ReindexBannerSearch::run($banner);
    $banner->refresh();
    expect($banner->universalSearch()->count())->toBe(1);
})->depends('store hello banner');

test('delete hello banner', function (Banner $banner) {
    $banner = DeleteBanner::make()->action($banner);

    expect($banner)->toBeInstanceOf(Banner::class)
        ->and($banner->trashed())->toBeTrue()
        ->and(UniversalSearch::where(
            'model_type',
            'Banner'
        )->count())->toBe(0);
})->depends('update hello banner');

test('websites search', function () {
    $this->artisan('search:websites')->assertExitCode(0);

    $website = Website::first();
    ReindexWebsiteSearch::run($website);
    expect($website->universalSearch()->count())->toBe(1);
});

test('webpages search', function () {
    $this->artisan('search:webpages')->assertExitCode(0);

    $webpage = Webpage::first();
    ReindexWebpageSearch::run($webpage);
    expect($webpage->universalSearch()->count())->toBe(1);
});



test('hydrate website', function () {
    $website = Website::first();
    $this->artisan('hydrate:websites', [
        'organisations' => $this->organisation->slug,
        '--slugs'       => $website->slug
    ])
        ->assertExitCode(0);

    HydrateWebsite::run($website);
    $website->refresh();

});

test('hydrate webpage', function () {
    $webpage = Webpage::first();
    $this->artisan('hydrate:webpages', [
        '--slugs'       => $webpage->slug
    ])
        ->assertExitCode(0);

    HydrateWebpage::run($webpage);
});

test('web hydrator', function () {
    $this->artisan('hydrate -s web')->assertExitCode(0);
});

test('store redirect', function (Webpage $webpage) {

    $homepage = $webpage->website->storefront;

    $redirect = StoreRedirect::make()->action($webpage, [
        'type' => RedirectTypeEnum::PERMANENT,
        'to_webpage_id' => $homepage->id
    ]);

    expect($redirect)->toBeInstanceOf(Redirect::class)
        ->and($redirect->type)->toBe(RedirectTypeEnum::PERMANENT)
        ->and($redirect->from_path)->toBe($webpage->url)
        ->and($redirect->from_url)->toBe('https://'.$redirect->website->domain . '/' . $webpage->url);

    return $redirect;
})->depends('create webpage');



test('web sitemap creation', function () {
    $this->artisan('sitemaps:create')->assertExitCode(0);
});
