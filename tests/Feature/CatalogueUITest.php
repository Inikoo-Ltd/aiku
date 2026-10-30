<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:17:45 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\ProductCategory\GetDepartmentTimeSeriesStats;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\SalesAnalysis\GetSalesAnalysis;
use App\Actions\Catalogue\SalesAnalysis\SalesAnalysisScope;
use App\Actions\Catalogue\ProductCategory\GetSubDepartmentTimeSeriesStats;
use App\Actions\Catalogue\Shop\SalesTarget\ForecastShopSales;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopMonthSalesTarget;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopYearSalesTarget;
use App\Actions\CRM\Customer\GetShopCustomersDashboard;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomersDashboard;
use App\Actions\Catalogue\Shop\SalesTarget\UpdateShopSalesTarget;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Catalogue\Shop\SalesTarget\GenerateSalesTargetTips;
use App\Actions\Helpers\AI\AskToAi;
use App\Models\Catalogue\SalesTargetTip;
use App\Actions\Accounting\InvoiceCategory\StoreInvoiceCategory;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\UI\Dashboards\GetGroupWarehouseDashboardData;
use App\Actions\UI\Dashboards\GetOperationsDashboardData;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Inventory\Warehouse\SeedWarehousePermissions;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\Catalogue\Shop\UI\GetCatalogueShowcase;
use App\Actions\UI\Grp\Layout\GetShopNavigation;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Dashboards\ShopDashboardSalesTableTabsEnum;
use App\Enums\Dashboards\ShopDashboardSectionsEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Helpers\Audit;
use App\Models\Web\SeoContentSuggestion;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Actions\Catalogue\ProductCategory\UI\GetFamilyPageScore;
use App\Actions\Web\Seo\AskWebmastersToImplementSeoReview;
use App\Actions\Web\Seo\StoreProductSeoReview;
use App\Actions\Web\Seo\ValidateProductSeoReview;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Actions\Catalogue\Product\UI\GetProductSeoReview;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Events\BroadcastStaffTaskBadgeUpdate;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Arr;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Billables\Charge;
use App\Models\Billables\Service;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\Catalogue\ShopTimeSeries;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user         = createAdminGuest($this->group)->getUser();


    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->department = $this->product->department;
    $this->family     = $this->product->family;


    $subDepartment = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->first();
    if (!$subDepartment) {
        $subDepartmentData = ProductCategory::factory()->definition();
        data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
        $subDepartment = StoreProductCategory::make()->action(
            $this->department,
            $subDepartmentData
        );
    }
    $this->subDepartment = $subDepartment;

    /** @var Collection $collection */
    $collection = Collection::first();
    if (!$collection) {
        data_set($storeData, 'code', 'Test');
        data_set($storeData, 'name', 'Test Name');

        $collection = StoreCollection::make()->action(
            $this->shop,
            $storeData
        );
    }
    $this->collectionModel = $collection;

    $charge = Charge::first();
    if (!$charge) {
        $charge = StoreCharge::make()->action(
            $this->shop,
            [
                'code'        => 'MyFColl',
                'name'        => 'My first charge',
                'type'        => ChargeTypeEnum::HANGING,
                'trigger'     => ChargeTriggerEnum::ORDER,
                'description' => 'Charge description',
                'price'       => fake()->numberBetween(100, 2000),
                'unit'        => 'charge',
            ]
        );
        $this->shop->refresh();
    }
    $this->charge = $charge;

    $service = Service::first();
    if (!$service) {
        $service = StoreService::make()->action(
            $this->shop,
            [
                'code'  => 'MySvc',
                'name'  => 'My first service',
                'price' => fake()->numberBetween(100, 2000),
                'unit'  => 'service',
                'state' => ServiceStateEnum::ACTIVE,
            ]
        );
        $this->shop->refresh();
    }
    $this->service = $service;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    $this->user->refresh();
    actingAs($this->user);
});



test('UI Index catalogue departments', function () {
    $this->withoutExceptionHandling();

    $response = get(route('grp.org.shops.show.catalogue.departments.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Departments')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI show department', function () {
    $this->withoutExceptionHandling();


    $response = get(route('grp.org.shops.show.catalogue.departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Department')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->product->department->name)
                    ->etc()
            )
            ->has('tabs')
            ->missing('tabs.navigation.related_categories');
    });
});

test('UI show department sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        'tab'         => DepartmentTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Department')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->department));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI create department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.create', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI edit department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.product_category.update')
                    ->where('parameters', [
                        'productCategory' => $this->department->id
                    ])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI Index catalogue family inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create catalogue family inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.create', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show family in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Family')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->family->name)
                    ->etc()
            )
            ->has('tabs');
    });
});


test('UI show family attachments tab lists trade unit documents read only', function () {
    get(route('grp.org.shops.show.catalogue.departments.show.families.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Family')
            ->has('attachments.documents'));
});

test('UI show family edit page tab lays out the webpage and content fields with their seo checks', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['title' => 'Wholesale <b>Aroma</b> Diffusers', 'seo_data' => ['structured_data' => ['@context' => 'https://schema.org', '@type' => 'FAQPage']]]);

    SeoTrackedKeyword::create([
        'shop_id'           => $this->shop->id,
        'keyword'           => 'aroma diffusers',
        'country_code'      => 'GB',
        'language_code'     => 'en',
        'device'            => SeoKeywordDeviceEnum::DESKTOP,
        'frequency'         => SeoKeywordFrequencyEnum::WEEKLY,
        'target_webpage_id' => $webpage->id,
        'is_active'         => true,
    ]);

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Family')
            ->where('tabs.current', FamilyTabsEnum::EDIT_PAGE->value)
            ->has('tabs.navigation.edit_page')
            ->where('edit_page.notice', null)
            ->where('edit_page.workshop_route.parameters.webpage', $webpage->slug)
            ->where('edit_page.sections.0.title', 'Targeted keywords')
            ->where('edit_page.sections.0.cards.0.keywords', ['aroma diffusers'])
            ->where('edit_page.sections.1.cards.0.update_route.name', 'grp.models.webpage.update')
            ->where('edit_page.sections.1.cards.0.fields.title.value', 'Wholesale <b>Aroma</b> Diffusers')
            ->where('edit_page.sections.1.cards.0.check', ['ok' => true, 'note' => 'Filled'])
            ->where('edit_page.sections.1.cards.1.fields.webpage_title_prefix.placeholder', data_get($webpage->website->settings, 'webpage.title_prefix'))
            ->where('edit_page.sections.1.cards.1.fields.webpage_title_suffix.placeholder', data_get($webpage->website->settings, 'webpage.title_suffix'))
            ->where('edit_page.sections.1.cards.2.fields.description.type', 'textarea')
            ->where('edit_page.sections.1.cards.3.update_route.name', 'grp.models.webpage.update')
            ->where('edit_page.sections.1.cards.3.fields.seo_image.type', 'image_crop_square')
            ->where('edit_page.sections.1.cards.3.fields.seo_image.hasOther.1.name', 'seo_image_url')
            ->where('edit_page.sections.1.cards.6.update_route.name', 'grp.models.product_category.update')
            ->has('edit_page.sections.1.cards.6.fields.description_title')
            ->where('edit_page.sections.1.cards.7.fields.description.toggle', fn ($toggle) => collect(['heading1', 'heading2', 'heading3'])->diff($toggle)->isEmpty())
            ->has('edit_page.page_score.steps', 4)
            ->where('edit_page.preview.title', 'Wholesale <b>Aroma</b> Diffusers')
            ->where('edit_page.preview.url', $webpage->fresh()->url)
            ->where('edit_page.preview.domain', $webpage->website->domain)
            ->has('edit_page.preview.share_image')
            ->has('edit_page.seo_issues.groups')
            ->where('edit_page.seo_review.checks.title', ['ok' => true, 'note' => 'Contains keyword'])
            ->where('edit_page.seo_review.structured_data', fn (string $json) => json_decode($json, true)['@type'] === 'FAQPage')
            ->where('edit_page.structured_data_source.website_name', $webpage->website->name)
            ->has('edit_page.structured_data_source.web_blocks')
            ->has('edit_page.structured_data_source.breadcrumbs')
            ->etc());

    $webpage->update(['seo_data' => ['structured_data' => ['']]]);

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('edit_page.seo_review.structured_data', '')->etc());
});

test('UI show family edit page translates the meta description from the master family', function () {
    createWebsite($this->shop);

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'code' => 'MS-'.uniqid(),
        'name' => 'Master Shop',
        'type' => ShopTypeEnum::B2B,
    ]);
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MD-'.uniqid(),
        'name' => 'Master Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MF-'.uniqid(),
        'name' => 'Master Family',
    ]);
    $masterFamily->updateQuietly(['meta_description' => 'Wholesale aroma diffusers for every room']);

    $family = $this->family->refresh();
    $family->updateQuietly(['master_product_category_id' => $masterFamily->id]);
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->updateQuietly(['description' => 'Aroma-Diffusoren im Großhandel']);

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Family')
            ->where('edit_page.sections.1.cards.2.update_route.name', 'grp.models.webpage.update')
            ->where('edit_page.sections.1.cards.2.fields.description.type', 'input_translation')
            ->where('edit_page.sections.1.cards.2.fields.description.textarea', true)
            ->where('edit_page.sections.1.cards.2.fields.description.main', 'Wholesale aroma diffusers for every room')
            ->where('edit_page.sections.1.cards.2.fields.description.value', 'Aroma-Diffusoren im Großhandel')
            ->etc());

    $family->updateQuietly(['master_product_category_id' => null]);
});

test('SEO team sends a family page review that replaces the last one and moves the workflow on', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $family->update(['faq' => [['question' => 'Do they need water?', 'answer' => 'Most do.']]]);

    $reviewRoute = route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]);
    $pending     = fn () => SeoContentSuggestion::where('webpage_id', $webpage->id)
        ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
        ->where('state', SeoContentSuggestionStateEnum::PENDING)
        ->get()
        ->keyBy('field');
    $targeted    = fn () => SeoTrackedKeyword::where('target_webpage_id', $webpage->id)->distinct()->orderBy('keyword')->pluck('keyword')->all();

    post($reviewRoute, ['suggestions' => ['not_a_field' => 'x']])->assertSessionHasErrors('suggestions');

    post($reviewRoute, [
        'keywords'            => 'Aroma Diffuser,  essential oil nebuliser ,aroma diffuser',
        'suggestions'         => ['title' => 'Electric Aroma Diffusers | Wholesale', 'desc_title' => '', 'top_desc' => 'Better copy', 'extra_desc' => '<p></p>'],
        'faq'                 => [
            'items' => [['question' => 'Do all aroma diffusers need water?', 'answer' => '', 'remove' => false]],
            'new'   => [['question' => 'How do I clean one?', 'answer' => 'Unplug it first.'], ['question' => '', 'answer' => 'Dropped without a question']],
        ],
        'structured_data'     => [['json_ld' => '{"@context":"https://schema.org","@type":"FAQPage"}', 'why' => 'Mark up the FAQ'], ['json_ld' => '', 'why' => '']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $suggestions = $pending();
    $audits      = fn (string $event, $ids) => Audit::where('auditable_type', 'SeoContentSuggestion')->whereIn('auditable_id', $ids)->where('event', $event)->get();
    expect($suggestions->keys()->sort()->values()->all())->toBe(['faq', 'title', 'top_desc'])
        ->and($audits('created', $suggestions->pluck('id'))->pluck('user_id')->unique()->all())->toBe([$this->user->id])
        ->and(json_decode($suggestions['faq']->suggestion, true)['new'])->toBe([['question' => 'How do I clean one?', 'answer' => 'Unplug it first.']])
        ->and(collect(json_decode($webpage->refresh()->structuredDataJson(), true))->pluck('@type')->all())->toBe(['FAQPage'])
        ->and($targeted())->toBe(['aroma diffuser', 'essential oil nebuliser'])
        ->and(collect(GetFamilyPageScore::run($family->refresh())['steps'])->firstWhere('key', 'seo_review')['is_done'])->toBeTrue();

    post($reviewRoute, ['keywords' => 'aroma diffuser', 'suggestions' => ['url' => 'electric-aroma-diffusers']])->assertRedirect();

    expect($pending()->keys()->all())->toBe(['url'])
        ->and($targeted())->toBe(['aroma diffuser'])
        ->and($audits('deleted', $suggestions->pluck('id')))->toHaveCount(3);

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('edit_page.seo_review.sent_count', 1)
            ->where('edit_page.seo_review.keywords', 'aroma diffuser')
            ->where('edit_page.seo_review.fields.2.key', 'url')
            ->where('edit_page.seo_review.fields.2.suggestion', 'electric-aroma-diffusers')
            ->etc());

    $urlSuggestion = $pending()['url'];
    patch(route('grp.models.seo_content_suggestion.dismiss', ['seoContentSuggestion' => $urlSuggestion->id]))->assertSessionHasNoErrors();

    expect($audits('updated', [$urlSuggestion->id])->map(fn (Audit $audit) => [$audit->new_values['state'] ?? null, $audit->user_id])->all())
        ->toBe([[SeoContentSuggestionStateEnum::DISMISSED->value, $this->user->id]]);
});

