<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 15:08:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\Web\Announcement\DeleteAnnouncement;
use App\Actions\Web\Announcement\PublishAnnouncement;
use App\Actions\Web\Announcement\StoreAnnouncement;
use App\Actions\Web\Announcement\UpdateAnnouncement;
use App\Actions\Web\Banner\DeleteBanner;
use App\Actions\Web\Banner\StoreBanner;
use App\Actions\Web\Banner\UpdateBanner;
use App\Actions\Web\ExternalLink\AttachExternalLinkToWebBlock;
use App\Actions\Web\ExternalLink\CheckExternalLinkStatus;
use App\Actions\Web\ExternalLink\StoreExternalLink;
use App\Actions\Web\ModelHasWebBlocks\DeleteModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Redirect\StoreRedirectFromWebpage;
use App\Actions\Web\Webpage\HydrateWebpage;
use App\Actions\Web\Webpage\Iris\ShowIrisRobotsTxt;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Actions\Web\Webpage\ProcessWebpageTimeSeriesRecords;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Actions\Web\Webpage\UpdateWebpageCanonicalUrl;
use App\Actions\Web\Website\AutosaveWebsiteMarginal;
use App\Actions\Web\Website\Cloudflare\BlockCountriesInCloudflare;
use App\Actions\Web\Website\HydrateWebsite;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\ProcessWebsiteTimeSeriesRecords;
use App\Actions\Web\Website\PublishWebsiteMarginal;
use App\Actions\Web\Website\SaveWebsiteSitemap;
use App\Actions\Web\Website\SaveWebsitesSitemap;
use App\Actions\Web\Website\StoreWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Actions\Web\Website\UpdateWebsite;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Web\WebsiteTabsEnum;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Helpers\Snapshot;
use App\Models\Helpers\SnapshotStats;
use App\Models\Web\Announcement;
use App\Models\Web\Banner;
use App\Models\Web\ExternalLink;
use App\Models\Web\Redirect;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageStats;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Lorisleiva\Actions\ActionRequest;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

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

    ReindexWebpageLuigiData::shouldRun();
    ReindexWebpageLuigiData::mock()
        ->shouldReceive('getJobUniqueId')
        ->andReturn(1);

    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);
});

test('create b2b website', function () {
    $website = StoreWebsite::make()->action(
        $this->shop,
        Website::factory()->definition()
    );


    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->storefront)->toBeInstanceOf(Webpage::class)
        ->and($website->webStats->number_webpages)->toBe(9);


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

    return $website;
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
        ->and($website->webStats->number_webpages)->toBe(7);

    /** @var Webpage $homeWebpage */
    $homeWebpage = $website->webpages()->where('type', WebpageTypeEnum::STOREFRONT)->first();

    expect($homeWebpage->state)->toBe(WebpageStateEnum::READY)
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
    $homeWebpage = $website->webpages()->where('type', WebpageTypeEnum::STOREFRONT)->first();
    expect($homeWebpage->ready_at)->toBeInstanceOf(Carbon::class)
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
        'ratio' => '16/9'
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


test('delete hello banner', function (Banner $banner) {
    $banner = DeleteBanner::make()->action($banner);

    expect($banner)->toBeInstanceOf(Banner::class)
        ->and($banner->trashed())->toBeTrue();
})->depends('update hello banner');


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
        '--slugs' => $webpage->slug
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
        'type'          => RedirectTypeEnum::PERMANENT,
        'to_webpage_id' => $homepage->id
    ]);

    expect($redirect)->toBeInstanceOf(Redirect::class)
        ->and($redirect->type)->toBe(RedirectTypeEnum::PERMANENT)
        ->and($redirect->from_path)->toBe($webpage->url)
        ->and($redirect->from_url)->toBe('https://www.'.$redirect->website->domain.'/'.$webpage->url);

    return $redirect;
})->depends('create webpage');


test('web sitemap creation', function () {

    SaveWebsitesSitemap::run();
    $this->artisan('sitemaps:create')->assertExitCode(0);

});

// UI

test('UI index websites in organisation', function () {
    $response = get(
        route('grp.org.websites.index', [$this->organisation->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Websites')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "websites")->etc()
            )
            ->has('data');
    });
})->depends('create b2b website');

