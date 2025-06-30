<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 15:08:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Web\Banner\StoreBanner;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Website\LaunchWebsite;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Enums\UI\Web\WebsiteTabsEnum;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Web\Banner;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});
beforeEach(function () {
    $web = Website::first();
    if (!$web) {
        list(
            $this->organisation,
            $this->user,
            $this->shop
            ) = createShop();
        $web = createWebsite($this->shop);
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
        $this->shop         = $web->shop;
    }
    $web->refresh();
    $this->web       = $web;
    $this->warehouse = createWarehouse();

    if ($this->web->shop->fulfilment) {
        $this->fulfilment        = $this->web->shop->fulfilment;
        $this->fulfilmentWebsite = $this->web;
    } else {
        $this->fulfilment        = createFulfilment($this->organisation);
        $this->fulfilmentWebsite = createWebsite($this->fulfilment->shop);
    }

    if ($this->fulfilmentWebsite->state == WebsiteStateEnum::IN_PROCESS) {
        LaunchWebsite::make()->action($this->fulfilmentWebsite);
    }

    $banner = Banner::first();

    if (!$banner) {
        $banner = StoreBanner::make()->action($this->fulfilmentWebsite, [
            'name' => 'fulfilmentBanner',
            'type' => BannerTypeEnum::LANDSCAPE,
        ]);
        $banner->refresh();
    }
    $this->banner = $banner;

    $webpage = Webpage::first();
    if (!$webpage) {
        $webpage = StoreWebpage::make()->action($this->web->storefront, Webpage::factory()->definition());
    }
    $this->webpage = $webpage;

    $this->user->refresh();
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->user);
});


test('can show fulfilment website', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.show',
            [
                $this->organisation->slug,
                $this->fulfilment,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has('breadcrumbs', 2);
    });
});

test('can show fulfilment website (tab showcase)', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.show',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug,
                'tab' => WebsiteTabsEnum::SHOWCASE->value
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has(
                "tabs",
                fn(AssertableInertia $page) => $page->where("current", WebsiteTabsEnum::SHOWCASE->value)->etc()
            )
            ->has(WebsiteTabsEnum::SHOWCASE->value)
            ->has('breadcrumbs', 2);
    });
});

test('can show fulfilment website (tab external links)', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.show',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug,
                'tab' => WebsiteTabsEnum::EXTERNAL_LINKS->value
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has(
                "tabs",
                fn(AssertableInertia $page) => $page->where("current", WebsiteTabsEnum::EXTERNAL_LINKS->value)->etc()
            )
            ->has(WebsiteTabsEnum::EXTERNAL_LINKS->value)
            ->has('breadcrumbs', 2);
    });
});

test('can show webpages list in fulfilment website', function () {
    $website  = $this->fulfilmentWebsite;
    $response = get(
        route(
            'grp.org.fulfilments.show.web.webpages.index',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Webpages')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data.data', 9);
    });
});

test('can show fulfilments website workshop', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.workshop',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/WebsiteWorkshop')
            ->where('title', "Website's workshop")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", "Workshop")->etc()
            )
            ->has('breadcrumbs', 2)
            ->has('tabs');
    });
});

test('can show fulfilments website workshop (header)', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.workshop.header',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/Header/HeaderWorkshop')
            ->where('title', "Website Header's Workshop")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", "Header's Workshop")->etc()
            )
            ->has('breadcrumbs', 0)
            ->has('uploadImageRoute')
            ->has('autosaveRoute')
            ->has('route_list')
            ->has('data')
            ->has('web_block_types');
    });
});

test('can show fulfilments website workshop (footer)', function () {
    $website = $this->fulfilmentWebsite;

    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.workshop.footer',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $website->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) use ($website) {
        $page
            ->component('Org/Web/Workshop/Footer/FooterWorkshop')
            ->where('title', "footer")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", $website->code)->etc()
            )
            ->has('breadcrumbs', 0)
            ->has('uploadImageRoute')
            ->has('autosaveRoute')
            ->has('data')
            ->has('webBlockTypes');
    });
});


test('UI index websites in organisation', function () {
    $response = get(
        route(
            'grp.org.websites.index',
            [
                $this->organisation->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Websites')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", "websites")->etc()
            )
            ->has('data');
    });
});

test('index fulfilment banner', function () {
    $response = get(
        route(
            'grp.org.fulfilments.show.web.banners.index',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $this->fulfilmentWebsite->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Banners/Banners')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", "banners")->etc()
            )
            ->has('data');
    });
});