test('webmaster implements the SEO review one suggestion at a time, keeps what was already pasted, then validates it', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['title' => 'Aroma Diffusers', 'breadcrumb_label' => 'Diffusers']);
    $family->update(['faq' => [['question' => 'Do they need water?', 'answer' => 'Most do.'], ['question' => 'Old question', 'answer' => 'Old answer']]]);

    post(route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]), [
        'suggestions' => ['title' => 'Electric Aroma Diffusers | Wholesale', 'breadcrumb_label' => 'Aroma diffusers', 'top_desc' => 'Better copy'],
        'faq'         => [
            'items' => [['question' => '', 'answer' => 'Most models do.', 'remove' => false], ['question' => '', 'answer' => '', 'remove' => true]],
            'new'   => [['question' => 'How do I clean one?', 'answer' => 'Unplug it first.']],
        ],
    ])->assertSessionHasNoErrors();

    $webpage->update(['breadcrumb_label' => 'Aroma diffusers range']);

    $suggestions = SeoContentSuggestion::where('webpage_id', $webpage->id)
        ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
        ->where('state', SeoContentSuggestionStateEnum::PENDING)
        ->get()
        ->keyBy('field');
    $implement   = fn (string $field, array $data = []) => patch(route('grp.models.product_category.seo_review.implement', ['productCategory' => $family->id, 'seoContentSuggestion' => $suggestions[$field]->id]), $data);
    $validate    = fn () => post(route('grp.models.product_category.seo_review.validate', ['productCategory' => $family->id]));
    $editPage    = route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]);

    get($editPage)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('edit_page.sections.1.cards.0.key', 'title')
        ->where('edit_page.webmaster_validation.tasks', fn ($tasks) => collect($tasks)->pluck('key')->all() === ['title', 'breadcrumb_label', 'top_desc', 'faq-item-0', 'faq-item-1', 'faq-new-0']
            && !collect($tasks)->contains('is_done', true))
        ->etc());

    $implement('title')->assertSessionHasNoErrors();
    $implement('breadcrumb_label')->assertSessionHasNoErrors();
    $implement('top_desc')->assertSessionHasNoErrors();
    $implement('faq')->assertSessionHasErrors('suggestion');
    $implement('faq', ['item' => 0])->assertSessionHasNoErrors();
    $implement('faq', ['item' => 1])->assertSessionHasNoErrors();

    expect($suggestions['faq']->refresh()->state)->toBe(SeoContentSuggestionStateEnum::PENDING);

    $implement('faq', ['new' => 0])->assertSessionHasNoErrors();
    $implement('title')->assertSessionHasErrors('suggestion');

    $webpage->refresh();
    $family->refresh();

    expect($webpage->title)->toBe('Electric Aroma Diffusers | Wholesale')
        ->and($webpage->breadcrumb_label)->toBe('Aroma diffusers range')
        ->and($family->description)->toBe('Better copy')
        ->and(collect($family->faq)->map(fn (array $item) => Arr::only($item, ['question', 'answer']))->all())->toEqual([
            ['question' => 'Do they need water?', 'answer' => 'Most models do.'],
            ['question' => 'How do I clean one?', 'answer' => 'Unplug it first.'],
        ])
        ->and($suggestions['faq']->refresh()->state)->toBe(SeoContentSuggestionStateEnum::IMPLEMENTED)
        ->and(collect(GetFamilyPageScore::run($family)['steps'])->firstWhere('key', 'implemented')['is_done'])->toBeFalse();

    $this->travel(4)->seconds();
    $validate()->assertSessionHasNoErrors();

    expect(SeoContentSuggestion::whereIn('id', $suggestions->pluck('id'))->pluck('state')->unique()->values()->all())->toBe([SeoContentSuggestionStateEnum::ACCEPTED])
        ->and($suggestions['title']->audits()->orderBy('id')->get()->map(fn (Audit $audit) => [$audit->event, $audit->new_values['state'] ?? null, $audit->user_id])->all())->toBe([
            ['created', SeoContentSuggestionStateEnum::PENDING->value, $this->user->id],
            ['updated', SeoContentSuggestionStateEnum::IMPLEMENTED->value, $this->user->id],
            ['updated', SeoContentSuggestionStateEnum::ACCEPTED->value, $this->user->id],
        ]);

    get($editPage)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('edit_page.webmaster_validation.is_validated', true)
        ->has('edit_page.webmaster_validation.tasks', 0)
        ->where('edit_page.webmaster_validation.activity', fn ($activity) => collect($activity)->contains('text', 'Validated the SEO suggestions.'))
        ->etc());
});

test('the SEO team adds several structured data items straight to the list of custom JSON-LD the family page already has, without webmaster validation', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['seo_data' => ['structured_data' => '{"@context":"https://schema.org","@type":"Organization","name":"AW"}']]);

    post(route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]), [
        'structured_data' => [
            ['json_ld' => '{"@context":"https://schema.org","@graph":[{"@type":"FAQPage"},{"@type":"VideoObject"}]}'],
            ['json_ld' => '{"@context":"https://schema.org","@type":"Organization","name":"AW"}'],
            ['json_ld' => '{"@context":"https://schema.org","@type":"HowTo"}'],
        ],
    ])->assertSessionHasNoErrors();

    $nodes = json_decode($webpage->refresh()->structuredDataJson(), true);

    expect(array_is_list($nodes))->toBeTrue()
        ->and(collect($nodes)->pluck('@type')->all())->toBe(['Organization', 'FAQPage', 'VideoObject', 'HowTo'])
        ->and(collect($nodes)->pluck('@context')->unique()->all())->toBe(['https://schema.org'])
        ->and(SeoContentSuggestion::where('webpage_id', $webpage->id)->where('field', 'structured_data')->exists())->toBeFalse();

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('edit_page.sections', fn ($sections) => collect($sections)->pluck('title')->doesntContain('Structured data (JSON-LD)'))
            ->where('edit_page.seo_review.structured_data_suggestions', [])
            ->etc());
});

test('webmaster edits SEO suggestions in the validation tab and the edited values are applied', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['title' => 'Aroma Diffusers']);
    $family->update(['faq' => []]);

    post(route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]), [
        'suggestions' => ['title' => 'Electric Aroma Diffusers'],
        'faq'         => ['new' => [['question' => 'How do I clean one?', 'answer' => 'Unplug it first.']]],
    ])->assertSessionHasNoErrors();

    $suggestions = SeoContentSuggestion::where('webpage_id', $webpage->id)->where('state', SeoContentSuggestionStateEnum::PENDING)->get()->keyBy('field');
    $implement   = fn (string $field, array $data) => patch(route('grp.models.product_category.seo_review.implement', ['productCategory' => $family->id, 'seoContentSuggestion' => $suggestions[$field]->id]), $data);

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('edit_page.webmaster_validation.tasks', fn ($tasks) => collect($tasks)->map(fn ($task) => collect($task['rows'])->pluck('input')->all())->all() === [['value'], ['question', 'answer']])
            ->etc());

    $implement('title', ['value' => 'Electric Aroma Diffusers | Wholesale'])->assertSessionHasNoErrors();
    $implement('faq', ['new' => 0, 'question' => 'How do I clean a diffuser?', 'answer' => 'Unplug it first.'])->assertSessionHasNoErrors();

    expect($webpage->refresh()->title)->toBe('Electric Aroma Diffusers | Wholesale')
        ->and($family->refresh()->faq)->toBe([['question' => 'How do I clean a diffuser?', 'answer' => 'Unplug it first.']])
        ->and($suggestions['title']->refresh()->state)->toBe(SeoContentSuggestionStateEnum::IMPLEMENTED);

    $product = $this->product->refresh();
    $productWebpage = $product->webpage ?? StoreProductWebpage::make()->action($product);

    post(route('grp.models.product.seo_review.store', ['product' => $product->id]), ['suggestions' => ['breadcrumb_label' => 'Diffuser']])->assertSessionHasNoErrors();

    $productSuggestion = SeoContentSuggestion::where('webpage_id', $productWebpage->id)->where('field', 'breadcrumb_label')->where('state', SeoContentSuggestionStateEnum::PENDING)->firstOrFail();
    patch(route('grp.models.product.seo_review.implement', ['product' => $product->id, 'seoContentSuggestion' => $productSuggestion->id]), ['value' => 'Aroma diffuser'])->assertSessionHasNoErrors();

    expect($productWebpage->refresh()->breadcrumb_label)->toBe('Aroma diffuser');
});

test('saving structured data on its own adds it to the page and keeps the suggestions still waiting for the webmaster', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['seo_data' => ['structured_data' => null]]);
    $reviewRoute = route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]);

    post($reviewRoute, ['suggestions' => ['title' => 'Electric Aroma Diffusers']])->assertSessionHasNoErrors();
    post($reviewRoute, ['structured_data' => [['json_ld' => '{"@context":"https://schema.org","@type":"HowTo"}']]])->assertSessionHasNoErrors();

    expect(collect(json_decode($webpage->refresh()->structuredDataJson(), true))->pluck('@type')->all())->toBe(['HowTo'])
        ->and(SeoContentSuggestion::where('webpage_id', $webpage->id)->where('state', SeoContentSuggestionStateEnum::PENDING)->pluck('field')->all())->toBe(['title']);

    $product        = $this->product->refresh();
    $productWebpage = $product->webpage ?? StoreProductWebpage::make()->action($product);
    $productWebpage->update(['seo_data' => ['structured_data' => null]]);
    $productRoute = route('grp.models.product.seo_review.store', ['product' => $product->id]);

    post($productRoute, ['suggestions' => ['breadcrumb_label' => 'Diffuser']])->assertSessionHasNoErrors();
    post($productRoute, ['structured_data' => [['json_ld' => '{"@context":"https://schema.org","@type":"VideoObject"}']]])->assertSessionHasNoErrors();

    expect(collect(json_decode($productWebpage->refresh()->structuredDataJson(), true))->pluck('@type')->all())->toBe(['VideoObject'])
        ->and(SeoContentSuggestion::where('webpage_id', $productWebpage->id)->where('state', SeoContentSuggestionStateEnum::PENDING)->pluck('field')->all())->toBe(['breadcrumb_label']);
});

test('webmaster validates a family SEO review with suggestions still pending, which dismisses them and leaves those fields as they were', function () {
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $webpage->update(['title' => 'Aroma Diffusers']);
    $family->update(['description' => 'Old copy']);

    post(route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]), [
        'suggestions' => ['title' => 'Electric Aroma Diffusers', 'top_desc' => 'Better copy'],
    ])->assertSessionHasNoErrors();

    $suggestions = SeoContentSuggestion::where('webpage_id', $webpage->id)
        ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
        ->where('state', SeoContentSuggestionStateEnum::PENDING)
        ->get()
        ->keyBy('field');

    patch(route('grp.models.product_category.seo_review.implement', ['productCategory' => $family->id, 'seoContentSuggestion' => $suggestions['title']->id]))->assertSessionHasNoErrors();
    post(route('grp.models.product_category.seo_review.validate', ['productCategory' => $family->id]))->assertSessionHasNoErrors();

    expect($suggestions['title']->refresh()->state)->toBe(SeoContentSuggestionStateEnum::ACCEPTED)
        ->and($suggestions['top_desc']->refresh())
        ->state->toBe(SeoContentSuggestionStateEnum::DISMISSED)
        ->decided_by_user_id->toBe($this->user->id)
        ->and($family->refresh()->description)->toBe('Old copy');

    get(route('grp.org.shops.show.catalogue.families.show', [$this->organisation->slug, $this->shop->slug, $family->slug, 'tab' => FamilyTabsEnum::EDIT_PAGE->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('edit_page.webmaster_validation.is_validated', true)
            ->has('edit_page.webmaster_validation.tasks', 0)
            ->etc());
});

test('sending the SEO review tasks the webmasters handling the page as assignee and collaborators, keeps one open task per page, and validating closes it', function () {
    Event::fake([BroadcastStaffTaskBadgeUpdate::class, BroadcastStaffTaskChanged::class]);
    createWebsite($this->shop);
    $family  = $this->family->refresh();
    $webpage = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);

    $openTasks = fn () => StaffTask::open()->where('data->kind', AskWebmastersToImplementSeoReview::TASK_KIND)->where('data->webpage_id', $webpage->id)->get();
    StaffTask::whereIn('id', $openTasks()->pluck('id'))->update(['status' => StaffTaskStatusEnum::CANCELLED, 'closed_at' => now()]);

    [$lockOwner, $editor, $expiredEditor] = collect(range(1, 3))->map(fn () => StoreGuest::make()->action($this->group, Guest::factory()->definition())->getUser())->all();
    $webpage->update([
        'locked_at'         => now(),
        'locked_by_user_id' => $lockOwner->id,
        'lock_data'         => ['editors' => [
            ['user_id' => $editor->id, 'until' => null, 'until_publish' => false],
            ['user_id' => $expiredEditor->id, 'until' => now()->subDay()->toIso8601String(), 'until_publish' => false],
            ['user_id' => $this->user->id, 'until' => null, 'until_publish' => false],
        ]],
    ]);

    $reviewRoute = route('grp.models.product_category.seo_review.store', ['productCategory' => $family->id]);

    post($reviewRoute, ['suggestions' => ['title' => 'Electric Aroma Diffusers']])->assertSessionHasNoErrors();
    post($reviewRoute, ['suggestions' => ['title' => 'Electric Aroma Diffusers', 'top_desc' => 'Better copy']])->assertSessionHasNoErrors();

    $task = $openTasks()->sole();
    expect($task->requester_id)->toBe($this->user->id)
        ->and($task->assignee_id)->toBe($lockOwner->id)
        ->and($task->collaborators()->pluck('users.id')->all())->toBe([$editor->id])
        ->and($task->description)->toContain('tab='.FamilyTabsEnum::EDIT_PAGE->value)
        ->and($task->conversation->messages()->latest('id')->value('body'))->toContain('2 suggestions are waiting');

    SeoContentSuggestion::where('webpage_id', $webpage->id)
        ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
        ->where('state', SeoContentSuggestionStateEnum::PENDING)
        ->update(['state' => SeoContentSuggestionStateEnum::IMPLEMENTED]);
    post(route('grp.models.product_category.seo_review.validate', ['productCategory' => $family->id]))->assertSessionHasNoErrors();

    expect($task->refresh()->status)->toBe(StaffTaskStatusEnum::DONE);

    $webpage->update(['locked_at' => null, 'locked_by_user_id' => null, 'lock_data' => null]);
    post($reviewRoute, ['suggestions' => ['url' => 'electric-aroma-diffusers']])->assertSessionHasNoErrors();

    expect($openTasks()->sole())
        ->assignee_id->toBeNull()
        ->department->toBe('webmaster');
});

test('webmaster validates a product SEO review with suggestions still pending, which dismisses them', function () {
    createWebsite($this->shop);
    $product = $this->product->refresh();
    $webpage = $product->webpage ?? StoreProductWebpage::make()->action($product);
    $product->refresh();

    StoreProductSeoReview::make()->handle($product, ['suggestions' => ['title' => 'Electric Aroma Diffuser', 'description' => 'Better meta description']], null);
    $suggestions = SeoContentSuggestion::where('webpage_id', $webpage->id)->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)->whereIn('state', [SeoContentSuggestionStateEnum::PENDING])->get()->keyBy('field');
    $suggestions['title']->update(['state' => SeoContentSuggestionStateEnum::IMPLEMENTED]);

    expect(ValidateProductSeoReview::make()->handle($product, $this->user))->toBe(1)
        ->and($suggestions['title']->refresh()->state)->toBe(SeoContentSuggestionStateEnum::ACCEPTED)
        ->and($suggestions['description']->refresh())
        ->state->toBe(SeoContentSuggestionStateEnum::DISMISSED)
        ->decided_by_user_id->toBe($this->user->id);

    expect(GetProductSeoReview::run($webpage, $product, $this->user))
        ->pending->toBe(0)
        ->is_validated->toBeTrue();
});

test('UI show product setting page shows the master meta description and the webpage share image', function () {
    createWebsite($this->shop);

    $masterShop = StoreMasterShop::make()->action($this->group, [
        'code' => 'MS-'.uniqid(),
        'name' => 'Master Shop',
        'type' => ShopTypeEnum::B2B,
    ]);
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MD-'.uniqid(),
        'name' => 'Master Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MF-'.uniqid(),
        'name' => 'Master Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'        => 'MA-'.substr(uniqid(), -6),
        'name'        => 'Master diffuser',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [['id' => $this->product->tradeUnits()->first()->id, 'quantity' => 1]],
    ]);
    $masterAsset->updateQuietly(['meta_description' => 'Wholesale aroma diffusers for every room']);

    $product = $this->product->refresh();
    $product->updateQuietly(['master_product_id' => $masterAsset->id]);
    $webpage = $product->webpage ?? StoreProductWebpage::make()->action($product);
    $webpage->updateQuietly(['seo_image_url' => 'https://cdn.example.com/diffuser.png']);

    get(route('grp.org.shops.show.catalogue.products.all_products.setting', [$this->organisation->slug, $this->shop->slug, $product->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/ProductSetting')
            ->where('master.meta_description', 'Wholesale aroma diffusers for every room')
            ->where('shop.shop.language_code', $this->shop->language->code)
            ->where('shop.webpage.seo_image_field.type', 'image_crop_square')
            ->where('shop.webpage.seo_image_field.value', ['original' => 'https://cdn.example.com/diffuser.png'])
            ->where('shop.webpage.seo_image_field.hasOther.1.value', 'https://cdn.example.com/diffuser.png')
            ->where('shop.webpage.structured_data_source.website_name', $webpage->website->name)
            ->has('shop.webpage.structured_data_source.web_blocks')
            ->where('shop.webpage.preview.url', $webpage->url)
            ->where('shop.webpage.preview.share_image.url', 'https://cdn.example.com/diffuser.png')
            ->etc());

    $product->updateQuietly(['master_product_id' => null]);
});