test('UI show fulfilment website', function (Website $website) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.fulfilments.show.web.websites.show', [
            $this->organisation->slug,
            $this->fulfilment,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has('breadcrumbs', 2);
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment website (tab showcase)', function (Website $website) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.fulfilments.show.web.websites.show', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug,
            'tab' => WebsiteTabsEnum::SHOWCASE->value
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has(
                "tabs",
                fn (AssertableInertia $page) => $page->where("current", WebsiteTabsEnum::SHOWCASE->value)->etc()
            )
            ->has(WebsiteTabsEnum::SHOWCASE->value)
            ->has('breadcrumbs', 2);
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment website (tab external links)', function (Website $website) {
    $response = get(
        route('grp.org.fulfilments.show.web.websites.show', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug,
            'tab' => WebsiteTabsEnum::EXTERNAL_LINKS->value
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has(
                "tabs",
                fn (AssertableInertia $page) => $page->where("current", WebsiteTabsEnum::EXTERNAL_LINKS->value)->etc()
            )
            ->has(WebsiteTabsEnum::EXTERNAL_LINKS->value)
            ->has('breadcrumbs', 2);
    });
})->depends('launch fulfilment website from command');

test('UI index webpages in fulfilment website', function (Website $website) {
    $response = get(
        route('grp.org.fulfilments.show.web.webpages.index', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Webpages')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data.data', 7);
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment website workshop', function (Website $website) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.fulfilments.show.web.websites.workshop', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/WebsiteWorkshop')
            ->where('title', "Website's workshop")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "Workshop")->etc()
            )
            ->has('breadcrumbs', 2)
            ->has('tabs');
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment website workshop (header)', function (Website $website) {
    $response = get(
        route('grp.org.fulfilments.show.web.websites.workshop.header', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/Header/HeaderWorkshop')
            ->where('title', "Website Header's Workshop")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "Header's Workshop")->etc()
            )
            ->has('breadcrumbs', 0)
            ->has('uploadImageRoute')
            ->has('autosaveRoute')
            ->has('route_list')
            ->has('data')
            ->has('web_block_types');
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment website workshop (footer)', function (Website $website) {
    $response = get(
        route('grp.org.fulfilments.show.web.websites.workshop.footer', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($website) {
        $page
            ->component('Org/Web/Workshop/Footer/FooterWorkshop')
            ->where('title', "footer")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $website->code)->etc()
            )
            ->has('breadcrumbs', 0)
            ->has('uploadImageRoute')
            ->has('autosaveRoute')
            ->has('data')
            ->has('webBlockTypes');
    });
})->depends('launch fulfilment website from command');

test('UI website workshop menu', function (Website $website) {
    $response = get(
        route('grp.org.shops.show.web.websites.workshop.menu', [
            $this->organisation->slug,
            $this->shop->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/Menu/MenuWorkshop')
            ->where('title', "Website Menu's Workshop")
            ->has('breadcrumbs')
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "Menu's Workshop")->etc()
            )
            ->has('autosaveRoute')
            ->has('data')
            ->has('webBlockTypes')
            ->has('uploadImageRoute');
    });
})->depends('launch fulfilment website from command');

test('UI edit fulfilment website', function (Website $website, Banner $banner) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.fulfilments.show.web.websites.edit', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug,
            $banner->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 2)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'Settings')->etc()
            )
            ->has('formData');
    });
})->depends('launch fulfilment website from command', 'UI store fulfilment banner');

test('UI store fulfilment banner', function (Website $website) {
    $banner = StoreBanner::make()->action($website, [
        'name' => 'fulfilmentBanner',
        'type' => BannerTypeEnum::LANDSCAPE,
        'ratio' => '16/9',
    ]);
    $banner->refresh();

    expect($banner)->toBeInstanceOf(Banner::class)
        ->and($banner->name)->toBe('fulfilmentBanner');

    return $banner;
})->depends('launch fulfilment website from command');

test('UI create banner', function (Website $website) {
    $response = get(
        route('grp.org.shops.show.web.banners.create', [
            $this->organisation->slug,
            $this->shop->slug,
            $website->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'New banner')
            ->has('breadcrumbs', 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'Banner')->etc()
            )
            ->has('formData');
    });
})->depends('launch fulfilment website from command');

test('UI index fulfilment banners', function (Website $website) {
    $response = get(
        route('grp.org.fulfilments.show.web.banners.index', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Banners/Banners')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "banners")->etc()
            )
            ->has('data');
    });
})->depends('launch fulfilment website from command');