test('show fulfilment banner', function () {
    $response = get(
        route(
            'grp.org.fulfilments.show.web.banners.show',
            [
                $this->organisation->slug,
                $this->fulfilment,
                $this->fulfilmentWebsite->slug,
                $this->banner->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Banners/Banner')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", $this->banner->name)->etc()
            )
            ->has('tabs');
    });
});

test('edit fulfilment banner', function () {
    $this->withoutExceptionHandling();
    $oldState = $this->banner->state;
    if ($this->banner != BannerStateEnum::LIVE->value) {
        $this->banner->update([
            'state' => BannerStateEnum::LIVE->value
        ]);
    }
    $response = get(
        route(
            'grp.org.fulfilments.show.web.banners.edit',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $this->fulfilmentWebsite->slug,
                $this->banner->slug,
                'section' => 'properties'
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", $this->banner->name)->etc()
            )
            ->has('formData');
    });

    $this->banner->update([
        'state' => $oldState
    ]);
});

test('delete banner in shop', function () {
    $this->withoutExceptionHandling();
    $banner = StoreBanner::make()->action($this->shop->website, [
        'name' => 'delete shop banner',
        'type' => BannerTypeEnum::LANDSCAPE,
    ]);

    $response = delete(
        route(
            'grp.models.shop.website.banner.delete',
            [
                $this->shop->id,
                $this->shop->website->id,
                $banner->id,
            ]
        )
    );
    $response->assertRedirect(
        route(
            'grp.org.shops.show.web.banners.index',
            [
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
                'website'      => $this->shop->website->slug,
                'banner'       => $banner->slug
            ]
        )
    );
});


test('show fulfilment banner workshop', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.fulfilments.show.web.banners.workshop',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $this->fulfilmentWebsite->slug,
                $this->banner->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Banners/BannerWorkshop')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 1)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", 'Workshop')->etc()
            )
            ->has('publishRoute')
            ->has('imagesUploadRoute')
            ->has('galleryRoute')
            ->has('banner');
    });
});

test('show fulfilment edit website', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.edit',
            [
                $this->organisation->slug,
                $this->fulfilment->slug,
                $this->fulfilmentWebsite->slug,
                $this->banner->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('navigation')
            ->has('breadcrumbs', 2)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", 'Settings')->etc()
            )
            ->has('formData');
    });
});

test('create banner', function () {
    $response = get(
        route(
            'grp.org.shops.show.web.banners.create',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $this->fulfilmentWebsite->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'new banner')
            ->has('breadcrumbs', 4)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", 'banner')->etc()
            )
            ->has('formData');
    });
});

test('web website workshop menu', function () {
    $response = get(
        route(
            'grp.org.shops.show.web.websites.workshop.menu',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $this->fulfilmentWebsite->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Workshop/Menu/MenuWorkshop')
            ->where('title', "Website Menu's Workshop")
            ->has('breadcrumbs')
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", "Menu's Workshop")->etc()
            )
            ->has('autosaveRoute')
            ->has('data')
            ->has('webBlockTypes')
            ->has('uploadImageRoute');
    });
});

test('can show webpages in shop website', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.shops.show.web.webpages.show',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $this->web->slug,
                $this->webpage->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Webpage')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", $this->webpage->code)->etc()
            )
            ->has('tabs');
    });
});

test('can show workshop webpages in shop website', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.shops.show.web.webpages.workshop',
            [
                $this->organisation->slug,
                $this->shop->slug,
                $this->web->slug,
                $this->webpage->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/WebpageWorkshop')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) => $page->where("title", $this->webpage->code)->etc()
            )
            ->has('webpage')
            ->has('webBlockTypes');
    });
});

test('UI get section route show shop website', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.web.websites.show', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
        'website'      => $this->web->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_WEBSITE->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});


test('ui index redirects in website', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.show',
            [
                'organisation' => $this->organisation->slug,
                'fulfilment'   => $this->fulfilment->slug,
                'website'      => $this->fulfilmentWebsite,
                'tab'          => WebsiteTabsEnum::REDIRECTS->value
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Website')
            ->has('title')
            ->has(
                "tabs",
                fn(AssertableInertia $page) => $page->where("current", WebsiteTabsEnum::REDIRECTS->value)->etc()
            )
            ->has('breadcrumbs', 2);
    });
});

test('ui index redirects in webpage', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.org.shops.show.web.webpages.show',
            [
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
                'website'      => $this->web->slug,
                'webpage'      => $this->webpage->slug,
                'tab'          => WebsiteTabsEnum::REDIRECTS->value
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Webpage')
            ->has('title')
            ->has(
                "tabs",
                fn(AssertableInertia $page) => $page->where("current", WebpageTabsEnum::REDIRECTS->value)->etc()
            )
            ->has('breadcrumbs', 3);
    });
});