test('the SEO team adds structured data straight to a product page, without webmaster validation', function () {
    createWebsite($this->shop);
    $product = $this->product->refresh();
    $webpage = $product->webpage ?? StoreProductWebpage::make()->action($product);
    $webpage->update(['seo_data' => ['structured_data' => '[{"@context":"https://schema.org","@type":"Organization","name":"AW"}]']]);

    post(route('grp.models.product.seo_review.store', ['product' => $product->id]), [
        'structured_data' => [
            ['json_ld' => '{"@context":"https://schema.org","@type":"VideoObject"}'],
            ['json_ld' => ''],
            ['json_ld' => '{"@context":"https://schema.org","@type":"Organization","name":"AW"}'],
        ],
    ])->assertSessionHasNoErrors();

    expect(collect(json_decode($webpage->refresh()->structuredDataJson(), true))->pluck('@type')->all())->toBe(['Organization', 'VideoObject'])
        ->and(GetProductSeoReview::run($webpage, $product, $this->user)['pending'])->toBe(0);
});

test('UI show family sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->family->slug,
        'tab'         => FamilyTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Family')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->family));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('sales analysis shop rows carry the live page of the scope record in that shop', function () {
    createWebsite($this->shop);
    $family      = $this->family->refresh();
    $familyPage  = $family->webpage ?? StoreProductCategoryWebpage::make()->action($family);
    $product     = $this->product->refresh();
    $productPage = $product->webpage ?? StoreProductWebpage::make()->action($product);
    $familyPage->update(['state' => WebpageStateEnum::LIVE, 'canonical_url' => 'https://example.test/aroma/family']);
    $productPage->update(['state' => WebpageStateEnum::LIVE, 'canonical_url' => 'https://example.test/aroma/family/product']);

    $pageUrl = fn (array $webpageShops) => collect(GetSalesAnalysis::make()->handle(new SalesAnalysisScope(
        currency: 'GBP',
        amountColumn: 'grp_net_amount',
        productIds: [],
        webpageShops: $webpageShops,
        shopNodeStates: [$this->shop->id => 'active'],
    ), [])['shops'])->firstWhere('shop_id', $this->shop->id)['shop_page_url'];

    expect($pageUrl([$familyPage->id => $this->shop->id]))->toBe('https://example.test/aroma/family')
        ->and($pageUrl([$familyPage->id => $this->shop->id, $productPage->id => $this->shop->id]))->toBeNull()
        ->and($pageUrl([]))->toBeNull();

    $familyPage->update(['state' => WebpageStateEnum::CLOSED]);

    expect($pageUrl([$familyPage->id => $this->shop->id]))->toBeNull();
});

test('UI edit family in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.product_category.update')
                    ->where('parameters', [
                        'productCategory' => $this->family->id
                    ])
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue family in (tab index)', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue family in (tab sales)', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.index', [
        $this->organisation->slug,
        $this->shop->slug,
        'tab' => 'sales'
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('sales')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product in current', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.current_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI show product navigation follows the list sort', function () {
    $this->withoutExceptionHandling();

    $makeProduct = function (string $code) {
        $productData = \App\Models\Catalogue\Product::factory()->definition();
        data_set($productData, 'code', $code);
        data_set($productData, 'trade_units', [['id' => $this->product->tradeUnits()->first()->id, 'quantity' => 1]]);
        data_set($productData, 'price', 100);

        return \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->family, $productData);
    };

    $first  = $makeProduct('NAVA01');
    $middle = $makeProduct('NAVB02');
    $last   = $makeProduct('NAVC03');

    $showRoute = fn ($product) => route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $product->slug
    ]);

    get($showRoute($middle).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get($showRoute($middle).'?bucket_sort=-code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $last->name)
            ->where('navigation.next.label', $first->name)
            ->etc()
    );
});

test('UI show product navigation skips non-main variants', function () {
    $this->withoutExceptionHandling();

    $makeProduct = function (string $code) {
        $productData = \App\Models\Catalogue\Product::factory()->definition();
        data_set($productData, 'code', $code);
        data_set($productData, 'trade_units', [['id' => $this->product->tradeUnits()->first()->id, 'quantity' => 1]]);
        data_set($productData, 'price', 100);

        return \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->family, $productData);
    };

    $first = $makeProduct('VARA01');
    $last  = $makeProduct('VARC03');

    \App\Actions\Catalogue\Product\StoreProductVariant::run($first, [
        'code'    => 'VARB02',
        'ratio'   => 2,
        'price'   => 200,
        'name'    => $first->name.' 1000u',
        'is_main' => false
    ]);

    $showRoute = fn ($product) => route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $product->slug
    ]);

    get($showRoute($first).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get($showRoute($last).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->etc()
    );
});

test('UI Index catalogue product all', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.all_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product in process', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.in_process_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue product in discontinued', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.discontinued_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue products not online', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.products.not_online_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI show product in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Product')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->product->code)
                    ->etc()
            )
            ->has('tabs');
    });
});


test('UI show product sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->product->slug,
        'tab'         => ProductTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Product')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProduct($this->product));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI Index catalogue sub department inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartments')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create catalogue sub department inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.create', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show sub department in department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->subDepartment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartment')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->subDepartment->name)
                    ->etc()
            )
            ->has('tabs')
            ->missing('tabs.navigation.families_order')
            ->missing('tabs.navigation.related_categories');
    });
});

test('UI show sub department sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->subDepartment->slug,
        'tab'         => DepartmentTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartment')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->subDepartment));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI edit sub department in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->subDepartment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue collection', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.collections.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Collections')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create collection', function () {
    $response = get(route('grp.org.shops.show.catalogue.collections.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show collection', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.collections.show', [$this->organisation->slug, $this->shop->slug, $this->collectionModel->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Collection')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->collectionModel->code)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show collection product sales tab ranks products by sales in a date range', function () {
    $this->withoutExceptionHandling();
    $product = Product::where('shop_id', $this->shop->id)->whereNotNull('asset_id')->firstOrFail();
    AttachModelToCollection::make()->action($this->collectionModel, $product);

    $response = get(route('grp.org.shops.show.catalogue.collections.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->collectionModel->slug,
        'tab'                   => 'product_sales',
        'product_sales_between' => ['date' => '20260101-20261231'],
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($product) {
        $page
            ->component('Org/Catalogue/Collection')
            ->where('tabs.navigation.product_sales.title', 'Product sales')
            ->has('product_sales.data', fn (AssertableInertia $rows) => $rows
                ->each(fn (AssertableInertia $row) => $row
                    ->has('sales_grp_currency_external')
                    ->has('invoices')
                    ->has('sold')
                    ->has('sales_grp_currency_external_delta')
                    ->etc()))
            ->where('product_sales.data', fn ($rows) => collect($rows)->pluck('id')->contains($product->id));
    });
});

test('UI show collection navigation follows the bucket it was opened from', function () {
    $this->withoutExceptionHandling();

    $makeCollection = function (string $code, CollectionStateEnum $state) {
        $collection = StoreCollection::make()->action($this->shop, [
            'code'        => $code,
            'name'        => $code.' name',
            'description' => $code.' description',
        ]);
        $collection->update(['state' => $state]);

        return $collection->refresh();
    };

    $first    = $makeCollection('NAVCOLA', CollectionStateEnum::ACTIVE);
    $inactive = $makeCollection('NAVCOLB', CollectionStateEnum::INACTIVE);
    $middle   = $makeCollection('NAVCOLC', CollectionStateEnum::ACTIVE);
    $last     = $makeCollection('NAVCOLD', CollectionStateEnum::ACTIVE);

    $showRoute = fn ($collection) => route('grp.org.shops.show.catalogue.collections.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $collection->slug
    ]);

    get($showRoute($middle).'?bucket=active')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->code.' - '.$first->name)
            ->where('navigation.next.label', $last->code.' - '.$last->name)
            ->etc()
    );

    expect($inactive->state)->toBe(CollectionStateEnum::INACTIVE);
});

test('UI edit collection', function () {
    $response = get(route('grp.org.shops.show.catalogue.collections.edit', [$this->organisation->slug, $this->shop->slug, $this->collectionModel->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI edit product', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.families.show.products.edit', [$this->organisation->slug, $this->shop->slug, $this->family->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI edit product composition', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.products.all_products.composition', [$this->organisation->slug, $this->shop->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/ProductComposition')
            ->has('title')
            ->has('pageHead')
            ->has('formData.blueprint.0.fields.trade_units')
            ->has('breadcrumbs');
    });
});

test('UI create product', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.show.products.create', [$this->organisation->slug, $this->shop->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 5);
    });
});

test('UI Index Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Charges')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data');
    });
});

test('UI Index Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Billables/Services')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI show Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.show', [$this->organisation->slug, $this->shop->slug, $this->charge->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Charge')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->charge->name)
                    ->etc()
            );
    });
});

test('UI show packaging and leaflet, with stats loaded after the page', function () {
    $packaging = \App\Actions\Billables\Packaging\StorePackaging::make()->action($this->shop, [
        'code'        => 'UI-PACK-'.uniqid(),
        'family_code' => 'UIFAM',
        'name'        => 'UI test envelope',
        'type'        => \App\Enums\Catalogue\Packaging\PackagingTypeEnum::cases()[0]->value,
        'price'       => 0.5,
    ]);
    $leaflet = \App\Actions\Billables\Leaflet\StoreLeaflet::make()->action($this->shop, [
        'name'         => 'UI test leaflet',
        'type'         => \App\Enums\Catalogue\Leaflet\LeafletTypeEnum::cases()[0]->value,
        'price'        => 0,
        'family_codes' => ['UIFAM'],
    ]);

    get(route('grp.org.shops.show.billables.packagings.show', [$this->organisation->slug, $this->shop->slug, $packaging->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Billables/Packaging')
            ->where('packaging.code', $packaging->code)
            ->where('tabs.current', 'showcase')
            ->has('navigation.previous')
            ->has('navigation.next')
            ->has('leaflets', 1)
            ->missing('stats'));

    get(route('grp.org.shops.show.billables.packagings.show', [$this->organisation->slug, $this->shop->slug, $packaging->slug, 'tab' => 'history']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'history')->has('history'));

    get(route('grp.org.shops.show.billables.packagings.show', [$this->organisation->slug, $this->shop->slug, $packaging->slug, 'tab' => 'orders']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'orders')->has('orders'));

    get(route('grp.org.shops.show.billables.leaflets.show', [$this->organisation->slug, $this->shop->slug, $leaflet->id, 'tab' => 'orders']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'orders')->has('orders'));

    get(route('grp.org.shops.show.billables.charges.show', [$this->organisation->slug, $this->shop->slug, $this->charge->slug, 'tab' => 'history']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('history'));

    get(route('grp.org.shops.show.billables.leaflets.show', [$this->organisation->slug, $this->shop->slug, $leaflet->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Billables/Leaflet')
            ->where('leaflet.name', 'UI test leaflet')
            ->where('tabs.current', 'showcase')
            ->has('navigation')
            ->has('packagings', 1)
            ->missing('stats'));

    expect(\App\Actions\Billables\Packaging\UI\ShowPackaging::make()->stats($packaging))
        ->toMatchArray(['orders' => 0, 'customers' => 0, 'revenue' => 0.0])
        ->not->toHaveKey('delivery_notes')
        ->and(\App\Actions\Billables\Leaflet\UI\ShowLeaflet::make()->stats($leaflet))
        ->toMatchArray(['orders' => 0, 'customers' => 0, 'last_used_at' => null, 'attached' => 0, 'copies_printed' => 0])
        ->not->toHaveKey('delivery_notes');

    get(route('grp.org.shops.show.billables.leaflets.index', [$this->organisation->slug, $this->shop->slug]))
        ->assertRedirect(route('grp.org.shops.show.billables.packagings.index', [$this->organisation->slug, $this->shop->slug, 'tab' => 'leaflets']));
});

test('UI edit Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.edit', [$this->organisation->slug, $this->shop->slug, $this->charge->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI create Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI show Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Billables/Service')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->service->name)
                    ->etc()
            );
    });
});

test('UI edit Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.edit', [$this->organisation->slug, $this->shop->slug, $this->service->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI edit shop with related products description link', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'code' => 'MS-'.uniqid(),
        'name' => 'Master Shop',
        'type' => ShopTypeEnum::DROPSHIPPING,
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MD-'.uniqid(),
        'name' => 'Master Department',
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MF-'.uniqid(),
        'name' => 'Master Family',
    ]);

    $this->shop->update([
        'master_shop_id' => $masterShop->id,
    ]);

    $response = get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($masterShop, $masterFamily) {
        $page
            ->component('EditModel')
            ->has(
                'formData.blueprint.3.fields.related_product_follow_master',
                fn (AssertableInertia $field) => $field
                    ->where('type', 'toggle')
                    ->where('descriptionLinks.manage_related_products.label', 'related products tab')
                    ->where('descriptionLinks.manage_related_products.route.name', 'grp.masters.master_shops.show.master_families.show')
                    ->where('descriptionLinks.manage_related_products.route.parameters.masterShop', $masterShop->slug)
                    ->where('descriptionLinks.manage_related_products.route.parameters.masterFamily', $masterFamily->slug)
                    ->where('descriptionLinks.manage_related_products.route.parameters.tab', 'related_products')
                    ->etc()
            );
    });
});


test('UI get section route catalogue dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.catalogue.dashboard', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_CATALOGUE->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route billables charges index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.billables.charges.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_BILLABLES->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route shop edit', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.settings.edit', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_SETTINGS->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route shop dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.dashboard.show', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_DASHBOARD->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('product index queries use time series aggregation', function () {
    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    expect(\App\Actions\Catalogue\Product\UI\IndexProductsInGroup::make()->handle($this->group)->total())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInOrganisation::make()->handle($this->organisation)->total())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInTradeUnit::make()->handle(\App\Models\Goods\TradeUnit::first())->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexOutOfStockProducts::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsWithNoFamily::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexRRPViolationProducts::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInCollection::make()->handle($this->collectionModel)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\Json\GetProductsInCollection::make()->handle($this->collectionModel)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\Json\GetProductsWithNoWebpage::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0);
});