test('UI show fulfilment banner', function (Website $website, Banner $banner) {
    $response = get(
        route('grp.org.fulfilments.show.web.banners.show', [
            $this->organisation->slug,
            $this->fulfilment,
            $website->slug,
            $banner->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($banner) {
        $page
            ->component('Org/Web/Banners/Banner')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $banner->name)->etc()
            )
            ->has('tabs');
    });
})->depends('launch fulfilment website from command', 'UI store fulfilment banner');

test('UI edit fulfilment banner', function (Website $website, Banner $banner) {
    $this->withoutExceptionHandling();
    $oldState = $banner->state;
    if ($banner->state != BannerStateEnum::LIVE) {
        $banner->update(['state' => BannerStateEnum::LIVE->value]);
    }

    $response = get(
        route('grp.org.fulfilments.show.web.banners.edit', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug,
            $banner->slug,
            'section' => 'properties'
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($banner) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $banner->name)->etc()
            )
            ->has('formData');
    });

    $banner->update(['state' => $oldState]);
})->depends('launch fulfilment website from command', 'UI store fulfilment banner');

test('UI show fulfilment banner workshop', function (Website $website, Banner $banner) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.fulfilments.show.web.banners.workshop', [
            $this->organisation->slug,
            $this->fulfilment->slug,
            $website->slug,
            $banner->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Banners/BannerWorkshop')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "Banner's workshop")->etc()
            )
            ->has('publishRoute')
            ->has('imagesUploadRoute')
            ->has('galleryRoute')
            ->has('banner');
    });
})->depends('launch fulfilment website from command', 'UI store fulfilment banner');

test('UI delete banner in shop', function (Website $website) {
    $this->withoutExceptionHandling();
    $banner = StoreBanner::make()->action($website, [
        'name' => 'delete shop banner',
        'type' => BannerTypeEnum::LANDSCAPE,
        'ratio' => '16/9',
    ]);

    $response = delete(
        route('grp.models.shop.website.banner.delete', [
            $this->shop->id,
            $website->id,
            $banner->id,
        ])
    );
    $response->assertRedirect(
        route('grp.org.shops.show.web.banners.index', [
            'organisation' => $this->organisation->slug,
            'shop'         => $this->shop->slug,
            'website'      => $website->slug,
            'banner'       => $banner->slug
        ])
    );
})->depends('create b2b website');

test('UI show webpage in shop website', function (Website $website, Webpage $webpage) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.shops.show.web.webpages.show', [
            $this->organisation->slug,
            $this->shop->slug,
            $website->slug,
            $webpage->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($webpage) {
        $page
            ->component('Org/Web/Webpage')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $webpage->code)->etc()
            )
            ->has('tabs');
    });
})->depends('create b2b website', 'create webpage');

test('UI show webpage workshop in shop website', function (Website $website, Webpage $webpage) {
    $this->withoutExceptionHandling();

    $response = get(
        route('grp.org.shops.show.web.webpages.workshop', [
            $this->organisation->slug,
            $this->shop->slug,
            $website->slug,
            $webpage->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($webpage) {
        $page
            ->component('Org/Web/WebpageWorkshop')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $webpage->code)->etc()
            )
            ->has('webpage')
            ->has('webBlockTypes');
    });
})->depends('create b2b website', 'create webpage');

test('UI get section route show shop website', function (Website $website) {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.web.websites.show', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
        'website'      => $website->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_WEBSITE->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
})->depends('create b2b website');

test('UI store announcement', function (Website $website) {
    $announcement = StoreAnnouncement::make()->action($website, [
        'name' => 'test announcement',
    ]);

    $website->refresh();

    expect($announcement)->toBeInstanceOf(Announcement::class)
        ->and($announcement->name)->toBe('test announcement')
        ->and($website->webStats->number_announcements)->toBe($website->announcements()->count());

    return $announcement;
})->depends('create b2b website');

test('UI blueprint returns 404 for a non catalogue webpage', function (Website $website, Webpage $webpage) {
    $website->refresh();

    $response = get(
        route('grp.org.shops.show.web.webpages.show.blueprint.show', [
            $this->organisation->slug,
            $this->shop->slug,
            $website->slug,
            $webpage->slug,
        ])
    );

    $response->assertNotFound();
})->depends('create b2b website', 'create webpage');