test('products export links every image as a jpg', function () {
    config([
        'img-proxy.base_url' => 'https://media.test',
        'img-proxy.key'      => str_repeat('ab', 32),
        'img-proxy.salt'     => str_repeat('cd', 32),
    ]);
    $encodeSource = fn (string $source) => rtrim(strtr(base64_encode($source), '+/', '-_'), '=');

    $product = \App\Models\Catalogue\Product::where('shop_id', $this->shop->id)->where('is_main', true)->whereNull('exclusive_for_customer_id')->first();
    $product->update([
        'web_images' => [
            'all' => [
                ['original' => ['original' => 'https://media.test/signature/'.$encodeSource('local://media/first.jpeg'), 'webp' => 'https://media.test/other/first.webp']],
                ['original' => ['original' => 'https://media.test/signature/'.$encodeSource('local://media/second.png')]],
            ],
        ],
    ]);

    $export = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['code', 'images', 'image_1', 'image_2', 'image_3'], imagesAsJpg: true);
    $row    = $export->mapRow($export->dataQuery()->where('products.id', $product->id)->first());

    $firstJpg  = \App\Actions\Helpers\Images\GetImgProxyUrl::run(new \App\Helpers\ImgProxy\Image()->make('local://media/first.jpeg')->extension('jpg'));
    $secondJpg = \App\Actions\Helpers\Images\GetImgProxyUrl::run(new \App\Helpers\ImgProxy\Image()->make('local://media/second.png')->extension('jpg'));

    [$code, $images, $firstShort, $secondShort, $thirdShort] = $row;

    expect($code)->toBe($product->code)
        ->and($images)->toBe("$firstShort, $secondShort")
        ->and($thirdShort)->toBeNull()
        ->and($firstShort)->toMatch('#^https?://[^/]+/i/[0-9A-Za-z]{1,11}\.jpg$#')
        ->and(strlen($firstShort))->toBeLessThan(strlen($firstJpg))
        ->and(\App\Actions\Helpers\Images\RedirectImageShortUrl::run(basename($firstShort)))->toBe($firstJpg)
        ->and(\App\Actions\Helpers\Images\RedirectImageShortUrl::run(basename($secondShort)))->toBe($secondJpg)
        ->and($export->mapRow($export->dataQuery()->where('products.id', $product->id)->first()))->toBe($row);

    $this->get($firstShort)->assertRedirect($firstJpg);

    expect(\App\Actions\Helpers\Images\ShortenImgProxyUrls::code($firstJpg, 0))->not->toBe(\App\Actions\Helpers\Images\ShortenImgProxyUrls::code($firstJpg, 1));

    $originalExport = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['image_1']);
    expect($originalExport->mapRow($originalExport->dataQuery()->where('products.id', $product->id)->first()))
        ->toBe(['https://media.test/signature/'.$encodeSource('local://media/first.jpeg')]);
});

test('website pages swap imgproxy urls for short signed links that serve the same image', function () {
    config([
        'img-proxy.base_url' => 'https://media.test',
        'img-proxy.key'      => str_repeat('ab', 32),
        'img-proxy.salt'     => str_repeat('cd', 32),
    ]);

    $media = \App\Models\Helpers\Media::create([
        'group_id'              => $this->organisation->group_id,
        'ulid'                  => (string) Str::ulid(),
        'uuid'                  => (string) Str::uuid(),
        'name'                  => 'shot',
        'file_name'             => 'a1b2c3d4.jpeg',
        'mime_type'             => 'image/jpeg',
        'disk'                  => 'local',
        'collection_name'       => 'images',
        'size'                  => 4,
        'manipulations'         => [],
        'custom_properties'     => [],
        'generated_conversions' => [],
        'responsive_images'     => [],
    ]);

    $image    = fn () => new \App\Helpers\ImgProxy\Image()->make($media->getImgProxyFilename());
    $original = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image());
    $thumb    = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image()->resize(0, 600)->extension('avif'));
    $page     = ['blocks' => [['web_images' => ['original' => $original, 'avif' => $thumb]], ['srcset' => "$thumb 1x, $original 2x"]], 'other' => 'https://media.test/x/y'];

    $website           = new \App\Models\Web\Website(['settings' => []]);
    $shorten           = fn () => \App\Actions\Helpers\Images\ShortenWebsiteImageUrls::run($page, $website, 'https://www.shop.test/some/page');
    expect($shorten())->toBe($page);

    $website->settings = ['short_image_urls' => true];
    $short             = $shorten();
    $shortThumb        = $short['blocks'][0]['web_images']['avif'];
    $shortOriginal     = $short['blocks'][0]['web_images']['original'];

    expect($shortThumb)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}/0x600\.avif$#')
        ->and($shortOriginal)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}\.jpeg$#')
        ->and(strlen($shortThumb))->toBeLessThan(strlen($thumb) - 40)
        ->and($short['blocks'][1]['srcset'])->toBe("$shortThumb 1x, $shortOriginal 2x")
        ->and($short['other'])->toBe('https://media.test/x/y');

    $serve = fn (string $short) => \App\Actions\Helpers\Images\ServeWebsiteShortImage::make()->handle(...array_pad(explode('/', Str::after($short, '/i/'), 3), 3, ''));
    expect($serve($shortThumb))->toBe($thumb)
        ->and($serve($shortOriginal))->toBe($original)
        ->and($serve(str_replace('0x600', 'rs::0:600::', $shortThumb)))->toBe($thumb)
        ->and($serve(str_replace('0x600', '0x1200', $shortThumb)))->toBeNull()
        ->and($serve(str_replace('.avif', '.png', $shortThumb)))->toBeNull()
        ->and($serve(str_replace('.jpeg', '', $shortOriginal)))->toBe($original)
        ->and($serve(str_replace('.jpeg', '.png', $shortOriginal)))->toBeNull()
        ->and($serve(str_replace('.jpeg', '.a1', $shortOriginal)))->toBeNull();

    \Illuminate\Support\Facades\Http::fake(['media.test/*' => \Illuminate\Support\Facades\Http::response('avif-bytes', 200, ['Content-Type' => 'image/avif'])]);
    $this->get(Str::after($shortThumb, 'https://www.shop.test'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/avif')
        ->assertSee('avif-bytes');
    $this->get(Str::after($shortOriginal, 'https://www.shop.test'))->assertOk();
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === $thumb);

    config(['img-proxy.internal_url' => 'http://127.0.0.1:8094']);
    \Illuminate\Support\Facades\Http::fake([
        '127.0.0.1:8094/*' => \Illuminate\Support\Facades\Http::response('local-bytes', 200, ['Content-Type' => 'image/avif']),
    ]);
    $this->get(Str::after($shortThumb, 'https://www.shop.test'))->assertOk()->assertSee('local-bytes');
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === Str::replaceStart(config('img-proxy.base_url'), 'http://127.0.0.1:8094', $thumb));

    \Illuminate\Support\Facades\Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28'));
    $this->get(Str::after($shortThumb, 'https://www.shop.test'))->assertStatus(504)->assertHeader('Cache-Control', 'max-age=60, public');

    config(['img-proxy.accel_location' => '/_imgproxy/']);
    \Illuminate\Support\Facades\Http::fake();
    $this->get(Str::after($shortThumb, 'https://www.shop.test'))
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/_imgproxy'.Str::after($thumb, 'https://media.test'))
        ->assertHeader('Cache-Control', 'max-age=60, public')
        ->assertHeader('Content-Type', 'application/octet-stream');
    $this->get(Str::after($shortOriginal, 'https://www.shop.test'))->assertHeader('X-Accel-Redirect', '/_imgproxy'.Str::after($original, 'https://media.test'));
    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('images inside a product are named after it, and any name serves the same image', function () {
    config([
        'img-proxy.base_url' => 'https://media.test',
        'img-proxy.key'      => str_repeat('ab', 32),
        'img-proxy.salt'     => str_repeat('cd', 32),
    ]);

    $media = \App\Models\Helpers\Media::create([
        'group_id'              => $this->organisation->group_id,
        'ulid'                  => (string) Str::ulid(),
        'uuid'                  => (string) Str::uuid(),
        'name'                  => 'shot',
        'file_name'             => 'a1b2c3d4.jpeg',
        'mime_type'             => 'image/jpeg',
        'disk'                  => 'local',
        'collection_name'       => 'images',
        'size'                  => 4,
        'manipulations'         => [],
        'custom_properties'     => [],
        'generated_conversions' => [],
        'responsive_images'     => [],
    ]);

    $image    = fn () => new \App\Helpers\ImgProxy\Image()->make($media->getImgProxyFilename());
    $original = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image());
    $thumb    = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image()->resize(0, 600)->extension('avif'));
    $card     = new class () {
        use \App\Http\Resources\Traits\HasCardWebImages;

        public function compact(array $gallery): array
        {
            return $this->compactGallery($gallery);
        }
    };

    $page = [
        'product' => ['name' => '<b>Leather Dragon</b> Notebook (20x15 cm)', 'code' => 'LDN-01', 'web_images' => [], 'images' => ['original' => $original, 'avif' => $thumb]],
        'header'  => ['name' => 'Header 2', 'code' => 'header-2', 'logo' => $thumb],
        'family'  => ['name' => 'Notebooks', 'code' => 'LBN', 'products' => [['name' => 'Cuaderno Esotérico Buda', 'code' => 'LBN-55', 'web_images' => ['main' => ['gallery' => $card->compact(['avif' => $thumb, 'original' => \App\Actions\Helpers\Images\GetImgProxyUrl::run($image()->resize(0, 600))])]]]]],
        'banner'  => $thumb,
    ];

    $short = \App\Actions\Helpers\Images\ShortenWebsiteImageUrls::run($page, new \App\Models\Web\Website(['settings' => ['short_image_urls' => true]]), 'https://www.shop.test/some/page');

    $namedOriginal = $short['product']['images']['original'];
    $namedThumb    = $short['product']['images']['avif'];
    $cardGallery   = $short['family']['products'][0]['web_images']['main']['gallery'];
    $cardThumb     = $cardGallery['_cig']['u'].'/'.Str::before($cardGallery['v']['avif'], '|').'/'.$cardGallery['_cig']['b'].Str::after($cardGallery['v']['avif'], '|');

    expect($namedOriginal)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}/o/leather-dragon-notebook-20x15-cm\.jpeg$#')
        ->and($namedThumb)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}/0x600/leather-dragon-notebook-20x15-cm\.avif$#')
        ->and($short['header']['logo'])->toBe($short['banner'])
        ->and($short['banner'])->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}/0x600\.avif$#')
        ->and($page['family']['products'][0]['web_images']['main']['gallery'])->toHaveKey('_cig')
        ->and($cardGallery['_cig'])->toBe(['u' => 'https://www.shop.test', 'b' => 'cuaderno-esoterico-buda'])
        ->and($cardThumb)->toBe(str_replace('leather-dragon-notebook-20x15-cm', 'cuaderno-esoterico-buda', $namedThumb));

    \Illuminate\Support\Facades\Http::fake(['media.test/*' => \Illuminate\Support\Facades\Http::response('avif-bytes', 200, ['Content-Type' => 'image/avif'])]);
    $path = fn (string $url) => Str::after($url, 'https://www.shop.test');

    $this->get($path($namedThumb))->assertOk()->assertSee('avif-bytes');
    $this->get($path($cardThumb))->assertOk();
    $this->get($path(str_replace('leather-dragon-notebook-20x15-cm', 'renamed-notebook', $namedThumb)))->assertOk();
    $this->get($path($namedOriginal))->assertOk();
    $this->get($path(str_replace('/0x600/', '/0x1200/', $namedThumb)))->assertNotFound();
    $this->get($path(str_replace('.avif', '.png', $namedThumb)))->assertNotFound();
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === $thumb);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === $original);
});

test('products export ends with the weight unit columns', function () {
    $product = \App\Models\Catalogue\Product::where('shop_id', $this->shop->id)->where('is_main', true)->whereNull('exclusive_for_customer_id')->first();
    $product->update(['marketing_weight' => 250, 'gross_weight' => null]);

    $export = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all');
    $row    = $export->mapRow($export->dataQuery()->where('products.id', $product->id)->first());

    expect(array_slice($export->headings(), -2))->toBe(['Unit weight (marketing) unit', 'Gross weight unit'])
        ->and(array_slice($row, -2))->toBe(['g', null]);
});

test('products export unit price follows the current outer price and units', function () {
    $product = \App\Models\Catalogue\Product::where('shop_id', $this->shop->id)->where('is_main', true)->whereNull('exclusive_for_customer_id')->first();
    $original = $product->only(['price', 'units']);
    $product->updateQuietly(['price' => 8.5, 'units' => 2]);

    $export = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['price', 'unit_price']);
    $row    = $export->mapRow($export->dataQuery()->where('products.id', $product->id)->first());
    $product->updateQuietly($original);

    expect((float) $row[0])->toBe(8.5)
        ->and((float) $row[1])->toBe(4.25);
});

test('UI show product sends the available stock of each part', function () {
    $this->withoutExceptionHandling();
    $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->first();
    $orgStock->update(['quantity_available' => 7]);
    $this->product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->product->slug
    ]))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('showcase.org_stocks.0.id', $orgStock->id)
            ->where('showcase.org_stocks.0.quantity_available', '7')
            ->where('showcase.org_stocks.0.quantity', fn ($quantity) => (float) $quantity === 1.0)
            ->etc()
    );
});

test('customer portfolio showcase does not send the stock of each part', function () {
    $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->first();
    $orgStock->update(['quantity_available' => 7]);
    $this->product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);
    request()->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'portfolio', []))->name('retina.portfolio'));

    $showcase = \App\Actions\Catalogue\Product\UI\GetProductShowcaseInPortfolio::run($this->product);

    expect($showcase['org_stocks'][0]['id'])->toBe($orgStock->id)
        ->and($showcase['org_stocks'][0])->not->toHaveKeys(['quantity', 'quantity_available'])
        ->and($showcase['parts'][0])->not->toHaveKeys(['quantity', 'quantity_available']);
});

test('sales are visible to webmasters but not to staff unrelated to sales', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn () => StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    $unrelated = $newUser();
    $unrelated->givePermissionTo('human-resources.'.$this->organisation->id.'.view');
    actingAs($unrelated);
    get(route('grp.org.shops.index', [$this->organisation->slug]))->assertForbidden();

    $webmaster = $newUser();
    $webmaster->givePermissionTo('web.'.$this->shop->id.'.view');
    actingAs($webmaster);

    get(route('grp.org.shops.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Catalogue/Shops'));

    get(route('grp.dashboard.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/GrpDashboard')->has('dashboard.super_blocks', 1));
});

test('shop links on the dashboards open the shop dashboard on the target tab, whatever tab the user looked at last', function () {
    $originalSettings = $this->user->settings;
    $this->user->update(['settings' => array_merge($originalSettings ?? [], ['shop_dashboard_section' => ShopDashboardSectionsEnum::SALES->value])]);

    $targetUrl = route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => ShopDashboardSectionsEnum::TARGET->value]);

    get(route('grp.majordomo.redirect_shops_from_dashboard', $this->shop->id))->assertRedirect($targetUrl);

    get($targetUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.super_blocks.0.sections.current', 'target'));

    $this->user->update(['settings' => $originalSettings]);
});

test('shop dashboard sales table shows departments, with brands as an icon on the right', function () {
    $response = get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Catalogue/Shop')
            ->where('dashboard.super_blocks.0.blocks.0.tabs.departments.title', 'Departments')
            ->where('dashboard.super_blocks.0.blocks.0.tabs.sub_departments.title', 'Sub-departments')
            ->has('dashboard.super_blocks.0.month_target.target')
            ->has('dashboard.super_blocks.0.year_target.target')
            ->where('dashboard.super_blocks.0.sections.current', 'target')
            ->where('dashboard.super_blocks.0.sections.navigation', fn ($navigation) => array_keys($navigation->all()) === array_map(
                fn (ShopDashboardSectionsEnum $section) => $section->value,
                ShopDashboardSectionsEnum::forShop($this->shop)
            ));
    });

    $departmentsTable = ShopDashboardSalesTableTabsEnum::DEPARTMENTS->table(
        $this->shop,
        ['departments' => GetDepartmentTimeSeriesStats::run($this->shop)]
    );

    expect($departmentsTable['header']['columns']['label']['formatted_value'])->toBe('Department')
        ->and($departmentsTable)->toHaveKeys(['body', 'totals'])
        ->and(ShopDashboardSalesTableTabsEnum::BRANDS->blueprint())->toMatchArray(['type' => 'icon', 'align' => 'right']);

    $subDepartmentsTable = ShopDashboardSalesTableTabsEnum::SUB_DEPARTMENTS->table(
        $this->shop,
        ['sub_departments' => GetSubDepartmentTimeSeriesStats::run($this->shop)]
    );

    expect($subDepartmentsTable['header']['columns']['label']['formatted_value'])->toBe('Sub-department')
        ->and($subDepartmentsTable)->toHaveKeys(['body', 'totals']);
});

test('catalogue top of the month links to the department, family and product with their counts', function () {
    $this->shop->stats->update([
        'top_1m_department_id' => $this->department->id,
        'top_1m_family_id'     => $this->family->id,
        'top_1m_product_id'    => $this->product->id,
    ]);

    $topSelling = GetCatalogueShowcase::run($this->shop->fresh())['top_selling'];
    $shopParameters = ['organisation' => $this->organisation->slug, 'shop' => $this->shop->slug];

    expect($topSelling['department']['route'])->toBe([
        'name'       => 'grp.org.shops.show.catalogue.departments.show',
        'parameters' => [...$shopParameters, 'department' => $this->department->slug],
    ])
        ->and($topSelling['department']['counts'])->toBe([
            'families' => $this->department->stats->number_current_families,
            'products' => $this->department->stats->number_current_products,
        ])
        ->and($topSelling['family']['route']['parameters']['family'])->toBe($this->family->slug)
        ->and($topSelling['family']['counts'])->toBe(['products' => $this->family->stats->number_current_products])
        ->and($topSelling['product']['route']['name'])->toBe('grp.org.shops.show.catalogue.products.all_products.show')
        ->and($topSelling['product']['route']['parameters']['product'])->toBe($this->product->slug);

    get(route($topSelling['department']['route']['name'], $topSelling['department']['route']['parameters']))->assertOk();
    get(route($topSelling['family']['route']['name'], $topSelling['family']['route']['parameters']))->assertOk();
    get(route($topSelling['product']['route']['name'], $topSelling['product']['route']['parameters']))->assertOk();
});

test('shop top menu links to the target section of the shop dashboard', function () {
    $shopNavigation = GetShopNavigation::run($this->shop, $this->user)['dashboard'];
    $target         = collect($shopNavigation['topMenu']['subSections'])->filter()->first();

    expect($shopNavigation['route']['parameters']['section'])->toBe(ShopDashboardSectionsEnum::TARGET->value);

    expect($target['root'])->toBe('grp.org.shops.show.dashboard.show')
        ->and($target['route']['name'])->toBe('grp.org.shops.show.dashboard.show')
        ->and($target['route']['parameters']['section'])->toBe(ShopDashboardSectionsEnum::TARGET->value);

    get(route($target['route']['name'], $target['route']['parameters']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.super_blocks.0.sections.current', ShopDashboardSectionsEnum::TARGET->value));
});

test('shop month sales target defaults to last year plus growth until management sets it', function () {
    $shop  = $this->shop;
    $today = now('UTC')->startOfDay();

    $block = GetShopMonthSalesTarget::run($shop, null, $today);
    $lastYearTotal = $block['last_year_total'];

    expect($block['target']['is_default'])->toBeTrue()
        ->and($block['target']['amount'])->toBe($lastYearTotal > 0 ? round($lastYearTotal * (1 + config('marketing.default_sales_target_growth')), 2) : null)
        ->and($block['chart']['this_year'])->toHaveCount($today->day)
        ->and($block['can_edit'])->toBeFalse();

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 123456.78, 'month' => $today->format('Y-m')]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['is_default'])->toBeFalse()
        ->and($block['target']['amount'])->toBe(123456.78)
        ->and($block['gap'])->toBe(round(max(0, 123456.78 - $block['sales_so_far'] - $block['pipeline']['amount']), 2));
});

test('sales targets count the invoices issued so far, partners included', function () {
    $shop       = $this->shop;
    $today      = Carbon::parse('2032-03-10', 'UTC');
    $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    foreach (['2032-01-20' => [4, 0], '2032-03-02' => [3, 2], '2032-03-15' => [7, 0]] as $period => [$invoices, $invoicesInternal]) {
        $timeSeries->records()->updateOrCreate(['period' => $period, 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()], ['invoices' => $invoices, 'invoices_internal' => $invoicesInternal]);
    }

    expect(GetShopMonthSalesTarget::run($shop, null, $today)['invoices'])->toBe(5)
        ->and(GetShopYearSalesTarget::run($shop, null, $today, false)['invoices'])->toBe(9);
});

test('shop year sales target compares the same days last year, January included, and sums monthly targets', function () {
    $shop  = $this->shop;
    $today = Carbon::parse('2031-03-10', 'UTC');

    $seed = function (TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) use ($shop) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales]
            );
        }
    };

    $seed(TimeSeriesFrequencyEnum::MONTHLY, ['2030-01' => 1000, '2030-02' => 2000, '2030-03' => 3000, '2030-12' => 4000, '2031-01' => 1100, '2031-02' => 2100, '2031-03' => 9999]);
    $seed(TimeSeriesFrequencyEnum::DAILY, ['2030-03-05' => 500, '2030-03-20' => 900, '2031-03-02' => 600, '2031-03-15' => 700]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $block  = GetShopYearSalesTarget::run($shop, null, $today);

    expect($block['sales_so_far'])->toBe(3800.0)
        ->and($block['last_year_so_far'])->toBe(3500.0)
        ->and($block['last_year_total'])->toBe(10000.0)
        ->and($block['remaining_days'])->toBe(296)
        ->and($block['chart']['this_year'])->toBe([1100.0, 3200.0, 3800.0])
        ->and($block['chart']['weekly_versus_last_year'])->toHaveCount(10)
        ->and(last($block['chart']['weekly_versus_last_year']))->toBe(['x' => 2.323, 'y' => 20.0])
        ->and($block['children'])->toBe([])
        ->and($block['expected'])->toBe(round(3800 + 6500 * (3800 / 3500), 2))
        ->and($block['target']['amount'])->toEqualWithDelta(10000 * (1 + $growth), 0.05)
        ->and($block['target']['is_default'])->toBeTrue()
        ->and($block['can_edit'])->toBeFalse();

    $organisationYear = GetShopYearSalesTarget::run($shop->organisation, null, $today);
    expect(collect($organisationYear['children'])->firstWhere('key', (string) $shop->id))
        ->toMatchArray(['name' => $shop->name, 'sales_so_far' => 3800.0, 'children' => []]);

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 5000, 'month' => '2031-02']);

    $block = GetShopYearSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['amount'])->toEqualWithDelta(8000 * (1 + $growth) + 5000, 0.05)
        ->and($block['target']['is_default'])->toBeFalse()
        ->and($block['target']['months_set'])->toBe(1);
});