test('UI smoke shop web GET routes', function (Website $website, Webpage $webpage, Redirect $redirect, \App\Models\Web\Announcement $announcement) {
    $website->refresh();
    $webpage->refresh();

    $org   = $this->organisation->slug;
    $shop  = $this->shop->slug;
    $w     = $website->slug;

    $base        = [$org, $shop, $w];
    $withWebpage = [$org, $shop, $w, $webpage->slug];

    $routes = [
        'grp.org.shops.show.web.websites.index'              => [$org, $shop],
        'grp.org.shops.show.web.websites.create'             => [$org, $shop],
        'grp.org.shops.show.web.websites.show'               => $base,
        'grp.org.shops.show.web.websites.edit'               => $base,
        'grp.org.shops.show.web.websites.workshop'           => $base,
        'grp.org.shops.show.web.websites.workshop.header'    => $base,
        'grp.org.shops.show.web.websites.workshop.footer'    => $base,
        'grp.org.shops.show.web.websites.workshop.menu'      => $base,
        'grp.org.shops.show.web.websites.workshop.sidebar'   => $base,
        'grp.org.shops.show.web.websites.workshop.preview'   => $base,
        'grp.org.shops.show.web.websites.restricted_country' => $base,
        'grp.org.shops.show.web.analytics.dashboard'         => $base,
        'grp.org.shops.show.web.analytics.visitors.index'    => $base,
        'grp.org.shops.show.web.announcements.index'         => $base,
        'grp.org.shops.show.web.announcements.create'        => $base,
        'grp.org.shops.show.web.announcements.show'          => [$org, $shop, $w, $announcement->ulid],
        'grp.org.shops.show.web.announcements.edit'          => [$org, $shop, $w, $announcement->ulid],
        'grp.org.shops.show.web.announcements.workshop'      => [$org, $shop, $w, $announcement->ulid],
        'grp.org.shops.show.web.banners.index'               => $base,
        'grp.org.shops.show.web.blogs.index'                 => $base,
        'grp.org.shops.show.web.blogs.create'                => $base,
        'grp.org.shops.show.web.crawls.index'                => $base,
        'grp.org.shops.show.web.redirect.index'              => $base,
        'grp.org.shops.show.web.redirect.export'             => $base,
        'grp.org.shops.show.web.redirect.show'               => [$org, $shop, $w, $redirect->id],
        'grp.org.shops.show.web.redirect.edit'               => [$org, $shop, $w, $redirect->id],
        'grp.org.shops.show.web.webpages.index'              => $base,
        'grp.org.shops.show.web.webpages.create'             => $base,
        'grp.org.shops.show.web.webpages.export'             => $base,
        'grp.org.shops.show.web.webpages.tree'               => $base,
        'grp.org.shops.show.web.webpages.show'               => $withWebpage,
        'grp.org.shops.show.web.webpages.edit'               => $withWebpage,
        'grp.org.shops.show.web.webpages.workshop'           => $withWebpage,
        'grp.org.shops.show.web.webpages.preview'            => $withWebpage,
        'grp.org.shops.show.web.webpages.redirect.create'    => $withWebpage,
        'grp.org.shops.show.web.webpages.show.webpages.index' => $withWebpage,
        // Excluded: webpages.show.blueprint.show only renders for ProductCategory-backed webpages
        // (it now 404s for the plain content webpage used here).
    ];

    $failures = [];
    foreach ($routes as $name => $params) {
        $status = get(route($name, $params))->status();
        if ($status >= 400) {
            $failures[] = "$name => $status";
        }
    }

    expect($failures)->toBe([]);
})->depends('launch website', 'create webpage', 'store redirect', 'UI store announcement');