test('organisation target adds up its shops, leaving closed shops out of the target', function () {
    $secondShop = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $closedShop = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $closedShop->update(['state' => ShopStateEnum::CLOSED]);
    $today = Carbon::parse('2034-06-10', 'UTC');

    $seed = function (Shop $shop, TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales]
            );
        }
    };

    $seed($this->shop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 1000]);
    $seed($this->shop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 400, '2034-06-03' => 300]);
    $seed($secondShop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 2000]);
    $seed($secondShop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 600, '2034-06-03' => 700]);
    $seed($closedShop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 5000]);
    $seed($closedShop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 100]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $month  = GetShopMonthSalesTarget::run($this->organisation, $this->user, $today);

    $shopChildren = collect($month['children'])->keyBy('key');

    expect($month['sales_so_far'])->toBe(1000.0)
        ->and($month['last_year_so_far'])->toBe(1100.0)
        ->and($month['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($month['target']['is_sum_of_shops'])->toBeTrue()
        ->and($month['selection_setting'])->toBe('organisation_target_shop_'.$this->organisation->id)
        ->and($shopChildren->has((string) $closedShop->id))->toBeFalse()
        ->and($shopChildren[(string) $secondShop->id])->toMatchArray(['name' => $secondShop->name, 'sales_so_far' => 700.0, 'last_year_total' => 600.0])
        ->and($shopChildren[(string) $secondShop->id]['target']['amount'])->toEqualWithDelta(600 * (1 + $growth), 0.05)
        ->and($shopChildren[(string) $secondShop->id]['link']['parameters'])->toMatchArray(['shop' => $secondShop->slug, 'section' => 'target'])
        ->and($month['can_edit'])->toBeFalse()
        ->and($month['update_route'])->toBeNull()
        ->and(GetShopYearSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(3000 * (1 + $growth), 0.05);

    UpdateShopSalesTarget::make()->action($secondShop, ['target_org_currency' => 5000, 'month' => '2034-06']);

    expect(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target'])->toMatchArray(['is_default' => false])
        ->and(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(400 * (1 + $growth) + 5000, 0.05)
        ->and(GetShopYearSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth) + 5000, 0.05);

    get(route('grp.org.dashboard.show', $this->organisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('dashboard.super_blocks.0.month_target.target')->has('dashboard.super_blocks.0.year_target.target')
            ->where('dashboard.super_blocks.0.tabs_box.navigation', fn ($boxes) => collect(collect($boxes)->firstWhere('tabs.0.tab_slug', 'returned')['children'] ?? [])
                ->isNotEmpty() && collect(collect($boxes)->firstWhere('tabs.0.tab_slug', 'returned')['children'])->every(fn ($child) => is_int($child['tabs'][0]['value']))));

    $secondShop->update(['state' => ShopStateEnum::CLOSED]);
});

test('expected month and year end add the TimesFM forecast of the days left, drawn with its likely range, and fall back to last year without one', function () {
    $shop  = $this->shop;
    $today = Carbon::parse('2036-05-10', 'UTC');

    $daily = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    for ($day = Carbon::parse('2036-04-01'); $day->lt($today); $day->addDay()) {
        $daily->records()->updateOrCreate(
            ['period' => $day->toDateString(), 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
            ['sales_org_currency_external' => $day->day === 3 ? 300 : 50, 'sales_grp_currency_external' => 40]
        );
    }

    config(['services.timesfm.url' => 'http://timesfm.test', 'services.timesfm.token' => 'secret']);
    Http::fake(['timesfm.test/forecast' => fn ($request) => Http::response([
        'version' => '3',
        'deciles' => array_fill(0, count($request['series']), array_fill(0, $request['horizon'], [-20, 40, 60, 80, 100, 120, 140, 160, 220])),
    ])]);

    expect(ForecastShopSales::run($today))->toBeGreaterThanOrEqual(1);

    Http::assertSent(fn ($request) => $request['horizon'] === 34 && $request->hasHeader('Authorization', 'Bearer secret'));
    Http::assertSentCount(1);

    $variance = round((220 / 2.563) ** 2, 2);
    $forecast = $shop->stats->fresh()->sales_forecast;
    expect($forecast['version'])->toBe('3')
        ->and($forecast['from'])->toBe('2036-05-10')
        ->and($forecast['org'])->toHaveCount(22 + 214)
        ->and($forecast['org']['2036-05-10'])->toEqual([14.6, round($variance / 7, 2)])
        ->and($forecast['org']['2036-06-01'])->toEqual([14.6, round($variance / 7, 2)])
        ->and(array_key_last($forecast['org']))->toBe('2036-12-31');

    $salesSoFar = 300 + 8 * 50;
    $block      = GetShopMonthSalesTarget::run($shop, null, $today);
    $line       = $block['chart']['forecast'];
    expect($block['sales_so_far'])->toEqual($salesSoFar)
        ->and($block['expected'])->toEqualWithDelta($salesSoFar + 21 * 14.6, 0.01)
        ->and($line['expected'][8])->toBeNull()
        ->and($line['expected'][9])->toEqual($salesSoFar)
        ->and($line['expected'][30])->toEqualWithDelta($block['expected'], 0.01)
        ->and($line['low'][30])->toBeGreaterThan($salesSoFar)->toBeLessThan($line['expected'][30])
        ->and($line['high'][30])->toEqualWithDelta($line['expected'][30] + 1.2816 * 1.5 * sqrt(21 * round($variance / 7, 2)), 0.05);

    $year = GetShopYearSalesTarget::run($shop, null, $today);
    expect($year['expected'])->toEqualWithDelta($year['sales_so_far'] + 235 * 14.6, 0.05)
        ->and($year['chart']['forecast']['expected'][3])->toEqual(round($year['sales_so_far'] - $salesSoFar, 2))
        ->and($year['chart']['forecast']['expected'][11])->toEqualWithDelta($year['expected'], 0.05)
        ->and($year['chart']['forecast']['high'][11])->toBeGreaterThan($year['expected']);

    $shopChild = collect(GetShopMonthSalesTarget::run($this->organisation, null, $today)['children'])->firstWhere('key', (string) $shop->id);
    $nextMonth = GetShopMonthSalesTarget::run($shop, null, Carbon::parse('2036-06-02', 'UTC'));
    expect($shopChild['expected'])->toEqualWithDelta($salesSoFar + 21 * 14.6, 0.01)
        ->and($nextMonth['expected'])->toEqual(0)
        ->and($nextMonth['chart']['forecast'])->toBeNull();

    config(['services.timesfm.url' => null]);
    expect(ForecastShopSales::run($today))->toBe(0);

    $shop->stats->update(['sales_forecast' => null, 'sales_forecast_hydrated_at' => null]);
});

test('the shop sales forecast averages TimesFM with last year on the recent trend', function () {
    $shop  = $this->shop;
    $today = Carbon::parse('2041-05-10', 'UTC');

    $daily = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    for ($day = Carbon::parse('2040-04-01'); $day->lt($today); $day->addDay()) {
        $daily->records()->updateOrCreate(
            ['period' => $day->toDateString(), 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
            ['sales_org_currency_external' => $day->year === 2041 ? 50 : 25, 'sales_grp_currency_external' => 40]
        );
    }

    config(['services.timesfm.url' => 'http://timesfm.test', 'services.timesfm.token' => 'secret']);
    Http::fake(['timesfm.test/forecast' => fn ($request) => Http::response([
        'version' => '3',
        'deciles' => array_fill(0, count($request['series']), array_fill(0, $request['horizon'], [-20, 40, 60, 80, 100, 120, 140, 160, 220])),
    ])]);

    ForecastShopSales::run($today);

    $forecast = $shop->stats->fresh()->sales_forecast;
    expect($forecast['org']['2041-05-10'][0])->toEqual(round((14.6 + 25 * 2) / 2, 2))
        ->and($forecast['org']['2041-12-31'][0])->toEqual(round((14.6 + 50 * 2) / 2, 2))
        ->and($forecast['grp']['2041-05-10'][0])->toEqual(round((14.6 + 40) / 2, 2));

    $shop->stats->update(['sales_forecast' => null, 'sales_forecast_hydrated_at' => null]);
    config(['services.timesfm.url' => null]);
});

test('group target adds up every organisation in the group currency', function () {
    $today = Carbon::parse('2036-03-10', 'UTC');

    $seed = function (Shop $shop, TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales * 10, 'sales_grp_currency_external' => $sales]
            );
        }
    };

    $seed($this->shop, TimeSeriesFrequencyEnum::MONTHLY, ['2035-03' => 2000]);
    $seed($this->shop, TimeSeriesFrequencyEnum::DAILY, ['2035-03-05' => 800, '2036-03-03' => 900]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $month  = GetShopMonthSalesTarget::run($this->group, $this->user, $today);

    expect($month['sales_so_far'])->toBe(900.0)
        ->and($month['last_year_so_far'])->toBe(800.0)
        ->and($month['currency_code'])->toBe($this->group->currency->code)
        ->and($month['selection_setting'])->toBe('group_target_organisation')
        ->and(collect($month['children'])->sum('target.amount'))->toEqualWithDelta($month['target']['amount'], 0.05)
        ->and(collect($month['children'])->firstWhere('key', (string) $this->organisation->id)['currency_code'])->toBe($this->group->currency->code)
        ->and($month['target']['amount'])->toEqualWithDelta(800 * (1 + $growth), 0.05)
        ->and($month['target']['is_sum_of_shops'])->toBeTrue()
        ->and($month['can_edit'])->toBeFalse()
        ->and(GetShopYearSalesTarget::run($this->group, null, $today)['target']['amount'])->toEqualWithDelta(2000 * (1 + $growth), 0.05);

    get(route('grp.dashboard.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('dashboard.super_blocks.0.month_target.target')->has('dashboard.super_blocks.0.year_target.target'));
});

test('a shop selling under several invoice categories targets their sum, partners included, each category taking its share of the shop target until set', function () {
    $shop     = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $customer = createCustomer($shop);
    $today    = Carbon::parse('2038-05-10', 'UTC');
    $growth   = (float) config('marketing.default_sales_target_growth');

    $category = fn (string $name) => StoreInvoiceCategory::make()->action($this->organisation, [
        'name'        => $name,
        'type'        => InvoiceCategoryTypeEnum::VIP->value,
        'currency_id' => $this->organisation->currency_id,
    ]);
    $retail   = $category('Retail '.uniqid());
    $partners = $category('Partners '.uniqid());

    $invoice = function (string $date, int $invoiceCategoryId, float $amount) use ($customer) {
        $invoice = StoreInvoice::make()->action($customer, [...Invoice::factory()->definition(), 'date' => $date, 'in_process' => false]);
        DB::table('invoices')->where('id', $invoice->id)->update(['invoice_category_id' => $invoiceCategoryId, 'org_net_amount' => $amount, 'in_process' => false]);
    };
    $invoice('2037-05-12', $retail->id, 750);
    $invoice('2037-05-20', $partners->id, 250);
    $invoice('2038-05-03', $retail->id, 300);
    $invoice('2038-05-04', $partners->id, 200);

    $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    $timeSeries->records()->updateOrCreate(['period' => '2037-05-12', 'frequency' => 'D'], ['sales_org_currency_external' => 750, 'sales_org_currency_internal' => 0]);
    $timeSeries->records()->updateOrCreate(['period' => '2037-05-20', 'frequency' => 'D'], ['sales_org_currency_external' => 0, 'sales_org_currency_internal' => 250]);
    $timeSeries->records()->updateOrCreate(['period' => '2038-05-03', 'frequency' => 'D'], ['sales_org_currency_external' => 300, 'sales_org_currency_internal' => 0]);
    $timeSeries->records()->updateOrCreate(['period' => '2038-05-04', 'frequency' => 'D'], ['sales_org_currency_external' => 0, 'sales_org_currency_internal' => 200]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);
    $byCategory = collect($block['children'])->keyBy('invoice_category_id');

    expect($block['sales_so_far'])->toBe(500.0)
        ->and($block['last_year_total'])->toBe(1000.0)
        ->and($block['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($block['target']['is_sum_of_categories'])->toBeTrue()
        ->and($block['selected_child'])->toBe('all')
        ->and($block['selection_setting'])->toBe('shop_target_category_'.$shop->id)
        ->and($byCategory[$retail->id])->toMatchArray(['key' => (string) $retail->id, 'name' => $retail->name, 'sales_so_far' => 300.0, 'last_year_total' => 750.0, 'can_edit' => true])
        ->and($byCategory[$retail->id]['target']['is_share'])->toBeTrue()
        ->and($byCategory[$retail->id]['chart']['this_year'])->toHaveCount(10)
        ->and($byCategory[$retail->id]['target']['amount'])->toEqualWithDelta(750 * (1 + $growth), 0.05)
        ->and($byCategory[$partners->id]['target']['amount'])->toEqualWithDelta(250 * (1 + $growth), 0.05);

    $organisationMonth  = GetShopMonthSalesTarget::run($this->organisation, null, $today);
    $organisationTarget = $organisationMonth['target']['amount'];
    $shopChildren       = collect($organisationMonth['children'])->keyBy('key');

    expect($shopChildren->has((string) $shop->id))->toBeFalse()
        ->and($shopChildren[$shop->id.'-'.$retail->id])->toMatchArray(['name' => $shop->name, 'sales_so_far' => 300.0, 'can_edit' => false, 'update_route' => null])
        ->and($shopChildren[$shop->id.'-'.$partners->id])->toMatchArray(['name' => $shop->name.' · '.$partners->name, 'sales_so_far' => 200.0])
        ->and($shopChildren[$shop->id.'-'.$partners->id]['link']['parameters'])->toMatchArray(['shop' => $shop->slug, 'section' => 'target'])
        ->and($shopChildren[$shop->id.'-'.$retail->id]['target']['amount'] + $shopChildren[$shop->id.'-'.$partners->id]['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth), 0.05);

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 500, 'month' => '2038-05', 'invoice_category_id' => $partners->id]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['amount'])->toEqualWithDelta(750 * (1 + $growth) + 500, 0.05)
        ->and(collect($block['children'])->firstWhere('invoice_category_id', $partners->id)['target'])->toMatchArray(['amount' => 500.0, 'is_share' => false])
        ->and(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta($organisationTarget + 500 - 250 * (1 + $growth), 0.05);

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 2000, 'month' => '2038-05']);

    expect(collect(GetShopMonthSalesTarget::run($shop, null, $today)['children'])->firstWhere('invoice_category_id', $retail->id)['target']['amount'])->toEqualWithDelta(1500, 0.05)
        ->and(GetShopYearSalesTarget::run($shop, null, $today)['target']['months_set'])->toBe(1);

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['settings' => ['shop_target_category_'.$shop->id => (string) $partners->id]])->assertSuccessful();

    expect(GetShopMonthSalesTarget::run($shop, $this->user->fresh(), $today)['selected_child'])->toBe((string) $partners->id)
        ->and(GetShopMonthSalesTarget::run($shop, $this->user->fresh(), $today)['selected_period'])->toBe('month');

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['settings' => ['sales_target_period' => 'year']])->assertSuccessful();

    expect(GetShopMonthSalesTarget::run($shop, $this->user->fresh(), $today))->toMatchArray(['selected_period' => 'year', 'selected_child' => (string) $partners->id]);

    $movedToOwnShop = $category('Faire '.uniqid());
    DB::table('invoice_categories')->where('id', $movedToOwnShop->id)->update(['settings' => json_encode(['shop_ids' => [$shop->id + 1000]])]);
    $invoice('2037-05-15', $movedToOwnShop->id, 400);
    Cache::tags(["dashboard-shop-$shop->id"])->flush();

    $block = GetShopMonthSalesTarget::run($shop, null, $today);

    expect(collect($block['children'])->pluck('invoice_category_id'))->not->toContain($movedToOwnShop->id)
        ->and($block['target']['amount'])->toEqualWithDelta(2000, 0.05)
        ->and(collect($block['children'])->sum('target.amount'))->toEqualWithDelta($block['target']['amount'], 0.05)
        ->and(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta($organisationTarget - 1000 * (1 + $growth) + 2000, 0.05);

    $shop->stats->update(['sales_forecast' => ['version' => '3', 'from' => '2038-05-10', 'org' => collect(range(10, 31))->mapWithKeys(fn (int $day) => [sprintf('2038-05-%02d', $day) => [10.0, 4.0]])->all(), 'grp' => null]]);
    $block      = GetShopMonthSalesTarget::run($shop, null, $today);
    $byCategory = collect($block['children'])->keyBy('invoice_category_id');

    expect($block['expected'])->toEqual(500 + 21 * 10)
        ->and(array_sum(array_column($block['children'], 'expected')))->toEqualWithDelta(500 + 21 * 10, 0.01)
        ->and($byCategory[$retail->id]['expected'])->toEqualWithDelta(300 + 210 * 630 / 1050, 0.01);

    $shop->update(['state' => ShopStateEnum::CLOSED]);
});

test('each morning a tip on reaching the target is written for the shop and shown on its target block', function () {
    $shop  = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $today = Carbon::parse('2039-05-06', 'UTC');

    ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY])
        ->records()->updateOrCreate(['period' => '2038-05-12', 'frequency' => 'D'], ['sales_org_currency_external' => 1000]);

    AskToAi::shouldRun()->once()->andReturn('Call the customers due to reorder today.');

    expect(GenerateSalesTargetTips::run($shop, $today))->toBe(1);

    $growth = (float) config('marketing.default_sales_target_growth');
    $tip    = SalesTargetTip::where('shop_id', $shop->id)->sole();
    $block  = GetShopMonthSalesTarget::run($shop, null, $today);

    expect($tip->invoice_category_id)->toBeNull()
        ->and($tip->facts['target'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($tip->facts)->toHaveKeys(['customers_due_to_reorder_within_a_week', 'open_baskets_amount', 'customers_who_bought_same_month_last_year_not_yet_this_month'])
        ->and($block['tip'])->toBe('Call the customers due to reorder today.')
        ->and($block['needed_per_day'])->toEqualWithDelta(1000 * (1 + $growth) / 25, 0.05)
        ->and($block['needed_this_week'])->toEqualWithDelta(1000 * (1 + $growth) * 3 / 26, 0.05)
        ->and(GetShopMonthSalesTarget::run($shop, null, $today->copy()->addDay())['tip'])->toBeNull();

    $shop->update(['state' => ShopStateEnum::CLOSED]);
});

test('stock tab shows stock health by days of cover; warehouse work lives on operations', function () {
    createWarehouse();
    Cache::forget(GetGroupWarehouseDashboardData::cacheKey($this->group, $this->group->organisations()->where('type', OrganisationTypeEnum::SHOP)->pluck('id')->all()));

    $overview        = GetGroupWarehouseDashboardData::run($this->group->refresh());
    $organisationRow = collect($overview['organisations'])->firstWhere('slug', $this->organisation->slug);

    expect(array_keys($overview['totals']))->toBe(['stock_health'])
        ->and(array_keys($organisationRow['stock_health']))->toBe(array_keys(GetOrganisationStockCoverBuckets::BUCKETS))
        ->and($organisationRow['routes']['stock_health']['name'])->toBe('grp.org.procurement.stock_cover.index')
        ->and(collect($overview['stock_levels'])->pluck('bucket')->all())->toBe(array_keys(GetOrganisationStockCoverBuckets::BUCKETS));

    actingAs($this->user);
    get(route('grp.dashboard.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('warehouseOverview')->where('operations.route.name', 'grp.dashboard.operations'));
});

test('a stock health level opens as one list across every shop organisation', function () {
    createWarehouse();
    actingAs($this->user);

    $overview = GetGroupWarehouseDashboardData::run($this->group->refresh());
    expect($overview['routes']['stock_levels']['name'])->toBe('grp.dashboard.stock_levels.index');

    $expectedOutOfStock = $this->group->organisations()->where('type', OrganisationTypeEnum::SHOP)->get()
        ->sum(fn ($organisation) => collect(GetOrganisationStockCoverBuckets::run($organisation))->firstWhere('bucket', 'out')['count']);

    get(route('grp.dashboard.stock_levels.index', ['elements' => ['cover' => 'out']]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/OrganisationStockCoverItems')
            ->where('data.meta.total', $expectedOutOfStock)
            ->where('exportRoute', null)
            ->where('currency', null));

    $outsider = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    actingAs($outsider);
    get(route('grp.dashboard.stock_levels.index'))->assertForbidden();
});

test('warehouse page opens on its own operations tab', function () {
    $warehouse = createWarehouse();
    actingAs($this->user);

    get(route('grp.org.warehouses.show.infrastructure.dashboard', [$warehouse->organisation->slug, $warehouse->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Warehouse/Warehouse')
            ->where('tabs.current', 'operations')
            ->where('operations.warehouse', $warehouse->id)
            ->where('operations.route.name', 'grp.dashboard.operations'));

    getJson(route('grp.dashboard.operations', ['warehouse' => $warehouse->id, 'remember' => 0]))
        ->assertOk()
        ->assertJsonPath('filters.warehouse', $warehouse->id)
        ->assertJsonCount(1, 'warehouses');
});

test('operations dashboard numbers open lists holding exactly what was counted', function () {
    $warehouse = createWarehouse();
    actingAs($this->user);

    $figures = getJson(route('grp.dashboard.operations', ['warehouse' => $warehouse->id, 'remember' => 0]))->assertOk()->json();

    $counted = [
        'age_under_4h'      => $figures['age_buckets']['under_4h'],
        'age_4_24h'         => $figures['age_buckets']['h4_24'],
        'age_1_2d'          => $figures['age_buckets']['d1_2'],
        'age_over_2d'       => $figures['age_buckets']['over_2d'],
        'dispatched'        => $figures['time_to_dispatch']['records'],
        'not_counted'       => $figures['stock']['not_audited_90d'],
        'picked_today'      => $figures['people']['pickers']['records'],
        'short_today'       => $figures['people']['pickers']['short_records'],
        'packed_today'      => $figures['people']['packers']['records'],
    ];

    foreach ($counted as $list => $tile) {
        $listRoute = $tile['breakdown'][0]['route'];
        expect($listRoute['parameters']['list'])->toBe($list);
        get(route($listRoute['name'], $listRoute['parameters']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Warehouse/OperationsRecords')->where('data.meta.total', $tile['count']));
    }

    expect($figures['returns']['processed']['breakdown'][0]['route']['parameters']['list'])->toBe('returns_processed');
    foreach (['returns_processed', 'return_reasons'] as $list) {
        get(route('grp.org.warehouses.show.operations.records', [$warehouse->organisation->slug, $warehouse->slug, $list, 'reason' => 'damaged']))->assertOk();
    }

    $unrelated = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($unrelated);
    get(route('grp.org.warehouses.show.operations.records', [$warehouse->organisation->slug, $warehouse->slug, 'not_counted']))->assertForbidden();
});

test('operations dashboard: warehouse staff land on it, only see their warehouse and no money', function () {
    $warehouse = createWarehouse();
    setPermissionsTeamId($this->group->id);
    SeedWarehousePermissions::run($warehouse);

    $supervisor = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    $supervisor->givePermissionTo("supervisor-dispatching.$warehouse->id");
    SetUserAuthorisedModels::run($supervisor);
    $supervisor->refresh();
    actingAs($supervisor);

    get(route('grp.dashboard.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/GrpDashboard')->where('operations.section', 'operations'));

    $response = getJson(route('grp.dashboard.operations'))->assertOk();
    expect(collect($response->json('filters.options.warehouses'))->pluck('id')->all())->toBe([$warehouse->id])
        ->and($response->json('can_see_value'))->toBeFalse()
        ->and($response->json('pipeline.unassigned.amount'))->toBeNull()
        ->and($response->json('pipeline.unassigned.count'))->toBe(DB::table('delivery_notes')->where('warehouse_id', $warehouse->id)->whereNull('deleted_at')->where('state', 'unassigned')->count())
        ->and($response->json('sales.rows.0.value_today'))->toBeNull()
        ->and(array_keys($response->json('attention')))->toBe(['urgent', 'at_risk', 'blocked', 'customer_service', 'out_of_stock', 'replenishment', 'overdue', 'stock_errors'])
        ->and($response->json('attention.blocked.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes')
        ->and($response->json('goods_in.counts.to_book_in.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.incoming.stock_deliveries.index')
        ->and($response->json('goods_in.overdue.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.incoming.stock_deliveries.index')
        ->and($response->json('stock.negative.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index')
        ->and($response->json('stock.replenishment.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.inventory.org_stocks.replenishments.index')
        ->and($response->json('returns.received.breakdown.0.route.name'))->toBe('grp.org.warehouses.show.incoming.returns.index');

    getJson(route('grp.dashboard.operations', ['channel' => 'b2b', 'period' => 7, 'remember' => 1]))
        ->assertOk()
        ->assertJsonPath('filters.channel', 'b2b')
        ->assertJsonPath('filters.period', 7);
    expect($supervisor->refresh()->settings[GetOperationsDashboardData::SETTINGS_KEY]['channel'])->toBe('b2b');
    getJson(route('grp.dashboard.operations'))->assertJsonPath('filters.channel', 'b2b');
    getJson(route('grp.dashboard.operations', ['warehouse' => '', 'channel' => '', 'period' => 30, 'remember' => 1]))->assertOk();
    getJson(route('grp.dashboard.operations'))->assertJsonPath('filters.channel', null)->assertJsonPath('filters.warehouse', null);

    $unrelated = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser();
    actingAs($unrelated);
    getJson(route('grp.dashboard.operations'))->assertForbidden();
});

test('shop dashboard tab data serves the sub-departments table', function () {
    getJson(route('grp.org.shops.show.dashboard.tab-data', [$this->organisation->slug, $this->shop->slug, 'tab' => 'sub_departments']))
        ->assertOk()
        ->assertJsonPath('tab', 'sub_departments')
        ->assertJsonPath('table.header.columns.label.formatted_value', 'Sub-department');
});

test('only organisation or group admins can change the shop sales target', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $routeParameters = ['organisation' => $this->shop->organisation_id, 'shop' => $this->shop->id];

    $webmaster = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $webmaster->givePermissionTo('web.'.$this->shop->id.'.view');
    actingAs($webmaster);
    patchJson(route('grp.models.org.shop.sales_target.update', $routeParameters), ['target_org_currency' => 1])->assertForbidden();

    $admin = $this->user;
    $admin->givePermissionTo('org-admin.'.$this->shop->organisation_id);
    actingAs($admin);
    patchJson(route('grp.models.org.shop.sales_target.update', $routeParameters), ['target_org_currency' => 50000])->assertSuccessful();

    expect(ShopSalesTarget::where('shop_id', $this->shop->id)->where('month', now('UTC')->startOfMonth()->toDateString())->first())
        ->target_org_currency->toBe('50000.00')
        ->set_by_user_id->toBe($admin->id);
});

test('dropshipping shops get sales channels and platforms, wholesale shops get customers', function () {
    $dropshipping = Shop::factory()->make(['type' => ShopTypeEnum::DROPSHIPPING]);
    $wholesale    = Shop::factory()->make(['type' => ShopTypeEnum::B2B]);

    expect(array_keys(ShopDashboardSectionsEnum::navigation($dropshipping)))->toBe(['target', 'sales', 'sales_analysis', 'sales_channels', 'platforms', 'marketing'])
        ->and(array_keys(ShopDashboardSectionsEnum::navigation($wholesale)))->toBe(['target', 'sales', 'sales_analysis', 'customers', 'marketing'])
        ->and(ShopDashboardSectionsEnum::current($wholesale, ['shop_dashboard_section' => 'platforms']))->toBe('target')
        ->and(ShopDashboardSectionsEnum::current($dropshipping, ['shop_dashboard_section' => 'platforms']))->toBe('platforms')
        ->and(ShopDashboardSectionsEnum::current($wholesale, ['shop_dashboard_section' => 'customers'], 'sales_analysis'))->toBe('sales_analysis');
});

test('sales analysis lives in the shop dashboard sales analysis tab', function () {
    get(route('grp.org.shops.show.dashboard.sales_analysis', [$this->organisation->slug, $this->shop->slug, 'from' => '2026-01-01']))
        ->assertRedirect(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'sales_analysis', 'from' => '2026-01-01']));

    get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'sales_analysis']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.super_blocks.0.sections.current', 'sales_analysis')
            ->missing('sales_analysis')
            ->reloadOnly('sales_analysis', fn (AssertableInertia $reload) => $reload->has('sales_analysis')));
});

test('shop customers tab reads the hydrated snapshot and matches sister shop buyers by email', function () {
    $otherShop = Shop::where('id', '!=', $this->shop->id)->where('group_id', $this->shop->group_id)->first()
        ?? StoreShop::make()->action($this->organisation, Shop::factory()->definition());

    $this->customer->update(['email' => 'Twin.Buyer@example.com', 'trade_state' => CustomerTradeStateEnum::MANY]);
    $twin = createCustomer($otherShop);
    $twin->update(['email' => '  twin.buyer@EXAMPLE.com ', 'trade_state' => CustomerTradeStateEnum::ONE]);

    $this->shop->crmStats()->update(['customers_dashboard' => null]);
    expect(GetShopCustomersDashboard::run($this->shop->refresh()))->toBe(['pending' => true]);

    ShopHydrateCustomersDashboard::run($this->shop);
    $data = GetShopCustomersDashboard::run($this->shop->refresh());

    expect($data['base'])->toHaveKeys(['ordered', 'active', 'losing', 'lost', 'never_ordered'])
        ->and($data['this_month'])->toHaveKeys(['registrations', 'registrations_with_orders'])
        ->and($data['problems'])->toHaveKeys(['conversations', 'classified', 'problems', 'by_topic'])
        ->and($data['problems_month_to_date'])->toHaveKeys(['since', 'problems', 'by_topic'])
        ->and($data['sister_shops']['shared_buyers'])->toBeGreaterThanOrEqual(1)
        ->and(collect($data['sister_shops']['shops'])->pluck('code'))->toContain($otherShop->code);

    get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'customers']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('customers_dashboard')
            ->reloadOnly('customers_dashboard', fn (AssertableInertia $reload) => $reload->has('customers_dashboard.sister_shops')));
});

test('shop dashboard widgets compute only the widgets a tab asks for', function () {
    $response = getJson(route('grp.org.shops.show.dashboard.widgets', [$this->organisation->slug, $this->shop->slug, 'only' => 'top_products,top_families,department_movers,family_movers,out_of_stock,out_of_stock_month,problems_month,customer_actions']))
        ->assertOk();

    expect($response->json())->toHaveKeys(['top_products', 'top_families', 'department_movers.growing', 'department_movers.falling', 'family_movers.period', 'out_of_stock.products', 'out_of_stock.estimated_lost', 'out_of_stock.rows', 'out_of_stock_month.estimated_lost', 'customer_actions.at_risk', 'customer_actions.overdue', 'routes'])
        ->not->toHaveKeys(['marketing', 'email', 'channels']);
});

test('department movers compare last year sales under the department each product is in now', function () {
    $today = now('UTC')->startOfDay();
    $from  = $today->day < 7 ? $today->copy()->subMonthNoOverflow()->startOfMonth() : $today->copy()->startOfMonth();
    $day   = $from->copy()->subYear();

    $seriesId = DB::table('asset_time_series')
        ->where('asset_id', $this->product->asset_id)
        ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)
        ->value('id') ?? DB::table('asset_time_series')->insertGetId([
            'asset_id'  => $this->product->asset_id,
            'shop_id'   => $this->shop->id,
            'frequency' => TimeSeriesFrequencyEnum::DAILY->value,
        ]);

    $record = [
        'asset_time_series_id' => $seriesId,
        'frequency'            => TimeSeriesFrequencyEnum::DAILY->singleLetter(),
        'period'               => $day->format('Y-m-d'),
    ];
    $original = DB::table('asset_time_series_records')->where($record)->value('sales_external');

    DB::table('asset_time_series_records')->updateOrInsert($record, [
        'sales_external' => (float) $original + 1_000_000_000,
        'sales_internal' => 0,
        'from'           => $day->copy()->startOfDay(),
        'to'             => $day->copy()->endOfDay(),
    ]);

    Cache::tags(["dashboard-shop-{$this->shop->id}"])->flush();
    $falling = getJson(route('grp.org.shops.show.dashboard.widgets', [$this->organisation->slug, $this->shop->slug, 'only' => 'department_movers']))
        ->assertOk()
        ->json('department_movers.falling');

    $original === null
        ? DB::table('asset_time_series_records')->where($record)->delete()
        : DB::table('asset_time_series_records')->where($record)->update(['sales_external' => $original]);
    Cache::tags(["dashboard-shop-{$this->shop->id}"])->flush();

    expect($falling[0]['slug'])->toBe($this->department->slug)
        ->and($falling[0]['sales_last_year'])->toBeGreaterThanOrEqual(1_000_000_000);
});

test('a compliance editor sees supply chain, goods and products, and edits only compliance information', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn () => StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    $tradeUnit  = $this->product->tradeUnits()->first();
    $compliance = $newUser();
    $compliance->givePermissionTo(['compliance.view', 'compliance.edit']);
    actingAs($compliance);

    get(route('grp.supply-chain.supplier_products.index'))->assertOk();
    get(route('grp.goods.trade-units.show', [$tradeUnit->slug]))->assertOk();
    get(route('grp.goods.trade-units.edit', [$tradeUnit->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->collapse()->keys()->doesntContain('name')
                && collect($blueprint)->pluck('fields')->collapse()->has('gpsr_warnings')));

    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'Keep away from children'])->assertSessionHasNoErrors();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['name' => 'Renamed by compliance'])->assertSessionHasErrors('name');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_manual' => 'Read first', 'name' => 'Renamed by compliance'])->assertSessionHasErrors('name');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['label_info_approved' => true])->assertSessionHasErrors('label_info_approved');
    patch(route('grp.models.product.update', $this->product->id), ['marketing_weight' => 321])->assertSessionHasNoErrors();
    patch(route('grp.models.product.update', $this->product->id), ['price' => 1])->assertSessionHasErrors('price');

    expect($tradeUnit->refresh()->gpsr_warnings)->toBe('Keep away from children')
        ->and($tradeUnit->name)->not->toBe('Renamed by compliance')
        ->and((int) $this->product->refresh()->marketing_weight)->toBe(321);

    expect($tradeUnit->refresh()->gpsr_manual)->not->toBe('Read first');

    $compliance->givePermissionTo('compliance.publish');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['label_info_approved' => true])->assertSessionHasNoErrors();

    $viewer = $newUser();
    $viewer->givePermissionTo('compliance.view');
    actingAs($viewer);
    get(route('grp.goods.trade-units.show', [$tradeUnit->slug]))->assertOk();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'x'])->assertForbidden();

    $unrelated = $newUser();
    $unrelated->givePermissionTo('human-resources.'.$this->organisation->id.'.view');
    actingAs($unrelated);

    patch(route('grp.models.product.update', $this->product->id), ['price' => 1])->assertForbidden();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'x'])->assertForbidden();
});

test('a compliance editor pastes an ingredients list onto a trade unit', function () {
    setPermissionsTeamId($this->group->id);
    $newUser = fn () => StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    $tradeUnit  = $this->product->tradeUnits()->first();
    $ingredient = 'Compliance pasted '.Str::random(8);

    $viewer = $newUser();
    $viewer->givePermissionTo('compliance.view');
    actingAs($viewer);
    postJson(route('grp.json.ingredients.parse'), ['text' => $ingredient])->assertForbidden();

    $compliance = $newUser();
    $compliance->givePermissionTo(['compliance.view', 'compliance.edit']);
    actingAs($compliance);

    postJson(route('grp.json.ingredients.parse'), ['text' => $ingredient.'*'])
        ->assertOk()
        ->assertJsonPath('0.name', $ingredient)
        ->assertJsonPath('0.is_new', true);

    $slug = postJson(route('grp.json.ingredients.parse'), ['text' => $ingredient, 'commit' => true])
        ->assertOk()
        ->json('0.slug');

    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['ingredients' => [$slug]])->assertSessionHasNoErrors();

    expect($tradeUnit->refresh()->ingredients()->pluck('slug')->all())->toBe([$slug]);
});

test('only goods, masters or media editors change trade unit media, and attachments follow the edit permission of their model', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn (array $permissions) => tap(
        StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser()
    )->givePermissionTo($permissions);

    $tradeUnit = $this->product->tradeUnits()->first();
    $pdf       = fn () => ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('terms.pdf', 10, 'application/pdf')], 'scope' => 'Other'];

    actingAs($newUser(["crm.{$this->shop->id}.edit"]));
    $this->post(route('grp.models.customer.attachment.attach', ['customer' => $this->customer->id]), $pdf())->assertSessionHasNoErrors();
    $attachment = $this->customer->attachments()->first();
    expect($attachment)->not->toBeNull();

    $mediaRoutes = [
        ['post', route('grp.models.trade-unit.upload_images', $tradeUnit->id)],
        ['post', route('grp.models.trade-unit.attach_images', $tradeUnit->id)],
        ['post', route('grp.models.trade-unit.upload_audio', $tradeUnit->id)],
        ['patch', route('grp.models.trade-unit.update_images', $tradeUnit->id)],
        ['patch', route('grp.models.trade-unit.update_image_alt', [$tradeUnit->id, $attachment->id])],
        ['delete', route('grp.models.trade-unit.detach_image', [$tradeUnit->id, $attachment->id])],
    ];

    actingAs($newUser(['goods.view', 'masters.view', "crm.{$this->shop->id}.view"]));
    foreach ($mediaRoutes as [$method, $url]) {
        $this->{$method}($url)->assertForbidden();
    }
    $this->post(route('grp.models.customer.attachment.attach', ['customer' => $this->customer->id]), $pdf())->assertForbidden();
    $this->delete(route('grp.models.customer.attachment.detach', [$this->customer->id, $attachment->id]))->assertForbidden();
    expect($this->customer->attachments()->count())->toBe(1);

    foreach (['goods.edit', 'masters.edit', 'group-webmaster.media-edit'] as $permission) {
        actingAs($newUser([$permission]));
        $this->post(route('grp.models.trade-unit.upload_images', $tradeUnit->id))->assertSessionHasErrors('images');
        $this->post(route('grp.models.trade-unit.upload_audio', $tradeUnit->id))->assertSessionHasErrors('audio');
    }

    actingAs($newUser(["crm.{$this->shop->id}.edit"]));
    $this->delete(route('grp.models.customer.attachment.detach', [$this->customer->id, $attachment->id]))->assertSuccessful();
    expect($this->customer->attachments()->count())->toBe(0);
});