test('UI smoke fulfilment web GET routes', function (Website $website) {
    $website->refresh();
    $webpage = $website->webpages()->first();

    $org = $this->organisation->slug;
    $ful = $this->fulfilment->slug;
    $w   = $website->slug;

    $base        = [$org, $ful, $w];
    $withWebpage = [$org, $ful, $w, $webpage->slug];

    $routes = [
        'grp.org.fulfilments.show.web.websites.index'             => [$org, $ful],
        'grp.org.fulfilments.show.web.websites.create'            => [$org, $ful],
        'grp.org.fulfilments.show.web.websites.show'              => $base,
        'grp.org.fulfilments.show.web.websites.edit'             => $base,
        'grp.org.fulfilments.show.web.websites.workshop'         => $base,
        'grp.org.fulfilments.show.web.websites.workshop.header'  => $base,
        'grp.org.fulfilments.show.web.websites.workshop.footer'  => $base,
        'grp.org.fulfilments.show.web.websites.workshop.menu'    => $base,
        'grp.org.fulfilments.show.web.websites.workshop.sidebar' => $base,
        'grp.org.fulfilments.show.web.websites.workshop.preview' => $base,
        'grp.org.fulfilments.show.web.websites.restricted_country' => $base,
        'grp.org.fulfilments.show.web.analytics.dashboard'       => $base,
        'grp.org.fulfilments.show.web.analytics.visitors.index'  => $base,
        'grp.org.fulfilments.show.web.banners.index'             => $base,
        'grp.org.fulfilments.show.web.crawls.index'              => $base,
        'grp.org.fulfilments.show.web.redirect.index'            => $base,
        'grp.org.fulfilments.show.web.redirect.export'           => $base,
        'grp.org.fulfilments.show.web.webpages.index'            => $base,
        'grp.org.fulfilments.show.web.webpages.create'           => $base,
        'grp.org.fulfilments.show.web.webpages.export'           => $base,
        'grp.org.fulfilments.show.web.webpages.tree'             => $base,
        'grp.org.fulfilments.show.web.webpages.show'             => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.edit'             => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.workshop'         => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.preview'          => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.redirect.create'  => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.show.webpages.index' => $withWebpage,
        'grp.org.fulfilments.show.web.webpages.index.type.content'   => $base,
        'grp.org.fulfilments.show.web.webpages.index.type.info'      => $base,
        'grp.org.fulfilments.show.web.webpages.index.type.operations' => $base,
    ];

    $failures = [];
    foreach ($routes as $name => $params) {
        $status = get(route($name, $params))->status();
        if ($status >= 400) {
            $failures[] = "$name => $status";
        }
    }

    expect($failures)->toBe([]);
})->depends('launch fulfilment website from command');

test('update announcement', function (Website $website) {
    $announcement = StoreAnnouncement::make()->action($website, ['name' => 'to update']);

    UpdateAnnouncement::make()->handle($announcement, ['name' => 'updated name']);
    $announcement->refresh();

    expect($announcement->name)->toBe('updated name')
        ->and($announcement->is_dirty)->toBeTrue();
})->depends('create b2b website');

test('delete announcement', function (Website $website) {
    $announcement = StoreAnnouncement::make()->action($website, ['name' => 'to delete']);

    DeleteAnnouncement::make()->handle($announcement);

    expect(Announcement::find($announcement->id))->toBeNull();
})->depends('create b2b website');

test('create catalogue webpages', function (Website $website) {
    createProduct($this->shop);

    $department = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->first();
    $family     = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->first();
    $product    = $this->shop->products()->first();

    $subDepartment = StoreProductCategory::make()->action($department, array_merge(
        ProductCategory::factory()->definition(),
        ['type' => ProductCategoryTypeEnum::SUB_DEPARTMENT->value]
    ));

    $departmentWebpage    = StoreProductCategoryWebpage::make()->action($department);
    $familyWebpage        = StoreProductCategoryWebpage::make()->action($family);
    $subDepartmentWebpage = StoreProductCategoryWebpage::make()->action($subDepartment);
    $productWebpage       = StoreProductWebpage::make()->action($product);

    $blogWebpage = StoreWebpage::make()->action($website, array_merge(
        Webpage::factory()->definition(),
        ['type' => WebpageTypeEnum::BLOG->value, 'sub_type' => WebpageSubTypeEnum::BLOG->value]
    ));

    expect($departmentWebpage)->toBeInstanceOf(Webpage::class)
        ->and($familyWebpage)->toBeInstanceOf(Webpage::class)
        ->and($subDepartmentWebpage)->toBeInstanceOf(Webpage::class)
        ->and($blogWebpage->type)->toBe(WebpageTypeEnum::BLOG)
        ->and($productWebpage->model_type)->toBe('Product');

    return compact('department', 'family', 'subDepartment', 'product', 'departmentWebpage', 'familyWebpage', 'subDepartmentWebpage', 'productWebpage', 'blogWebpage');
})->depends('launch website');

test('UI smoke catalogue webpage routes', function (Website $website, array $cat) {
    $website->refresh();

    $org  = $this->organisation->slug;
    $shop = $this->shop->slug;
    $w    = $website->slug;

    $base    = [$org, $shop, $w];
    $dept    = [$org, $shop, $w, $cat['departmentWebpage']->slug];
    $fam     = [$org, $shop, $w, $cat['familyWebpage']->slug];
    $subDept = [$org, $shop, $w, $cat['subDepartmentWebpage']->slug];

    $routes = [
        'grp.org.shops.show.web.webpages.index.type.catalogue'                      => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.department'                 => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.department.families_overview' => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.family'                     => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.product'                    => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.sub_department'             => $base,
        'grp.org.shops.show.web.webpages.index.sub_type.department.families'        => $dept,
        'grp.org.shops.show.web.webpages.index.sub_type.department.products'        => $dept,
        'grp.org.shops.show.web.webpages.index.sub_type.department.sub_departments' => $dept,
        'grp.org.shops.show.web.webpages.index.sub_type.family.products'            => $fam,
        'grp.org.shops.show.web.webpages.index.sub_type.sub_department.families'    => $subDept,
        'grp.org.shops.show.web.webpages.index.sub_type.sub_department.products'    => $subDept,
        'grp.org.shops.show.web.webpages.index.type.content'                        => $base,
        'grp.org.shops.show.web.webpages.index.type.info'                           => $base,
        'grp.org.shops.show.web.webpages.index.type.operations'                     => $base,
        'grp.org.shops.show.web.webpages.index.redirect-options'                    => [$org, $shop, $w, $cat['departmentWebpage']->slug],
        'grp.org.shops.show.web.blogs.create'                                       => $base,
        'grp.org.shops.show.web.webpages.show.blueprint.show'                       => $dept,
        'grp.org.shops.show.web.blogs.show'                                         => [$org, $shop, $w, $cat['blogWebpage']->slug],
        'grp.org.shops.show.web.blogs.edit'                                         => [$org, $shop, $w, $cat['blogWebpage']->slug],
        'grp.org.shops.show.web.blogs.workshop'                                     => [$org, $shop, $w, $cat['blogWebpage']->slug],
    ];

    $failures = [];
    foreach ($routes as $name => $params) {
        $status = get(route($name, $params))->status();
        if ($status >= 400) {
            $failures[] = "$name => $status";
        }
    }

    foreach (['departmentWebpage', 'familyWebpage', 'subDepartmentWebpage', 'productWebpage'] as $key) {
        foreach (['grp.org.shops.show.web.webpages.show', 'grp.org.shops.show.web.webpages.workshop'] as $name) {
            $status = get(route($name, [$org, $shop, $w, $cat[$key]->slug]))->status();
            if ($status >= 400) {
                $failures[] = "$name ($key) => $status";
            }
        }
    }

    expect($failures)->toBe([]);
})->depends('launch website', 'create catalogue webpages');

test('UI smoke iris storefront routes', function (Website $website, array $cat) {
    $website->refresh();
    DetectWebsiteFromDomain::mock()->shouldReceive('parseDomain')->andReturn($website->domain);

    $host = 'http://'.$website->domain;

    $routes = [
        '/',
        '/catalogue',
        '/catalogue/department/'.$cat['department']->slug,
        '/catalogue/family/'.$cat['family']->slug,
        '/catalogue/sub-department/'.$cat['subDepartment']->slug,
        '/catalogue/products/'.$cat['product']->slug,
        '/blog',
        '/robots.txt',
        '/llms.txt',
        '/search',
        '/warming_base.txt',
        '/warming_families.txt',
        '/warming_products.txt',
    ];

    $failures = [];
    foreach ($routes as $path) {
        $status = get($host.$path)->getStatusCode();
        if ($status >= 400) {
            $failures[] = "$path => $status";
        }
    }

    expect($failures)->toBe([]);
})->depends('launch website', 'create catalogue webpages');

test('update webpage', function (Website $website) {
    $webpage = StoreWebpage::make()->action($website->storefront, Webpage::factory()->definition());

    $updated = UpdateWebpage::make()->action($webpage, ['seo_data' => ['description' => 'updated seo']]);

    expect($updated)->toBeInstanceOf(Webpage::class)
        ->and($updated->id)->toBe($webpage->id);
})->depends('launch website');

test('store redirect from webpage', function (Webpage $webpage) {
    $path = 'legacy-'.uniqid();
    $redirect = StoreRedirectFromWebpage::make()->action($webpage, [
        'type'      => RedirectTypeEnum::PERMANENT->value,
        'from_path' => $path,
        'from_url'  => 'https://'.$webpage->website->domain.'/'.$path,
    ]);

    expect($redirect)->toBeInstanceOf(Redirect::class)
        ->and($redirect->to_webpage_id)->toBe($webpage->id)
        ->and($redirect->type)->toBe(RedirectTypeEnum::PERMANENT);
})->depends('create webpage');