test('accounts can edit billables, staff without product or accounting edit cannot', function () {
    setPermissionsTeamId($this->group->id);
    $newUser = function (array $permissions) {
        $user = StoreGuest::make()->action(
            $this->group,
            array_merge(Guest::factory()->definition(), ['positions' => []])
        )->getUser();
        $user->givePermissionTo($permissions);

        return $user->refresh();
    };

    $accountsViewer = $newUser(["accounting.{$this->organisation->id}.view"]);
    expect(GetShopNavigation::run($this->shop, $accountsViewer))->toHaveKey('billables')
        ->and(GetShopNavigation::run($this->shop, $newUser([])))->not->toHaveKey('billables');

    actingAs($accountsViewer);
    get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0', false));
    patch(route('grp.models.shop.services.update', $this->service->id), ['name' => 'Viewer rename'])->assertForbidden();
    patch(route('grp.models.charge.update', $this->charge->id), ['name' => 'Viewer rename'])->assertForbidden();

    actingAs($newUser(["accounting.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.edit"]));
    get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0.style', 'edit'));
    get(route('grp.org.shops.show.billables.services.edit', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))->assertOk();
    patch(route('grp.models.shop.services.update', $this->service->id), ['name' => 'Accounts rename'])->assertSessionHasNoErrors();
    patch(route('grp.models.charge.update', $this->charge->id), ['name' => 'Accounts rename'])->assertSessionHasNoErrors();

    expect($this->service->refresh()->name)->toBe('Accounts rename')
        ->and($this->charge->refresh()->name)->toBe('Accounts rename');
});

test('catalogue top listed and top sold tabs read the hourly rankings with the same totals as the live query', function () {
    $platform = $this->group->platforms()->where('type', \App\Enums\Ordering\Platform\PlatformTypeEnum::MANUAL)->firstOrFail();
    $customers = [$this->customer, createCustomer($this->shop)];
    foreach ($customers as $index => $customer) {
        $channel = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'rankings-'.$index]);
        \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($channel, $this->product, []);
    }

    \App\Actions\Catalogue\RebuildCatalogueRankings::run();
    actingAs($this->user);

    $rows = function (string $url, string $tab, array $columns) {
        return collect(get($url)->assertOk()->viewData('page')['props'][$tab]['data'])
            ->map(fn (array $row) => array_map(fn ($value) => is_numeric($value) ? (float) $value : $value, \Illuminate\Support\Arr::only($row, $columns)))
            ->sortBy('id')->values()->all();
    };

    $everything = '20000101-20991231';
    foreach (
        [
            route('grp.dashboard.catalogue.show'),
            route('grp.org.shops.show.catalogue.dashboard', [$this->organisation->slug, $this->shop->slug]),
        ] as $url
    ) {
        foreach (
            [
                'top_listed_families' => [['id', 'total_listed', 'total_customers'], 'created_at'],
                'top_listed_products' => [['id', 'total_listed', 'total_customers'], 'created_at'],
                'top_sold_products'   => [['id', 'total_sold', 'total_amount'], 'date'],
            ] as $tab => [$columns, $dateColumn]
        ) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $ranked    = $rows($url.$separator.'tab='.$tab, $tab, $columns);
            $live      = $rows($url.$separator.'tab='.$tab.'&between['.$dateColumn.']='.$everything, $tab, $columns);

            expect($ranked)->toBe($live);
        }
    }

    $listedFamilies = $rows(route('grp.dashboard.catalogue.show').'?tab=top_listed_families', 'top_listed_families', ['id', 'total_listed', 'total_customers']);
    expect(collect($listedFamilies)->firstWhere('id', (float) $this->family->id))->toMatchArray(['total_listed' => 2.0, 'total_customers' => (float) collect($customers)->unique('id')->count()]);
});

test('platform and country top listed and top sold tabs count portfolios and invoice lines against the product they belong to', function () {
    $platform = $this->group->platforms()->where('type', \App\Enums\Ordering\Platform\PlatformTypeEnum::MANUAL)->firstOrFail();
    actingAs($this->user);

    $platformUrl = fn (string $tab) => route('grp.org.shops.show.crm.platforms.show', [$this->organisation->slug, $this->shop->slug, $platform->slug, 'tab' => $tab]);
    $rowFor      = fn (string $url, string $tab, int $id) => collect(get($url)->assertOk()->viewData('page')['props'][$tab]['data'])->firstWhere('id', $id) ?? [];

    $before = [
        'families' => $rowFor($platformUrl('top_listed_families'), 'top_listed_families', $this->family->id)['total_listed'] ?? 0,
        'products' => $rowFor($platformUrl('top_listed_products'), 'top_listed_products', $this->product->asset_id)['total_listed'] ?? 0,
        'sold'     => $rowFor($platformUrl('top_sold_products'), 'top_sold_products', $this->product->asset_id)['total_sold'] ?? 0,
    ];

    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition());
    DB::table('customers')->where('id', $customer->id)->update(['location' => json_encode(['AQ', 'Antarctica', ''])]);

    foreach ([0, 1] as $index) {
        $channel = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'platform-top-'.$index]);
        \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($channel, $this->product, []);

        $invoice = StoreInvoice::make()->action($customer, [...Invoice::factory()->definition(), 'in_process' => false]);
        \App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction::make()->action($invoice, $this->product->historicAsset, [
            'date'            => now(),
            'tax_category_id' => $invoice->tax_category_id,
            'quantity'        => 3,
            'gross_amount'    => 10,
            'net_amount'      => 10,
        ]);
    }

    expect($rowFor($platformUrl('top_listed_families'), 'top_listed_families', $this->family->id)['total_listed'])->toEqual($before['families'] + 2)
        ->and($rowFor($platformUrl('top_listed_products'), 'top_listed_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_listed' => $before['products'] + 2])
        ->and($rowFor($platformUrl('top_sold_products'), 'top_sold_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_sold' => (float) ($before['sold'] + 6)]);

    $countryUrl = route('grp.org.shops.show.crm.countries.show', [$this->organisation->slug, $this->shop->slug, 'AQ', 'tab' => 'top_products']);
    expect(get($countryUrl)->assertOk()->viewData('page')['props']['top_products']['data'])->toHaveCount(1)
        ->and($rowFor($countryUrl, 'top_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_sold' => 6, 'total_amount' => 20]);
});

test('reviews need website or CRM view to open and website or CRM edit to manage', function () {
    $originalRoles = $this->user->roles->pluck('name')->toArray();
    $parameters    = [$this->organisation->slug, $this->shop->slug];

    actingAsUserWithRoles($this->user, []);
    get(route('grp.org.shops.show.reviews.backlog', $parameters))->assertForbidden();

    actingAsUserWithRoles($this->user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop)]);
    get(route('grp.org.shops.show.reviews.backlog', $parameters))->assertOk();
    get(route('grp.org.shops.show.reviews.overall', $parameters))->assertOk();

    $review = \App\Models\Reviews\Review::where('shop_id', $this->shop->id)->first();
    if ($review) {
        $this->patch(route('grp.models.review.approve', ['review' => $review->id]))->assertForbidden();
        $this->patch(route('grp.models.review.reject', ['review' => $review->id]))->assertForbidden();
        $this->delete(route('grp.models.review.delete', ['review' => $review->id]))->assertForbidden();
    }

    actingAsUserWithRoles($this->user, $originalRoles);
});

test('a shop viewer sees billables but cannot open their create or edit forms', function () {
    $originalRoles = $this->user->roles->pluck('name')->toArray();
    actingAsUserWithRoles($this->user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop)]);
    $parameters = [$this->organisation->slug, $this->shop->slug];

    expect(get(route('grp.org.shops.show.billables.packagings.index', $parameters))->assertOk()->viewData('page')['props']['can_edit'])->toBeFalse()
        ->and(get(route('grp.org.shops.show.billables.shipping.index', $parameters))->assertOk()->viewData('page')['props']['pageHead']['actions'] ?? [])->toBeEmpty();

    get(route('grp.org.shops.show.billables.packagings.create', $parameters))->assertForbidden();
    get(route('grp.org.shops.show.billables.leaflets.create', $parameters))->assertForbidden();
    get(route('grp.org.shops.show.billables.shipping.create', $parameters))->assertForbidden();
    get(route('grp.org.shops.show.billables.charges.create', $parameters))->assertForbidden();

    actingAsUserWithRoles($this->user, $originalRoles);
});

test('a shop viewer sees category offers and related categories but none of the controls to change them', function () {
    $originalRoles = $this->user->roles->pluck('name')->toArray();
    actingAsUserWithRoles($this->user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(\App\Enums\SysAdmin\Authorisation\RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $this->shop)]);

    $departmentUrl = route('grp.org.shops.show.catalogue.departments.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug]);
    $props         = get($departmentUrl.'?tab=related_product_category')->assertOk()->viewData('page')['props'];

    expect($props['can_edit_offers'])->toBeFalse()
        ->and($props['related_product_category']['editable'])->toBeFalse();

    $this->patch(route('grp.models.product_category.related_product_categories.sync', ['productCategory' => $this->department->id]), ['related_product_categories_id' => []])->assertForbidden();
    $this->post(route('grp.models.category_offer.store', ['shop' => $this->shop->id]), [])->assertForbidden();
    $this->post(route('grp.models.bogo_offer.store', ['shop' => $this->shop->id]), [])->assertForbidden();

    $subDepartment = ProductCategory::where('shop_id', $this->shop->id)->where('type', \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::SUB_DEPARTMENT)->first();
    if ($subDepartment) {
        $this->post(route('grp.models.sub-department.families.attach', ['subDepartment' => $subDepartment->id]), ['families_id' => []])->assertForbidden();
    }

    $family = $this->product->family;
    if ($family) {
        $this->patch(route('grp.models.product_category.reorder_index', ['productCategory' => $family->id]), [])->assertForbidden();
        $this->patch(route('grp.models.product_category.repair_product_images', ['productCategory' => $family->id]))->assertForbidden();
    }
    $this->post(route('grp.models.gift_offer.store', ['shop' => $this->shop->id]), [])->assertForbidden();
    $this->post(route('grp.models.step_discount.store', ['shop' => $this->shop->id]), [])->assertForbidden();

    $collection = \App\Models\Catalogue\Collection::where('shop_id', $this->shop->id)->first();
    if ($collection) {
        $this->patch(route('grp.models.collection.webpage_disable', ['collection' => $collection->id]), [])->assertForbidden();
    }

    $productProps = get(route('grp.org.shops.show.catalogue.products.all_products.show', [$this->organisation->slug, $this->shop->slug, $this->product->slug]))->assertOk()->viewData('page')['props'];
    expect($productProps['can_edit'])->toBeFalse()
        ->and($productProps['can_edit_offers'])->toBeFalse();

    actingAsUserWithRoles($this->user, $originalRoles);
});

test('family edit needs catalogue edit and the shop dashboard needs a permission for the shop', function () {
    $shop       = $this->shop;
    $parameters = [$this->organisation->slug, $shop->slug];
    $familyEdit = route('grp.org.shops.show.catalogue.families.edit', [...$parameters, $this->family->slug]);
    $dashboards = [
        route('grp.org.shops.show.dashboard.show', $parameters),
        route('grp.org.shops.show.dashboard.brands', $parameters),
        route('grp.org.shops.show.dashboard.sales_analysis', $parameters),
    ];
    $originalRoles       = $this->user->roles->pluck('name')->toArray();
    $originalPermissions = $this->user->getDirectPermissions()->pluck('name')->all();

    try {
        actingAsUserWithOnlyPermissions($this->user, ["products.$shop->id.view"]);
        get($familyEdit)->assertForbidden();
        get($dashboards[0])->assertOk();
        get($dashboards[1])->assertOk();
        get($dashboards[2])->assertRedirect();

        actingAsUserWithOnlyPermissions($this->user, ["products.$shop->id.edit"]);
        get($familyEdit)->assertOk();

        actingAsUserWithOnlyPermissions($this->user, ["human-resources.{$this->organisation->id}.view", "shops-view.{$this->organisation->id}"]);
        foreach ($dashboards as $dashboard) {
            get($dashboard)->assertForbidden();
        }

        actingAsUserWithOnlyPermissions($this->user, ["accounting.{$this->organisation->id}.view"]);
        get($dashboards[0])->assertOk();
    } finally {
        setPermissionsTeamId($this->user->group_id);
        $this->user->syncPermissions($originalPermissions);
        actingAsUserWithRoles($this->user, $originalRoles);
        \App\Actions\SysAdmin\User\SetUserAuthorisedModels::run($this->user);
    }
});

test('shop and organisation dashboard data answer only staff of the shop or organisation, INI-076', function () {
    $org  = $this->organisation;
    $shop = $this->shop;
    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $org->group_id, 'status' => true]);

    $shopRoutes = [
        route('grp.org.shops.show.dashboard.widgets', [$org->slug, $shop->slug, 'only' => 'top_products']),
        route('grp.org.shops.show.dashboard.tab-data', [$org->slug, $shop->slug, 'tab' => 'sub_departments']),
        route('grp.org.shops.show.dashboard.changelogs.index', [$org->slug, $shop->slug]),
    ];
    $orgTabData = route('grp.org.dashboard.tab-data', [$org->slug, 'tab' => 'shops']);

    actingAsUserWithOnlyPermissions($user, ['goods.view']);
    foreach ([...$shopRoutes, $orgTabData] as $url) {
        getJson($url)->assertForbidden();
    }

    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);
    foreach ($shopRoutes as $url) {
        getJson($url)->assertOk();
    }

    actingAsUserWithOnlyPermissions($user, ["accounting.$org->id.view"]);
    foreach ([...$shopRoutes, $orgTabData] as $url) {
        getJson($url)->assertOk();
    }
});