test('autosave website marginals', function (Website $website) {
    request()->setUserResolver(fn () => $this->user);
    $publisher = ['publisher_id' => $this->user->id, 'publisher_type' => $this->user->getMorphClass()];

    $marginals = [
        'header', 'footer', 'menu', 'sidebar', 'department', 'sub_department',
        'family', 'families_overview', 'families_description', 'departments_description',
        'product', 'products', 'collection',
    ];

    foreach ($marginals as $marginal) {
        AutosaveWebsiteMarginal::make()->action($website, $marginal, array_merge(['layout' => ['blocks' => []]], $publisher));
    }

    $website->refresh();

    expect($website->unpublishedHeaderSnapshot->layout)->toHaveKey('header')
        ->and($website->unpublishedFooterSnapshot->layout)->toHaveKey('footer');
})->depends('launch website');

test('publish website marginal header', function (Website $website) {
    request()->setUserResolver(fn () => $this->user);
    AutosaveWebsiteMarginal::make()->action($website, 'header', ['layout' => ['blocks' => []]]);

    PublishWebsiteMarginal::make()->action($website, 'header', ['layout' => ['blocks' => []]]);

    $website->refresh();

    expect($website->liveHeaderSnapshot)->not->toBeNull();
})->depends('launch website');

test('save website sitemap', function (Website $website) {
    $count = SaveWebsiteSitemap::run($website);

    expect($count)->toBeInt()->toBeGreaterThanOrEqual(0);
})->depends('launch website');

test('robots txt is generated on demand with absolute sitemap urls', function (Website $website) {
    Storage::disk('local')->delete("robots/robots_$website->id.txt");

    $robotsTxt = ShowIrisRobotsTxt::make()->getRobotText($website);

    expect($robotsTxt)
        ->toContain('User-agent: *')
        ->toContain('Disallow: /app/interest/favourites')
        ->toContain("://www.$website->domain/sitemap.xml")
        ->toContain("://www.$website->domain/sitemaps/products.xml")
        ->not->toContain('Sitemap: /');
})->depends('launch website');

test('update webpage canonical url', function (Webpage $webpage) {
    $canonicalUrl = UpdateWebpageCanonicalUrl::run($webpage);

    expect($canonicalUrl)->toBeString();
})->depends('create webpage');

test('update webpage canonical url bans stale varnish objects', function (Webpage $webpage) {
    config()->set('iris.cache.varnish', true);
    config()->set('iris.cache.varnish_hosts', ['http://varnish.test']);
    Http::fake();

    $webpage->update(['canonical_url' => 'https://v2.stale-domain.test/old-path']);

    $canonicalUrl = UpdateWebpageCanonicalUrl::run($webpage, false);

    expect($canonicalUrl)->not->toBe('https://v2.stale-domain.test/old-path');

    Http::assertSent(fn ($request) => $request->hasHeader('x-ban-webpage', (string)$webpage->id));
    Http::assertSent(
        fn ($request) => $request->hasHeader('x-ban-host', 'v2.stale-domain.test')
            && $request->hasHeader('x-ban-url', '/old-path')
    );
    Http::assertSent(fn ($request) => $request->hasHeader('x-ban-host', parse_url($canonicalUrl, PHP_URL_HOST)));
})->depends('create webpage');

test('iris webpage renders when canonical differs only by trailing slash', function (Website $website) {
    config()->set('iris.cache.webpage_path.ttl', 0);
    config()->set('iris.cache.webpage.ttl', 0);

    $website->storefront->update(['canonical_url' => 'https://'.$website->domain.'/']);

    $request = ActionRequest::createFrom(Request::create('https://'.$website->domain));
    $request->merge(['website' => $website]);

    $result = ShowIrisWebpage::make()->handle(null, [], $request);

    expect($result)->toBeArray()
        ->and($result['status'])->toBe('ok');
})->depends('launch website');

test('iris webpage redirects to trimmed canonical url', function (Website $website) {
    config()->set('iris.cache.webpage_path.ttl', 0);
    config()->set('iris.cache.webpage.ttl', 0);

    $website->storefront->update(['canonical_url' => 'https://www.'.$website->domain.'/']);

    $request = ActionRequest::createFrom(Request::create('https://'.$website->domain));
    $request->merge(['website' => $website]);

    $result = ShowIrisWebpage::make()->handle(null, [], $request);

    expect($result)->toBe('https://www.'.$website->domain)
        ->and($request->attributes->get('iris_redirect_webpage_id'))->toBe($website->storefront->id);
})->depends('launch website');

test('process website time series records', function (Website $website) {
    ProcessWebsiteTimeSeriesRecords::run(
        $website->id,
        TimeSeriesFrequencyEnum::DAILY,
        '2026-01-01',
        '2026-01-07'
    );

    expect($website->timeSeries()->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->exists())->toBeTrue();
})->depends('launch website');

test('process webpage time series records', function (Webpage $webpage) {
    ProcessWebpageTimeSeriesRecords::run(
        $webpage->id,
        TimeSeriesFrequencyEnum::DAILY,
        '2026-01-01',
        '2026-01-07'
    );

    expect($webpage->timeSeries()->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->exists())->toBeTrue();
})->depends('create webpage');

test('publish announcement', function (Website $website) {
    $announcement = StoreAnnouncement::make()->action($website, ['name' => 'to publish']);
    UpdateAnnouncement::make()->handle($announcement, ['fields' => ['title' => 'hi']]);
    $announcement->refresh();

    request()->setUserResolver(fn () => $this->user);
    PublishAnnouncement::make()->handle($announcement, [
        'text'                 => 'hello world',
        'container_properties' => [],
    ]);
    $announcement->refresh();

    expect($announcement->live_snapshot_id)->not->toBeNull();
})->depends('create b2b website');

// Cloudflare: mutate website slugs, so keep last to avoid stale slugs in UI tests above

it('correctly picks zone kind ruleset when multiple exist', function () {

    $website = Website::first();

    $website->update([
        'slug'               => 'ua',
        'cloudflare_zone_id' => 'zone_123',
        'cloudflare_token'   => encrypt('fake_token'),
    ]);

    // Mock Cloudflare API
    Http::fake([
        'https://api.cloudflare.com/client/v4/zones/zone_123/rulesets?phase=http_request_firewall_custom' => Http::response([
            'result' => [
                [
                    'id'   => 'managed_ruleset_id',
                    'kind' => 'managed',
                ],
                [
                    'id'   => 'zone_ruleset_id',
                    'kind' => 'zone',
                ],
            ],
        ]),
        'https://api.cloudflare.com/client/v4/zones/zone_123/rulesets/zone_ruleset_id' => Http::response([
            'result' => [
                'id'    => 'zone_ruleset_id',
                'rules' => [],
            ],
        ]),
        'https://api.cloudflare.com/client/v4/zones/zone_123/rulesets/zone_ruleset_id' => Http::response([
            'result' => [
                'id' => 'zone_ruleset_id',
            ],
        ]),
    ]);

    $result = BlockCountriesInCloudflare::run($website, ['UA', 'RU']);

    expect($result['result']['id'])->toBe('zone_ruleset_id');

    // Assert that the PUT request was made to the correct ruleset ID
    Http::assertSent(function ($request) {
        return $request->method() === 'PUT' &&
            $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone_123/rulesets/zone_ruleset_id' &&
            count($request['rules']) === 1 &&
            $request['rules'][0]['expression'] === '(ip.src.country in {"UA" "RU"})';
    });
});

it('creates ruleset if none of zone kind exists', function () {

    $website = Website::first();

    $website->update([
        'slug'               => 'ua-new',
        'cloudflare_zone_id' => 'zone_456',
        'cloudflare_token'   => encrypt('fake_token'),
    ]);

    // Mock Cloudflare API
    Http::fake([
        'https://api.cloudflare.com/client/v4/zones/zone_456/rulesets?phase=http_request_firewall_custom' => Http::response([
            'result' => [
                [
                    'id'   => 'managed_ruleset_id',
                    'kind' => 'managed',
                ],
            ],
        ]),
        'https://api.cloudflare.com/client/v4/zones/zone_456/rulesets' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'result' => [
                        'id'   => 'new_zone_ruleset_id',
                        'kind' => 'zone',
                    ],
                ]);
            }
            return Http::response([], 404);
        },
        'https://api.cloudflare.com/client/v4/zones/zone_456/rulesets/new_zone_ruleset_id' => Http::response([
            'result' => [
                'id'    => 'new_zone_ruleset_id',
                'rules' => [],
            ],
        ]),
        'https://api.cloudflare.com/client/v4/zones/zone_456/rulesets/new_zone_ruleset_id' => Http::response([
            'result' => [
                'id' => 'new_zone_ruleset_id',
            ],
        ]),
    ]);

    $result = BlockCountriesInCloudflare::run($website, ['UA']);

    expect($result['result']['id'])->toBe('new_zone_ruleset_id');
});
