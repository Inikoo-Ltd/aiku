<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\Stock\StoreStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLeadTime;
use App\Actions\SupplyChain\SupplierProduct\Hydrators\SupplierProductHydrateLeadTime;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplierProducts\RepairOrgSupplierProductsSupplierDrift;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\Artefact\StoreArtefact;
use Illuminate\Support\Str;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Models\Production\Production;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItems;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\PartnerShoppingListItem\SuggestPartnerShoppingList;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Procurement\ShoppingListItem;
use App\Models\Catalogue\Shop;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\MergeSupplierProducts;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Goods\Stock;
use App\Enums\UI\SupplyChain\StockTabsEnum;
use App\Models\SysAdmin\Organisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Location;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Enums\Catalogue\HealthRankEnum;
use App\Actions\Procurement\OrgPartner\UI\ShowPartnerBrowse;
use Illuminate\Support\Facades\Cache;
use App\Actions\Procurement\OrgPartner\UpdatePartnerLeadTimeEstimate;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockStats;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ProcurementHelpers.php';

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation      = createOrganisation();
    $this->otherOrganisation = createOrganisation();
    $this->group             = group();
    $this->adminGuest        = createAdminGuest($this->organisation->group);


    $this->stocks    = createStocks($this->group);
    $this->orgStocks = createOrgStocks($this->organisation, $this->stocks);

    $agent = Agent::orderBy('id')->first();
    if (!$agent) {
        $modelData = Agent::factory()->definition();
        $agent     = StoreAgent::make()->action(
            group: $this->group,
            modelData: $modelData
        );
    }
    $this->agent = $agent;

    $orgAgent = OrgAgent::orderBy('id')->first();
    if (!$orgAgent) {
        $orgAgent = StoreOrgAgent::make()->action(
            $this->organisation,
            $this->agent,
            []
        );
    }

    $this->orgAgent = $orgAgent;


    $supplier = Supplier::where('agent_id', $this->agent->id)->first();
    if (!$supplier) {
        $storeData = Supplier::factory()->definition();
        $supplier  = StoreSupplier::make()->action(
            $this->agent,
            $storeData
        );
    }

    $this->supplier = $supplier;

    $stock = Stock::orderBy('id')->first();
    if (!$stock) {
        $storeData = Stock::factory()->definition();
        $stock     = StoreStock::make()->action(
            $this->organisation->group,
            $storeData
        );
    }

    $this->stock = $stock;

    $supplierProduct = SupplierProduct::where('supplier_id', $this->supplier->id)->orderBy('id')->first();
    if (!$supplierProduct) {
        $storeData = SupplierProduct::factory()->definition();
        data_set($storeData, 'stock_id', $this->stock->id);
        $supplierProduct = StoreSupplierProduct::make()->action(
            $this->supplier,
            $storeData
        );
    }

    $this->supplierProduct = $supplierProduct;

    $orgSupplier = OrgSupplier::where('supplier_id', $this->supplier->id)->orderBy('id')->first();
    if (!$orgSupplier) {
        $orgSupplier = StoreOrgSupplier::make()->action(
            $this->organisation,
            $this->supplier,
        );
    }

    $this->orgSupplier = $orgSupplier;

    $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $this->orgSupplier->id)
        ->where('supplier_product_id', $this->supplierProduct->id)
        ->orderBy('id')
        ->first();
    if (!$orgSupplierProduct) {
        $orgSupplierProduct = StoreOrgSupplierProduct::make()->action(
            $this->orgSupplier,
            $this->supplierProduct
        );
    }

    $this->orgSupplierProduct = $orgSupplierProduct;

    $orgPartner = OrgPartner::first();
    if (!$orgPartner) {
        $orgPartner = StoreOrgPartner::make()->action(
            $this->organisation,
            $this->otherOrganisation,
        );
    }

    $orgPartner->partner->update(['is_manufacturing_hub' => true]);
    $this->orgPartner = $orgPartner;

    $stockDelivery = StockDelivery::orderBy('id')->first();
    if (!$stockDelivery) {
        $stockDelivery = StoreStockDelivery::make()->action(
            $this->orgSupplier,
            [
                'reference' => 12345,
                'date'      => date('Y-m-d')
            ]
        );
    }

    $this->stockDelivery = $stockDelivery;

    $purchaseOrder = PurchaseOrder::orderBy('id')->first();
    if (!$purchaseOrder) {
        $purchaseOrder = StorePurchaseOrder::make()->action(
            $this->orgSupplier,
            PurchaseOrder::factory()->definition()
        );
    }

    $this->purchaseOrder = $purchaseOrder;

    Config::set(
        'inertia.pages.paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

describe('partner browse', function () {
    test('UI partner browse index requires procurement shop_id', function () {
        $partner = $this->orgPartner->partner;
        $originalSettings = $partner->settings;
        $partner->update(['settings' => Arr::except($originalSettings, 'procurement.shop_id')]);

        $response = $this->get(route('grp.org.procurement.org_partners.show.browse.index', [$this->organisation->slug, $this->orgPartner->id]));
        $response->assertNotFound();

        $partner->update(['settings' => $originalSettings]);
    });

    test('UI partner browse index returns catalogue tree when shop_id set', function () {
        $seller = $this->orgPartner->partner;
        $sellerShop = $seller->shops()->first();
        if (!$sellerShop) {
            $sellerShop = StoreShop::run($seller, Shop::factory()->definition());
        }

        [, $sellerProduct] = createProduct($sellerShop);
        createOrgStocks($this->orgPartner->organisation, [$sellerProduct->orgStocks()->first()->stock]);

        $originalSettings = $seller->settings;
        $settings = $originalSettings;
        data_set($settings, 'procurement.shop_id', $sellerShop->id);
        $seller->update(['settings' => $settings]);

        $response = $this->get(route('grp.org.procurement.org_partners.show.browse.index', [$this->organisation->slug, $this->orgPartner->id]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Procurement/PartnerBrowse')
                ->has('title')
                ->has('level')
                ->has('categories')
                ->has('collections')
                ->has('miniCart.count')
                ->has('miniCart.total')
                ->has('miniCart.items')
                ->has('miniCart.listRoute');
        });

        $seller->update(['settings' => $originalSettings]);
    });

    test('UI partner shopping dashboard renders', function () {
        $response = $this->get(route('grp.org.procurement.org_partners.show.shopping.dashboard', [$this->organisation->slug, $this->orgPartner->id]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Procurement/PartnerShoppingDashboard')
                ->has('title')
                ->has('stats')
                ->has('coverBuckets', 8)
                ->where('coverBuckets.0.bucket', 'out')
                ->where('coverBuckets.7.bucket', 'never')
                ->where('coverTotal', fn ($total) => $total === collect($page->toArray()['props']['coverBuckets'])->sum('count'))
                ->has('latePurchaseOrders')
                ->has('leadTime.days')
                ->where('leadTime.source', 'estimate')
                ->has('canEditLeadTime')
                ->has('coverBuckets.0.ranks')
                ->where('orgPartner.currency', $this->organisation->currency->code);
        });
    });

    test('partner lead time estimate is editable until history exists', function () {
        $original = $this->orgPartner->data;

        $updated = UpdatePartnerLeadTimeEstimate::make()->action($this->orgPartner, ['lead_time_days' => 21]);

        expect(Arr::get($updated->data, 'shopping.lead_time_days'))->toBe(21)
            ->and(GetPartnerLeadTime::run($updated))->toMatchArray(['days' => 21, 'source' => 'estimate']);

        $this->orgPartner->update(['data' => $original]);
    });

    test('partner order capacity bootstraps deterministically from forecast', function () {
        DB::beginTransaction();
        DB::table('stock_deliveries')
            ->where('organisation_id', $this->orgPartner->organisation_id)
            ->where('partner_id', $this->orgPartner->partner_id)
            ->update(['deleted_at' => now()]);

        $capacity = GetPartnerOrderCapacity::run($this->orgPartner);
        DB::rollBack();

        expect($capacity['partner_capacity'])
            ->toMatchArray(['delivers_to_us_per_30d' => null, 'source' => 'none'])
            ->and($capacity['warehouse'])->toHaveKeys([
                'total_locations',
                'empty_locations',
                'free_ratio',
                'inbound_open_po_lines',
                'partner_share_used',
                'partner_share_limit',
            ])
            ->and($capacity['blocked'])->toHaveKeys(['at_capacity', 'warehouse_full']);
    });

    test('shopping list adds are never blocked by the order budget', function () {
        $seller = $this->orgPartner->partner;
        $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
        [, $sellerProduct] = createProduct($sellerShop);
        $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerProduct->orgStocks()->first()->stock])[0];
        $this->buyerOrgStock = $buyerOrgStock;

        $this->buyerOrgStock->update(['quantity_available' => 10, 'health_rank' => null]);
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
        Cache::put("partner-order-capacity:{$this->orgPartner->id}", [
            ['delivers_to_us_per_30d' => 0.01, 'source' => 'sales', 'samples' => 0],
            [
                'total_locations'       => 100,
                'empty_locations'       => 50,
                'free_ratio'            => 0.5,
                'inbound_open_po_lines' => 0,
                'partner_share_used'    => 0,
                'partner_share_limit'   => 10,
            ],
        ], now()->addMinutes(15));

        $firstItem = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 5,
        ]);

        expect(GetPartnerOrderCapacity::run($this->orgPartner->refresh())['blocked']['at_capacity'])->toBeTrue()
            ->and(GetPartnerOrderCapacity::overBudgetMessage($this->orgPartner))->toBeString()
            ->and(submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
                'quantity' => 1,
            ]))->toBeInstanceOf(PartnerShoppingListItem::class);

        $bulk = StorePartnerShoppingListItems::make()->action($this->orgPartner, [
            ['org_stock_id' => $this->buyerOrgStock->id, 'quantity' => 1],
        ]);
        expect($bulk)->toMatchArray(['created' => 1, 'skipped' => [], 'over_budget' => true]);

        $this->buyerOrgStock->update(['quantity_available' => 0]);
        $outOfStockItem = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 1,
        ]);
        expect($outOfStockItem)->toBeInstanceOf(PartnerShoppingListItem::class);

        $this->buyerOrgStock->update(['quantity_available' => 10, 'health_rank' => HealthRankEnum::A]);
        $aRankItem = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 1,
        ]);
        expect($aRankItem)->toBeInstanceOf(PartnerShoppingListItem::class);

        $this->buyerOrgStock->update(['health_rank' => null]);
        Cache::forget("partner-order-capacity:{$this->orgPartner->id}");
        expect($outOfStockItem->id)->toBe($firstItem->id)
            ->and($aRankItem->id)->toBe($firstItem->id)
            ->and(DeletePartnerShoppingListItem::make()->action($firstItem))->toBeTrue();
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
    });

    test('browse card shows the component that runs out first', function () {
        $components = collect([1, 2])->map(function (int $stockId) {
            $orgStock = new OrgStock(['stock_id' => $stockId]);
            $orgStock->stock_id = $stockId;

            return $orgStock;
        });

        $buyerOrgStocks = collect([1, 2])->mapWithKeys(function (int $stockId) {
            $orgStock = new OrgStock();
            $orgStock->setRelation('stats', new OrgStockStats(['days_of_cover' => $stockId === 1 ? 700 : 4]));

            return [$stockId => $orgStock];
        });

        $tightest = ShowPartnerBrowse::make()->tightestComponent($components, $buyerOrgStocks);

        expect($tightest->stock_id)->toBe(2);
    });

    test('UI partner browse filters by stock cover bucket', function () {
        $seller     = $this->orgPartner->partner;
        $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
        [, $sellerProduct] = createProduct($sellerShop);
        createOrgStocks($this->orgPartner->organisation, [$sellerProduct->orgStocks()->first()->stock]);

        $originalSettings = $seller->settings;
        $settings         = $originalSettings;
        data_set($settings, 'procurement.shop_id', $sellerShop->id);
        $seller->update(['settings' => $settings]);

        $response = $this->get(route('grp.org.procurement.org_partners.show.browse.index', [$this->organisation->slug, $this->orgPartner->id]).'?cover=out');
        $response->assertOk();

        $response->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Procurement/PartnerBrowse')
                ->where('level', 'cover')
                ->where('coverLabel', 'Out of stock')
                ->has('products.data');
        });

        $neverStocked = $this->get(route('grp.org.procurement.org_partners.show.browse.index', [$this->organisation->slug, $this->orgPartner->id]).'?cover=never');
        $neverStocked->assertOk();
        $neverStocked->assertInertia(fn (AssertableInertia $page) => $page
            ->where('level', 'cover')
            ->where('coverLabel', 'We never stocked')
            ->has('products.data'));

        $seller->update(['settings' => $originalSettings]);
    });

    test('UI partner cover bucket items index', function () {
        $response = $this->get(route('grp.org.procurement.org_partners.show.shopping.items.index', [$this->organisation->slug, $this->orgPartner->id]).'?cover=out');
        $response->assertOk();

        $response->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Procurement/PartnerCoverBucketItems')
                ->where('bucket', 'out')
                ->where('bucketLabel', 'Out of stock')
                ->where('perPage', 25)
                ->where('perPageOptions', [25, 100, 500])
                ->where('items.data', fn ($rows) => collect($rows)->every(fn ($row) => collect(['quarterly_usage', 'stock_cover', 'stock_deliveries', 'units_per_pack', 'quantity_ordered', 'other_open_purchase_orders'])
                    ->every(fn ($key) => array_key_exists($key, (array) $row))))
                ->has('items.data')
                ->has('addRoute.name');
        });

        $route = route('grp.org.procurement.org_partners.show.shopping.items.index', [$this->organisation->slug, $this->orgPartner->id]);

        $this->get($route.'?cover=out&per_page=500')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('perPage', 500)->where('items.per_page', 500));

        $this->get($route.'?cover=out&per_page=9999')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('perPage', 25)->where('items.per_page', 25));
    });
});

test('UI partner shopping list index', function () {
    $response = $this->get(route('grp.org.procurement.org_partners.show.shopping_list.index', [$this->organisation->slug, $this->orgPartner->id]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/PartnerShoppingList')
            ->has('title')
            ->has('data')
            ->has('orgStockFetchRoute')
            ->where('filterGroups', fn ($groups) => collect($groups)->pluck('key')->all() === ['category', 'origin', 'rank']
                && collect(collect($groups)->firstWhere('key', 'origin')['options'])->pluck('value')->all() === ['us', 'hub'])
            ->where('queryBuilderProps.default.elementGroups', fn ($groups) => collect($groups)->keys()->all() === ['priority']);
    });

    $this->get(route('grp.org.procurement.org_partners.show.shopping_list.index', [
        $this->organisation->slug, $this->orgPartner->id, 'filter[category]' => 'none,999999', 'filter[origin]' => 'us', 'filter[rank]' => 'A',
    ]))->assertOk();
});

test('UI partner shipping list index', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);

    $response = $this->get(route('grp.org.productions.show.to_produce.list', [$this->organisation->slug, $production->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Production/PartnerShippingList')
            ->has('title')
            ->has('data');
    });
});

test('pre-pick list only shows partner lines that have stock behind them', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();

    $withStock = createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $sellerStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $withStock->stock_id)->first()
        ?? createOrgStocks($seller, [$withStock->stock])[0];
    $sellerStock->update(['quantity_available' => 12]);
    $item = submittedPartnerShoppingListItem($this->orgPartner, $withStock, ['quantity' => 30]);

    $props = $this->get(route('grp.org.productions.show.pre_pick.index', [$seller->slug, $production->slug]))
        ->assertOk()->viewData('page')['props'];

    expect($props['data']['data'])->toHaveCount(1)
        ->and((float) $props['data']['data'][0]['can_pick'])->toBe(12.0)
        ->and((float) $props['data']['data'][0]['quantity'])->toBe(30.0)
        ->and($props['filters'])->toHaveKeys(['category', 'cosmetic', 'requester', 'priority'])
        ->and(collect($props['filters']['requester']['options'])->pluck('value')->all())
        ->toBe([$this->orgPartner->organisation->code]);

    expect(collect($props['data']['data'])->firstWhere('id', $item->id)['can_pick'])->toEqual(12)
        ->and($props['data']['data'][0]['is_cosmetic'])->toBeFalse()
        ->and(collect($props['filters']['cosmetic']['options'])->pluck('value')->all())->toBe(['non-cosmetic']);

    $withStock->stock->update(['is_cosmetic' => true]);
    $prePickUrl = route('grp.org.productions.show.pre_pick.index', [$seller->slug, $production->slug]);

    $props = $this->get($prePickUrl)->assertOk()->viewData('page')['props'];
    expect($props['data']['data'][0]['is_cosmetic'])->toBeTrue()
        ->and(collect($props['filters']['cosmetic']['options'])->pluck('value')->all())->toBe(['cosmetic']);

    expect($this->get($prePickUrl.'?elements[cosmetic]=cosmetic')->viewData('page')['props']['data']['data'])->toHaveCount(1);

    $withStock->stock->update(['is_cosmetic' => false]);
    $sellerStock->update(['quantity_available' => 0]);
    $props = $this->get(route('grp.org.productions.show.pre_pick.index', [$seller->slug, $production->slug]))
        ->assertOk()->viewData('page')['props'];
    expect($props['data']['data'])->toBe([]);
});

test('pre-pick list caps can pick at stock not already promised and says why a pre-pick is refused', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();

    $withStock   = createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $sellerStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $withStock->stock_id)->first()
        ?? createOrgStocks($seller, [$withStock->stock])[0];
    $sellerStock->update(['quantity_available' => 12]);

    submittedPartnerShoppingListItem($this->orgPartner, $withStock, ['quantity' => 12])->update(['pre_picked_at' => now()]);
    $waiting = submittedPartnerShoppingListItem($this->orgPartner, $withStock, ['quantity' => 5]);

    $props = $this->get(route('grp.org.productions.show.pre_pick.index', [$seller->slug, $production->slug]))
        ->assertOk()->viewData('page')['props'];

    expect((float) collect($props['data']['data'])->firstWhere('id', $waiting->id)['can_pick'])->toBe(0.0);

    $this->post(route('grp.org.productions.show.pre_pick.pick', [$seller->slug, $production->slug]), ['lines' => [['id' => $waiting->id, 'quantity' => 5]]])
        ->assertRedirect()
        ->assertSessionHas('notification', fn (array $notification) => $notification['status'] === 'error' && str_contains($notification['description'], 'already promised'));

    expect($waiting->fresh()->pre_picked_at)->toBeNull();
});

test('production queue counts feed the sidebar and ignore deleted lines', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();

    $withStock   = createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $sellerStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $withStock->stock_id)->first()
        ?? createOrgStocks($seller, [$withStock->stock])[0];
    $sellerStock->update(['quantity_available' => 12]);

    $counts = fn () => $this->get(route('grp.org.productions.show.queue_counts', [$seller->slug, $production->slug]))
        ->assertOk()->json();

    $before = $counts();
    $item   = submittedPartnerShoppingListItem($this->orgPartner, $withStock, ['quantity' => 4]);

    expect($counts()['pre_pick'])->toBe($before['pre_pick'] + 1)
        ->and($counts()['channel'])->toBe('grp.org.'.$seller->id.'.production-queues');

    $item->delete();
    expect($counts()['pre_pick'])->toBe($before['pre_pick']);
});

test('pre-picking reserves stock for the partner without creating any order', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();

    $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $sellerOrgStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $buyerOrgStock->stock_id)->first()
        ?? createOrgStocks($seller, [$buyerOrgStock->stock])[0];
    $sellerOrgStock->update(['quantity_available' => 4]);

    $item        = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 10]);
    $ordersBefore = \App\Models\Ordering\Order::count();

    $result = \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
        ->action($seller, [['id' => $item->id, 'quantity' => 10]]);

    expect($result['pre_picked'])->toBe(1)
        ->and($result['quantity'])->toBe(4.0)
        ->and(\App\Models\Ordering\Order::count())->toBe($ordersBefore);

    $item->refresh();
    expect($item->pre_picked_at)->not->toBeNull()
        ->and((float) $item->quantity)->toBe(4.0)
        ->and($item->transaction_id)->toBeNull()
        ->and($item->state)->toBe(ShoppingListItemStateEnum::OPEN);

    /* What could not be covered stays on the list, still waiting and not pre-picked. */
    $remainder = PartnerShoppingListItem::where('parent_id', $item->id)->first();
    expect((float) $remainder->quantity)->toBe(6.0)
        ->and($remainder->pre_picked_at)->toBeNull();

    /* Once the partner is preparing it, the buyer can no longer change or remove the line. */
    expect(fn () => UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => 2]))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and(fn () => \App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem::make()->action($item))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect((float) $item->refresh()->quantity)->toBe(4.0);

    /* A pre-picked line drops off the pre-pick list, it is now the warehouse's job. */
    $listed = $this->get(route('grp.org.productions.show.pre_pick.index', [$seller->slug, $production->slug]))
        ->assertOk()->viewData('page')['props']['data']['data'];
    expect(collect($listed)->pluck('id'))->not->toContain($item->id);
});

test('pre-pick closed short cancels the shortfall instead of leaving it outstanding', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();

    $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $sellerOrgStock = OrgStock::where('organisation_id', $seller->id)->where('stock_id', $buyerOrgStock->stock_id)->first()
        ?? createOrgStocks($seller, [$buyerOrgStock->stock])[0];
    $sellerOrgStock->update(['quantity_available' => 25]);

    $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 28]);

    $this->post(route('grp.org.productions.show.pre_pick.pick', [$seller->slug, $production->slug]), ['lines' => [['id' => $item->id, 'quantity' => 25, 'cancel_shortfall' => true]]])
        ->assertRedirect();

    $item->refresh();
    $shortfall = PartnerShoppingListItem::where('parent_id', $item->id)->first();

    expect((float) $item->quantity)->toBe(25.0)
        ->and($item->pre_picked_at)->not->toBeNull()
        ->and((float) $shortfall->quantity)->toBe(3.0)
        ->and($shortfall->state)->toBe(ShoppingListItemStateEnum::DISMISSED)
        ->and($shortfall->dismissed_at)->not->toBeNull()
        ->and(PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::OPEN)->whereNull('pre_picked_at')->exists())->toBeFalse();
});

test('only a manufacturing hub sells to its partners through the shopping list and pre-pick', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $seller     = $this->orgPartner->partner;
    $buyerWasHub = $this->orgPartner->organisation->is_manufacturing_hub;
    $seller->update(['is_manufacturing_hub' => false]);
    $production->organisation->update(['is_manufacturing_hub' => false]);
    $this->orgPartner->organisation->update(['is_manufacturing_hub' => false]);

    $this->get(route('grp.org.procurement.org_partners.show.shopping_list.index', [$this->organisation->slug, $this->orgPartner->id]))->assertNotFound();
    $this->get(route('grp.org.procurement.org_partners.show.shopping.dashboard', [$this->organisation->slug, $this->orgPartner->id]))->assertNotFound();
    $this->getJson(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id]))->assertNotFound();
    $this->get(route('grp.org.productions.show.pre_pick.index', [$production->organisation->slug, $production->slug]))->assertForbidden();

    $subNavigation = $this->get(route('grp.org.procurement.org_partners.show', [$this->organisation->slug, $this->orgPartner->id]))
        ->assertOk()->viewData('page')['props']['pageHead']['subNavigation'];
    expect(collect($subNavigation)->pluck('route.name'))->not->toContain('grp.org.procurement.org_partners.show.shopping.dashboard')
        ->and(fn () => \App\Actions\Procurement\OrgPartner\SetPartnerGoodsOutLocation::run($this->orgPartner, new Location()))
        ->toThrow(ValidationException::class);

    $this->orgPartner->organisation->update(['is_manufacturing_hub' => $buyerWasHub]);
    $seller->update(['is_manufacturing_hub' => true]);
    $production->organisation->update(['is_manufacturing_hub' => true]);

    $this->get(route('grp.org.procurement.org_partners.show.shopping_list.index', [$this->organisation->slug, $this->orgPartner->id]))->assertOk();
    $this->get(route('grp.org.productions.show.pre_pick.index', [$production->organisation->slug, $production->slug]))->assertOk();
});

test('batch size is hinted to the partner buyer and to the factory board', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $orgStock   = OrgStock::where('organisation_id', $this->orgPartner->partner_id)->first();
    $artefact   = StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BATCH-1', 'name' => 'Batched artefact', 'recommended_batch_size' => 45]);
    $artefact->update(['org_stock_id' => $orgStock->id]);

    $rows = collect($this->get(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id]))->json('data'));
    expect($rows->firstWhere('id', $orgStock->id)['batch_size'])->toBe(45);
});

test('the partner stock picker shows how long the buyer own stock lasts', function () {
    $partnerOrgStock = OrgStock::where('organisation_id', $this->orgPartner->partner_id)->first();
    $buyerOrgStock   = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)
        ->where('stock_id', $partnerOrgStock->stock_id)->first()
        ?? createOrgStocks($this->orgPartner->organisation, [$partnerOrgStock->stock])[0];

    $buyerOrgStock->stats->update([
        'days_of_cover'             => 9.6,
        'predicted_out_of_stock_at' => now()->addDays(9)->toDateString(),
    ]);

    $row = collect($this->get(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id]))->json('data'))
        ->firstWhere('id', $partnerOrgStock->id);

    expect($row['buyer_days_of_cover'])->toBe(9)
        ->and($row['buyer_out_of_stock_at'])->toBe(now()->addDays(9)->toDateString());
});

test('to produce item moves backlog to preparing and back', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);
    $orgStock   = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)->first() ?? createOrgStocks($this->orgPartner->organisation, [Stock::first()])[0];
    $item       = PartnerShoppingListItem::whereNull('job_order_id')->first() ?? submittedPartnerShoppingListItem($this->orgPartner, $orgStock, ['quantity' => 3]);

    $this->post(route('grp.org.productions.show.to_produce.items.preparing', [$this->organisation->slug, $production->slug]), ['preparing' => true, 'lines' => [['id' => $item->id, 'quantity' => 10]]])
        ->assertRedirect();
    expect($item->fresh()->preparing_at)->not->toBeNull()
        ->and((float) $item->fresh()->quantity_to_produce)->toBe(10.0);

    $this->post(route('grp.org.productions.show.to_produce.items.preparing', [$this->organisation->slug, $production->slug]), ['preparing' => false, 'lines' => [['id' => $item->id]]])
        ->assertRedirect();
    expect($item->fresh()->preparing_at)->toBeNull()
        ->and($item->fresh()->quantity_to_produce)->toBeNull();
});

test('UI to produce list grouped by artisan, family and for', function () {
    $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'PART', 'name' => 'Partner factory']);

    foreach (['by_artisan' => 'maker', 'by_category' => 'family', 'by_for' => 'buyer_code', 'index' => 'board'] as $routeSuffix => $groupBy) {
        $this->get(route('grp.org.productions.show.to_produce.'.$routeSuffix, [$this->organisation->slug, $production->slug]))
            ->assertInertia(function (AssertableInertia $page) use ($groupBy) {
                $page
                    ->component('Org/Production/PartnerShippingList')
                    ->where('groupBy', $groupBy)
                    ->has('groups')
                    ->where('artisanWorkload', fn ($workload) => in_array($groupBy, ['maker', 'board', 'mixes']) ? $workload !== null : $workload === null);
            });
    }
});

test('partner shopping list org stocks json feed', function () {
    $seller = $this->orgPartner->partner;

    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->where('state', ShoppingListItemStateEnum::OPEN)
        ->delete();

    $response = $this->getJson(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id]));

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $sellerOrgStock->id);

    expect($row)->not->toBeNull()
        ->and($row['code'])->toBe($sellerOrgStock->code)
        ->and($row['saveRoute']['name'])->toBe('grp.org.procurement.org_partners.show.shopping_list.store')
        ->and($row['deleteRoute'])->toBeNull();

    submittedPartnerShoppingListItem($this->orgPartner, $sellerOrgStock, ['quantity' => 5, 'break_batch' => true]);

    $response = $this->getJson(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id]));
    $row = collect($response->json('data'))->firstWhere('id', $sellerOrgStock->id);

    $expectedPricePerSko = round(
        (float) $sellerProduct->price / (float) $sellerOrgStock->pivot->quantity
        * $this->orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($this->orgPartner),
        4
    );

    expect((float) $row['quantity_ordered'])->toBe(5.0)
        ->and(round((float) $row['price_per_sko'], 4))->toBe($expectedPricePerSko)
        ->and($response->json('currency'))->toBe($this->orgPartner->organisation->currency->code)
        ->and($row['saveRoute']['name'])->toBe('grp.org.procurement.org_partners.show.shopping_list.update')
        ->and($row['deleteRoute']['name'])->toBe('grp.org.procurement.org_partners.show.shopping_list.destroy');
});

test('partner stock picker shows a split SKO once, editing the line the partner has not started', function () {
    $seller = $this->orgPartner->partner;

    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->where('stock_id', $sellerOrgStock->stock_id)
        ->delete();

    $prePicked = submittedPartnerShoppingListItem($this->orgPartner, $sellerOrgStock, ['quantity' => 2, 'break_batch' => true]);
    $prePicked->update(['pre_picked_at' => now()]);
    $waiting = $prePicked->replicate()->fill(['parent_id' => $prePicked->id, 'pre_picked_at' => null, 'quantity' => 8]);
    $waiting->save();

    $rows = collect($this->getJson(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id, 'filter[global]' => $sellerOrgStock->code]))->json('data'))
        ->where('id', $sellerOrgStock->id);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['shopping_list_item_id'])->toBe($waiting->id)
        ->and((float) $rows->first()['quantity_ordered'])->toBe(8.0)
        ->and((float) $rows->first()['quantity_started'])->toBe(2.0);
});

test('partner shopping list org stocks search matches a word inside the name', function () {
    $seller = $this->orgPartner->partner;

    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();
    $sellerOrgStock->update(['name' => 'ActivaWrap Honeycombe paper, 90gsm']);

    $response = $this->getJson(route('grp.json.org_partner.shopping_list_org_stocks', [$this->orgPartner->id, 'filter[global]' => 'Honeycombe']));

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $sellerOrgStock->id))->not->toBeNull();
});

test('auto-fill suggests shopping list within budget from usage history', function () {
    $seller = $this->orgPartner->partner;
    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();
    $sellerOrgStock->update(['quantity_available' => 50]);

    $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];
    $buyerOrgStock->update(['quantity_available' => 0]);
    DB::table('delivery_note_items')->where('org_stock_id', $buyerOrgStock->id)->update(['quantity_dispatched' => 0]);
    $buyerOrgStock->stats()->update(['days_of_cover' => null, 'recommended_order_quantity' => null]);

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->whereIn('state', ShoppingListItemStateEnum::onPartnerBuyerList())
        ->delete();

    $buyerOrgStock->stats->update(['predicted_daily_usage' => 5.5]);

    DB::table('org_stocks')->where('id', $sellerOrgStock->id)->update(['quantity_available' => 50]);

    $proposal = SuggestPartnerShoppingList::make()->action($this->orgPartner, 100000);

    $line = collect($proposal['lines'])->firstWhere('org_stock_id', $sellerOrgStock->id);

    expect($line)->not->toBeNull()
        ->and($proposal['total'])->toBeLessThanOrEqual(100000)
        ->and((float) $line['quantity'])->toBeGreaterThanOrEqual(1.0)
        ->and((float) $line['cost'])->toBe(round($line['quantity'] * $line['price_per_sko'], 2))
        ->and($line['reason'])->toContain('/quarter');

    $result = StorePartnerShoppingListItems::make()->action($this->orgPartner, [
        ['org_stock_id' => $sellerOrgStock->id, 'quantity' => $line['quantity'], 'notes' => $line['reason']],
    ]);

    expect($result['created'])->toBe(1);

    $item = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->where('state', ShoppingListItemStateEnum::DRAFT)
        ->where('stock_id', $sellerOrgStock->stock_id)
        ->first();

    expect($item)->not->toBeNull()
        ->and((float) $item->quantity)->toBe((float) $line['quantity'])
        ->and($item->notes)->toBe($line['reason']);
});

test('on demand buyer org stock excluded from partner shopping', function () {
    $seller = $this->orgPartner->partner;
    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();
    $sellerOrgStock->update(['quantity_available' => 50]);

    $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];
    $buyerOrgStock->update(['quantity_available' => 0, 'is_on_demand' => true]);
    $buyerOrgStock->stats->update(['predicted_daily_usage' => 5.5]);

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->where('state', ShoppingListItemStateEnum::OPEN)
        ->delete();

    $proposal = SuggestPartnerShoppingList::make()->action($this->orgPartner, 100000);
    expect(collect($proposal['lines'])->firstWhere('org_stock_id', $sellerOrgStock->id))->toBeNull();

    $outStockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($this->orgPartner, 'out');
    expect($outStockIds)->not->toContain($sellerOrgStock->stock_id);

    $buyerOrgStock->update(['is_on_demand' => false]);

    $outStockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($this->orgPartner, 'out');
    expect($outStockIds)->toContain($sellerOrgStock->stock_id);
});

test('do-not-auto-order buyer org stock excluded from auto-fill but kept in buckets', function () {
    $seller = $this->orgPartner->partner;
    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();
    $sellerOrgStock->update(['quantity_available' => 50]);

    $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];
    $buyerOrgStock->update(['quantity_available' => 0]);
    DB::table('delivery_note_items')->where('org_stock_id', $buyerOrgStock->id)->update(['quantity_dispatched' => 0]);
    $buyerOrgStock->stats()->update(['days_of_cover' => null, 'recommended_order_quantity' => null, 'predicted_daily_usage' => 5.5]);

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
        ->whereIn('state', ShoppingListItemStateEnum::onPartnerBuyerList())
        ->delete();

    $outStockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($this->orgPartner, 'out');
    expect($outStockIds)->toContain($sellerOrgStock->stock_id);

    DB::table('org_stocks')->where('id', $sellerOrgStock->id)->update(['quantity_available' => 50]);

    $proposal = SuggestPartnerShoppingList::make()->action($this->orgPartner, 100000);
    expect(collect($proposal['lines'])->firstWhere('org_stock_id', $sellerOrgStock->id))->not->toBeNull();

    UpdateOrgStock::make()->action($buyerOrgStock, ['is_excluded_from_auto_ordering' => true]);
    expect($buyerOrgStock->refresh()->is_excluded_from_auto_ordering)->toBeTrue();

    DB::table('org_stocks')->where('id', $sellerOrgStock->id)->update(['quantity_available' => 50]);
    DB::table('org_stocks')->where('id', $buyerOrgStock->id)->update(['quantity_available' => 0]);
    $buyerOrgStock->stats()->update(['days_of_cover' => null, 'recommended_order_quantity' => null, 'predicted_daily_usage' => 5.5]);

    $proposal = SuggestPartnerShoppingList::make()->action($this->orgPartner, 100000);
    expect(collect($proposal['lines'])->firstWhere('org_stock_id', $sellerOrgStock->id))->toBeNull();

    $outStockIds = GetPartnerStockCoverBuckets::make()->stockIdsInBucket($this->orgPartner, 'out');
    expect($outStockIds)->toContain($sellerOrgStock->stock_id);
});

test('org partner shopping list stats hydrate', function () {
    $seller = $this->orgPartner->partner;
    $sellerShop = $seller->shops()->first() ?? StoreShop::run($seller, Shop::factory()->definition());
    [, $sellerProduct] = createProduct($sellerShop);
    $sellerOrgStock = $sellerProduct->orgStocks()->first();

    PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
    OrgPartnerHydrateShoppingListItems::run($this->orgPartner);

    expect($this->orgPartner->stats->refresh()->number_open_shopping_list_items)->toBe(0);

    $item = submittedPartnerShoppingListItem($this->orgPartner, $sellerOrgStock, ['quantity' => 3]);
    OrgPartnerHydrateShoppingListItems::run($this->orgPartner);

    expect($this->orgPartner->stats->refresh()->number_open_shopping_list_items)->toBe(1)
        ->and($this->orgPartner->stats->number_shopping_list_items)->toBe(1);

    DeletePartnerShoppingListItem::make()->action($item);
    OrgPartnerHydrateShoppingListItems::run($this->orgPartner);

    expect($this->orgPartner->stats->refresh()->number_open_shopping_list_items)->toBe(0);
});

test('org stock lead time hydrator measures from delivery history', function () {
    $orgStock = OrgStock::first();
    expect($orgStock)->not->toBeNull();

    OrgStockHydrateLeadTime::run($orgStock);
    $orgStock->refresh();

    expect($orgStock->lead_time_samples)->toBe(0)
        ->and($orgStock->measured_lead_time_days)->toBeNull();

    UpdateOrgStock::make()->action($orgStock, ['estimated_lead_time_days' => 21]);

    expect($orgStock->refresh()->estimated_lead_time_days)->toBe(21);
});

test('lead time of a supplier ordered from once a year is measured from its last 3 orders, not the estimate, each order counted once at its first delivery', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['estimated_lead_time_days' => 45]);
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id], ['available' => true]);

    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());

    $order = function (int $submittedDaysAgo, int ...$leadDaysPerDelivery) use ($orgSupplier, $supplierProduct, $orgStock): void {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
        StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $orgStock, [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 10]);
        $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays($submittedDaysAgo)]);

        foreach ($leadDaysPerDelivery as $leadDays) {
            $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'LEAD-'.uniqid(), 'date' => date('Y-m-d')], strict: false);
            DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update(['booked_in_at' => null, 'placed_at' => now()->subDays($submittedDaysAgo - $leadDays)]);
            DB::table('purchase_order_stock_delivery')->insert(['purchase_order_id' => $purchaseOrder->id, 'stock_delivery_id' => $stockDelivery->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    };

    $order(1800, 400);
    $order(1400, 220);
    $order(1000, 200);
    $order(600, 180, 500, 520);

    SupplierProductHydrateLeadTime::run($supplierProduct);
    OrgStockHydrateLeadTime::run($orgStock);

    expect($supplierProduct->refresh()->measured_lead_time_days)->toBe(200)
        ->and($supplierProduct->lead_time_samples)->toBe(3)
        ->and($orgStock->refresh()->measured_lead_time_days)->toBe(200);

    $order(300, 30);
    $order(200, 30);
    $order(100, 30);

    SupplierProductHydrateLeadTime::run($supplierProduct);

    expect($supplierProduct->refresh()->measured_lead_time_days)->toBe(30)
        ->and($supplierProduct->lead_time_samples)->toBe(3);
});

test('agent lead times roll up per sub-supplier', function () {
    $leadTimes = App\Actions\Procurement\OrgAgent\GetAgentLeadTimes::run($this->orgAgent);

    expect($leadTimes['agent'])->toHaveKeys(['days', 'source', 'samples'])
        ->and($leadTimes['agent']['days'])->toBeGreaterThan(0)
        ->and($leadTimes['suppliers'])->toBeArray();

    foreach ($leadTimes['suppliers'] as $supplier) {
        expect($supplier)->toHaveKeys(['supplier_id', 'code', 'name', 'days', 'source', 'samples'])
            ->and($supplier['days'])->toBeGreaterThan(0);
    }
});

test('agent cover buckets count each supplier product exactly once', function () {
    $cover = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::run($this->orgAgent);

    $distinct = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::make()
        ->scopedQuery($this->orgAgent)
        ->distinct()
        ->count('osp.id');

    expect($cover['buckets'])->toHaveCount(9)
        ->and($cover['buckets'][0]['bucket'])->toBe('out')
        ->and(collect($cover['buckets'])->pluck('bucket')->all())->toContain('gone')
        ->and($cover['total'])->toBe($distinct);
});

test('agent catalogue leaves out dead codes: every SKO discontinued, or the SKO is already bought under another code', function () {
    $buckets = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::make();
    $inNever = fn (OrgSupplierProduct $orgSupplierProduct) => in_array($orgSupplierProduct->id, $buckets->orgSupplierProductIdsInBucket($this->orgAgent, 'never'), true);

    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [, $oldCode] = createAgentOrgSupplierWithProduct($this);
    $oldCode->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $oldCode->id)->delete();
    DB::table('stock_has_supplier_products')->where('supplier_product_id', $oldCode->supplier_product_id)->delete();
    $oldCodeStockLink = StockHasSupplierProduct::create(['stock_id' => $stock->id, 'supplier_product_id' => $oldCode->supplier_product_id, 'available' => true]);

    expect($inNever($oldCode))->toBeTrue();

    $stock->update(['state' => StockStateEnum::DISCONTINUED]);
    expect($inNever($oldCode))->toBeFalse();

    $stock->update(['state' => StockStateEnum::ACTIVE]);
    expect($inNever($oldCode))->toBeTrue();

    [, $newCode] = createAgentOrgSupplierWithProduct($this);
    $newCode->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE]);
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $newCode->id)->delete();
    $newCodeStockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $newCode->supplier_product_id], ['available' => true]);
    OrgStockHasOrgSupplierProduct::create([
        'org_stock_id'                  => $orgStock->id,
        'org_supplier_product_id'       => $newCode->id,
        'stock_has_supplier_product_id' => $newCodeStockLink->id,
        'status'                        => true,
        'local_priority'                => 0,
    ]);

    expect($inNever($oldCode))->toBeFalse()
        ->and($inNever($newCode))->toBeFalse()
        ->and($oldCodeStockLink->exists)->toBeTrue();

    $newCode->update(['state' => OrgSupplierProductStateEnum::DISCONTINUED, 'is_available' => false]);
    expect($inNever($oldCode))->toBeTrue();

    $newCode->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    expect($inNever($oldCode))->toBeFalse();

    $discontinue = App\Actions\Procurement\OrgSupplierProducts\DiscontinueDeadOrgSupplierProducts::make();
    $isDead      = fn (OrgSupplierProduct $orgSupplierProduct) => $discontinue->dead($this->organisation->slug)->where('osp.id', $orgSupplierProduct->id)->exists();

    expect($isDead($oldCode))->toBeFalse();

    $stock->update(['state' => StockStateEnum::DISCONTINUED]);

    expect($isDead($oldCode))->toBeTrue()
        ->and($isDead($newCode))->toBeFalse()
        ->and(collect($discontinue->preview($this->organisation->slug))->sum('products'))->toBeGreaterThanOrEqual(1);

    $recentOrder = StorePurchaseOrder::make()->action($oldCode->orgSupplier, [], strict: false);
    $lineId      = DB::table('purchase_order_transactions')->insertGetId([
        'group_id'            => $recentOrder->group_id,
        'organisation_id'     => $recentOrder->organisation_id,
        'purchase_order_id'   => $recentOrder->id,
        'supplier_product_id' => $oldCode->supplier_product_id,
        'org_stock_id'        => $orgStock->id,
        'state'               => PurchaseOrderTransactionStateEnum::IN_PROCESS->value,
        'delivery_state'      => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
        'quantity_ordered'    => 1,
        'data'                => '{}',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    expect($isDead($oldCode))->toBeFalse();

    DB::table('purchase_order_transactions')->where('id', $lineId)->update(['deleted_at' => now()]);

    expect($isDead($oldCode))->toBeTrue()
        ->and($oldCode->fresh()->state)->toBe(OrgSupplierProductStateEnum::ACTIVE->value);

    DB::table('org_supplier_products')->where('id', $oldCode->id)->update(['org_agent_id' => null]);

    expect($isDead($oldCode))->toBeTrue()
        ->and(collect($discontinue->preview($this->organisation->slug))->pluck('agent'))->toContain('direct');

    $discontinue->handle($this->organisation->slug);

    expect($oldCode->fresh()->state)->toBe(OrgSupplierProductStateEnum::DISCONTINUED->value)
        ->and($newCode->fresh()->state)->toBe(OrgSupplierProductStateEnum::ACTIVE->value)
        ->and($oldCode->supplierProduct->fresh()->state)->not->toBe(SupplierProductStateEnum::DISCONTINUED);

    $stock->update(['state' => StockStateEnum::ACTIVE]);
    $orgStock->update(['state' => OrgStockStateEnum::DISCONTINUED]);
    App\Actions\Goods\Stock\Hydrators\StockHydrateStateFromOrgStocks::run($stock->id);

    expect($stock->fresh()->state)->toBe(StockStateEnum::DISCONTINUED)
        ->and($newCode->fresh()->state)->toBe(OrgSupplierProductStateEnum::DISCONTINUED->value);
});

test('agent stock groups read the lead time the ordering card reads and are never at risk past the overstock edge', function () {
    $buckets = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::make();
    $stock   = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $orgSupplierProduct->supplierProduct->update(['measured_lead_time_days' => null, 'estimated_lead_time_days' => 10]);
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    $stockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id], ['available' => true]);
    $orgStock  = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 100, 'measured_lead_time_days' => null, 'estimated_lead_time_days' => 60]);
    OrgStockHasOrgSupplierProduct::create([
        'org_stock_id'                  => $orgStock->id,
        'org_supplier_product_id'       => $orgSupplierProduct->id,
        'stock_has_supplier_product_id' => $stockLink->id,
        'status'                        => true,
        'local_priority'                => 0,
    ]);

    $bucketFor = function (float $days) use ($orgStock, $buckets, $orgSupplierProduct) {
        $orgStock->stats()->updateOrCreate([], ['days_of_cover' => $days, 'predicted_daily_usage' => 1, 'stock_value' => 100]);

        return collect(['w1', 'w2', 'w3', 'w4', 'ok'])->first(fn (string $bucket) => in_array($orgSupplierProduct->id, $buckets->orgSupplierProductIdsInBucket($this->orgAgent, $bucket), true));
    };

    expect($bucketFor(100))->toBe('w2')
        ->and($bucketFor(150))->toBe('ok');

    $orgStock->update(['quantity_available' => 0, 'packed_in' => 1]);
    $isOut = function () use ($orgStock, $buckets, $orgSupplierProduct) {
        $orgStock->stats()->updateOrCreate([], ['days_of_cover' => 0, 'predicted_daily_usage' => 1, 'stock_value' => 0]);

        return in_array($orgSupplierProduct->id, $buckets->orgSupplierProductIdsInBucket($this->orgAgent, 'out'), true);
    };
    expect($isOut())->toBeTrue();

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplierProduct->orgSupplier, [], strict: false);
    DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update(['state' => PurchaseOrderStateEnum::SUBMITTED->value]);
    DB::table('purchase_order_transactions')->insert([
        'group_id'            => $purchaseOrder->group_id,
        'organisation_id'     => $purchaseOrder->organisation_id,
        'purchase_order_id'   => $purchaseOrder->id,
        'supplier_product_id' => $orgSupplierProduct->supplier_product_id,
        'org_stock_id'        => $orgStock->id,
        'state'               => PurchaseOrderTransactionStateEnum::SUBMITTED->value,
        'delivery_state'      => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
        'quantity_ordered'    => 300,
        'data'                => '{}',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);
    expect($isOut())->toBeFalse();
    DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update(['deleted_at' => now()]);

    $signals = App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals::run($orgStock->fresh(), $orgSupplierProduct->supplierProduct, fallbackLeadTimeDays: 45);
    expect($signals['lead_time_days'])->toBe(60);

    $orgStock->update(['estimated_lead_time_days' => null]);
    $orgSupplierProduct->supplierProduct->update(['estimated_lead_time_days' => null]);
    $signals = App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals::run($orgStock->fresh(), $orgSupplierProduct->supplierProduct->fresh(), fallbackLeadTimeDays: 45);
    expect($signals['lead_time_days'])->toBe(45);
});

test('agent round budget is a usual month of orders plus restocking, in the organisation currency', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, []);

    DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update([
        'state'          => PurchaseOrderStateEnum::SUBMITTED->value,
        'delivery_state' => App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum::IN_PROCESS->value,
        'submitted_at'   => now(),
        'cost_total'     => 1200000000,
        'org_exchange'   => 0.001,
    ]);
    Cache::forget("agent-order-capacity:{$this->orgAgent->id}");

    try {
        $capacity = App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::run($this->orgAgent);
        $budget   = $capacity['agent_capacity'];

        expect($budget['source'])->toBe('measured')
            ->and($budget['usual_month_orders'])->toBeGreaterThanOrEqual(1200000 / 12)
            ->and($budget['usual_month_orders'])->toBeLessThan(1200000000 / 12)
            ->and($budget['lands_for_us_per_30d'])->toBe(round($budget['usual_month_orders'] + $budget['restock_out_of_stock'], 2))
            ->and($capacity['pipeline']['waiting'])->toBeGreaterThanOrEqual(1200000.0)
            ->and($capacity['pipeline']['waiting'])->toBeLessThan(1200000000.0)
            ->and($capacity['pipeline']['total'])->toBe(round($capacity['pipeline']['waiting'] + $capacity['pipeline']['being_made'] + $capacity['pipeline']['on_the_way'], 2));
    } finally {
        DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update(['cost_total' => 0, 'deleted_at' => now()]);
        Cache::forget("agent-order-capacity:{$this->orgAgent->id}");
    }
});

test('agent order capacity is deterministic and reported in the organisation currency', function () {
    Cache::forget("agent-order-capacity:{$this->orgAgent->id}");

    $capacity = App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::run($this->orgAgent);

    expect($capacity['agent_capacity'])->toHaveKeys(['lands_for_us_per_30d', 'source', 'samples', 'usual_month_orders', 'restock_out_of_stock', 'container_value'])
        ->and($capacity['pipeline'])->toHaveKeys(['waiting', 'being_made', 'on_the_way', 'total'])
        ->and($capacity['container'])->toBe(['label' => '40 ft container', 'cbm' => 67.7])
        ->and(App\Actions\Procurement\OrgAgent\UpdateOrgAgent::make()->action($this->orgAgent, ['order_container' => '20ft'])->order_container)->toBe(App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum::TWENTY_FOOT)
        ->and(App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::run($this->orgAgent->fresh())['container']['cbm'])->toBe(33.2)
        ->and(App\Actions\Procurement\OrgAgent\UpdateOrgAgent::make()->action($this->orgAgent->fresh(), ['order_container' => '40ft'])->order_container)->toBe(App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum::FORTY_FOOT)
        ->and($capacity['currency'])->toBe($this->organisation->currency->code)
        ->and($capacity['warehouse'])->toHaveKeys([
            'total_locations',
            'empty_locations',
            'free_ratio',
            'inbound_open_po_lines',
            'agent_share_used',
            'agent_share_limit',
        ])
        ->and($capacity['blocked'])->toHaveKeys(['at_capacity', 'warehouse_full'])
        ->and($capacity['list'])->toHaveKeys(['value', 'lines', 'units']);
});

test('agent supplier performance surfaces per sub-supplier lateness', function () {
    $suppliers = App\Actions\Procurement\OrgAgent\GetAgentSupplierPerformance::run($this->orgAgent);

    expect($suppliers)->toBeArray();

    foreach ($suppliers as $supplier) {
        expect($supplier)->toHaveKeys([
            'supplier_id',
            'code',
            'days',
            'source',
            'open_orders',
            'late_orders',
            'worst_days_late',
            'open_deliveries',
            'list_lines',
        ])
            ->and($supplier['late_orders'])->toBeLessThanOrEqual($supplier['open_orders']);
    }
});

test('agent shopping list proposal orders whole cartons and respects the carton minimum', function () {
    $proposal = App\Actions\Procurement\OrgAgent\SuggestAgentShoppingList::make()
        ->handle($this->orgAgent, ['budget' => 100000, 'bucket' => 'out']);

    expect($proposal['currency'])->toBe($this->organisation->currency->code);

    foreach ($proposal['lines'] as $line) {
        expect($line['cartons'])->toBeGreaterThanOrEqual(1)
            ->and($line['quantity_units'])->toBe($line['cartons'] * $line['units_per_carton']);
    }

    expect(collect($proposal['lines'])->sum('cost'))->toBeLessThanOrEqual($proposal['budget']);
});

test('agent shopping list converts the SKO recommendation to units before rounding to cartons and sends the SKO size to the page', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $orgSupplierProduct->supplierProduct->update([
        'currency_id'          => $this->organisation->currency_id,
        'cost'                 => 1,
        'units_per_pack'       => 70,
        'units_per_carton'     => 2,
        'minimum_carton_order' => 1,
    ]);
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    $stockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id], ['available' => true]);
    $orgStock  = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0, 'packed_in' => 2, 'is_excluded_from_auto_ordering' => false]);
    $orgStock->stats()->updateOrCreate([], ['days_of_cover' => 0, 'predicted_daily_usage' => 1, 'stock_value' => 0, 'recommended_order_quantity' => 19.16, 'on_the_way_po_count' => 0]);
    OrgStockHasOrgSupplierProduct::create([
        'org_stock_id'                  => $orgStock->id,
        'org_supplier_product_id'       => $orgSupplierProduct->id,
        'stock_has_supplier_product_id' => $stockLink->id,
        'status'                        => true,
        'local_priority'                => 0,
    ]);
    DB::table('shopping_list_items')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();

    $suggest = App\Actions\Procurement\OrgAgent\SuggestAgentShoppingList::make();
    $lineFor = fn () => collect($suggest->lines($this->orgAgent, 'out'))->firstWhere('org_supplier_product_id', $orgSupplierProduct->id);

    expect($lineFor()['cartons'])->toBe(20)
        ->and($lineFor()['quantity_units'])->toBe(40);

    $orgStock->update(['packed_in' => 3]);
    expect($lineFor())->toBeNull();

    $orgStock->update(['packed_in' => 2]);
    $response = $this->get(route('grp.org.procurement.org_agents.show.shopping.items.index', [$this->organisation->slug, $this->orgAgent->slug]).'?cover=out');
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('items.data', fn ($items) => (float) collect($items)->firstWhere('id', $orgSupplierProduct->id)['units_per_pack'] === 2.0));
});

test('supplier shopping list suggestion rounds the SKO recommendation, in units, to cartons and skips a carton smaller than one SKO', function () {
    $fill = fn (array $candidate) => (fn () => $this->greedyFill([array_merge([
        'org_supplier_product_id' => 1,
        'code'                    => 'ABC',
        'name'                    => 'A thing',
        'cost'                    => 1.0,
        'units_per_carton'        => 2,
        'packed_in'               => 2,
        'minimum_carton_order'    => 1,
        'never_stocked'           => false,
        'health_rank'             => 'B',
        'cap_exempt'              => false,
        'our_stock'               => 0.0,
        'days_of_cover'           => 0.0,
        'recommended'             => 19.16,
        'quarterly_usage'         => 40.0,
    ], $candidate)], 10000))->call(new App\Actions\Procurement\ShoppingListItem\SuggestSupplierShoppingList());

    $lines = $fill([]);
    expect($lines[0]['quantity'])->toBe(40.0)
        ->and($lines[0]['cartons'])->toBe(20.0)
        ->and($lines[0]['cost'])->toBe(40.0);

    $usageOnly = $fill(['recommended' => null, 'quarterly_usage' => 10.0, 'our_stock' => 4.0]);
    expect($usageOnly[0]['quantity'])->toBe(12.0);

    expect($fill(['packed_in' => 3]))->toBeEmpty();
});

test('pages count SKOs with the org stock packed_in, not the supplier pack, and fall back to the supplier pack only without an org stock', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['units_per_pack' => 70, 'units_per_carton' => 140]);
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id], ['available' => true]);

    $unitsPerSko = fn (int $organisationId) => (float) DB::selectOne('select '.OrgStock::unitsPerSkoSql((string) $organisationId, (string) $supplierProduct->id).' as units')->units;

    expect($unitsPerSko($this->organisation->id))->toBe(70.0);

    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'packed_in' => 1]);

    expect($unitsPerSko($this->organisation->id))->toBe(1.0)
        ->and($unitsPerSko(0))->toBe(70.0);

    $draft = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line  = StorePurchaseOrderTransaction::make()->action($draft, $supplierProduct->historicSupplierProduct, $orgStock, [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 140]);

    $draftLine = collect(App\Actions\Procurement\OrgAgent\UI\ShowAgentDraftsToSend::make()->lines($this->orgAgent)[$draft->id] ?? [])->firstWhere('id', $line->id);
    expect($draftLine['skos'])->toBe(140.0);

    $resource = (new App\Http\Resources\Procurement\PurchaseOrderTransactionResource($line->fresh()))->toArray(request());
    expect((float) $resource['units_per_pack'])->toBe(1.0)
        ->and((float) $resource['units_per_carton'])->toBe(140.0);

    $orgStock->update(['packed_in' => null]);
    expect($unitsPerSko($this->organisation->id))->toBe(70.0)
        ->and((float) (new App\Http\Resources\Procurement\PurchaseOrderTransactionResource($line->fresh()))->toArray(request())['units_per_pack'])->toBe(70.0);
});

test('agent drafts to send flag the products still short of the supplier minimum carton order', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['units_per_carton' => 10, 'minimum_carton_order' => 5]);

    $draft = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line  = StorePurchaseOrderTransaction::make()->action($draft, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 30]);

    $card = fn () => collect(App\Actions\Procurement\OrgAgent\UI\ShowAgentDraftsToSend::make()->handle($this->orgAgent))->firstWhere('supplier_id', $supplierProduct->supplier_id);

    expect($card()['below_minimum'])->toBe([[
        'code'          => $supplierProduct->code,
        'name'          => $supplierProduct->name,
        'cartons'       => 3.0,
        'minimum'       => 5,
        'organisations' => [$this->organisation->code => 3.0],
    ]]);

    $line->update(['quantity_ordered' => 50]);
    expect($card()['below_minimum'])->toBe([]);
});

test('agent warehouse pages and the goods receipt count SKOs with the org stock packed_in', function () {
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['units_per_pack' => 70]);
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id], ['available' => true]);
    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'packed_in' => 1]);

    $receipt = App\Models\Procurement\AgentGoodsReceipt::create([
        'group_id'    => $this->agent->group_id,
        'agent_id'    => $this->agent->id,
        'supplier_id' => $supplierProduct->supplier_id,
        'number'      => (int) App\Models\Procurement\AgentGoodsReceipt::where('agent_id', $this->agent->id)->max('number') + 1,
        'reference'   => 'GR-'.Str::upper(Str::random(6)),
        'state'       => App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum::RECEIVED,
        'received_at' => now()->toDateString(),
        'quantity'    => 140,
    ]);
    $receipt->items()->create(['group_id' => $this->agent->group_id, 'organisation_id' => $this->organisation->id, 'supplier_product_id' => $supplierProduct->id, 'quantity' => 140]);
    App\Models\Procurement\AgentWarehouseStock::create([
        'group_id'               => $this->agent->group_id,
        'agent_id'               => $this->agent->id,
        'organisation_id'        => $this->organisation->id,
        'supplier_product_id'    => $supplierProduct->id,
        'agent_goods_receipt_id' => $receipt->id,
        'state'                  => App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum::RECEIVED,
        'quantity'               => 140,
    ]);

    $rows = App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentReceivedProducts::make()->handle($this->agent);
    $row  = collect($rows->items())->firstWhere('code', $supplierProduct->code);
    expect($row['units_per_sko'])->toBe(1.0)
        ->and($row['skos'])->toBe(140.0);

    expect(App\Actions\Procurement\AgentWarehouseStock\UI\GetAgentProductsInProductionToReceive::make()->handle($this->agent, ['supplier' => $supplierProduct->supplier_id]))->toBeArray();

    $this->get(route('grp.org.agent.warehouse.received.goods_receipts.show', [$this->agent->organisation->slug, $receipt->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.units_per_sko', 1));
});

test('agent receives a purchase order line in batches and rejected units go back to production', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line          = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 100]);
    $purchaseOrder->updateQuietly([
        'state'              => App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum::SUBMITTED,
        'delivery_state'     => App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        'submitted_at'       => now(),
        'sample_approved_at' => now(),
    ]);
    $line->updateQuietly(['state' => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum::SUBMITTED]);

    $remaining = fn () => (float) App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentProductsInProduction::inProductionLines($this->agent)
        ->where('purchase_order_transactions.id', $line->id)
        ->selectRaw(App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentProductsInProduction::remainingQuantitySql().' as remaining')
        ->value('remaining');
    $receive = fn (float $quantity) => App\Actions\Procurement\AgentGoodsReceipt\StoreAgentGoodsReceipt::make()->action($this->agent, [
        'lines' => [['purchase_order_transaction_id' => $line->id, 'quantity' => $quantity]],
    ]);
    $check = App\Actions\Procurement\AgentGoodsReceipt\CheckAgentGoodsReceiptItem::make();

    $item = $receive(100)->items()->sole();
    expect($purchaseOrder->refresh()->production_started_at)->not->toBeNull()
        ->and($purchaseOrder->areLinesLockedByProduction())->toBeTrue()
        ->and((new App\Http\Resources\Procurement\PurchaseOrderTransactionResource($line->fresh()))->toArray(request())['cancelRoute'])->toBeNull();

    $check->handle($item, ['quantity_rejected' => 10]);
    expect((float) $item->agentWarehouseStock->refresh()->quantity)->toBe(90.0)
        ->and($remaining())->toBe(10.0);

    $purchaseOrder->updateQuietly(['qc_passed_at' => now()]);
    $receivingLine = collect(App\Actions\Procurement\AgentGoodsReceipt\UI\GetPurchaseOrderGoodsReceipts::run($purchaseOrder->refresh(), true)['lines'])->firstWhere('id', $line->id);
    expect($remaining())->toBe(10.0)
        ->and($receivingLine['outstanding'])->toBe(10.0);

    expect(fn () => $receive(0.0004))->toThrow(Illuminate\Validation\ValidationException::class);

    $replacement = $receive(10);
    expect(fn () => $check->handle($item->refresh(), ['quantity_rejected' => 0]))->toThrow(Illuminate\Validation\ValidationException::class);

    App\Actions\Procurement\AgentGoodsReceipt\CancelAgentGoodsReceipt::make()->handle($replacement);
    expect($replacement->warehouseStocks()->count())->toBe(0)
        ->and($remaining())->toBe(10.0);

    expect((new PurchaseOrder(['production_started_at' => now()]))->areLinesLockedByProduction())->toBeFalse();
});

test('agent order in warehouse stage follows its goods receipts, checks and packing', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct = $orgSupplierProduct->supplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line          = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 100]);
    $purchaseOrder->updateQuietly([
        'state'              => App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum::SUBMITTED,
        'delivery_state'     => App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        'submitted_at'       => now(),
        'sample_approved_at' => now(),
    ]);
    $line->updateQuietly(['state' => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum::SUBMITTED]);

    $stage = fn () => collect(App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages::make()->forUi($purchaseOrder->refresh(), []))->firstWhere('key', 'in_warehouse');
    $steps = fn () => collect($stage()['steps'])->pluck('date', 'key')->map(fn ($date) => $date !== null)->all();
    $receive = fn (float $quantity) => App\Actions\Procurement\AgentGoodsReceipt\StoreAgentGoodsReceipt::make()->action($this->agent, [
        'lines' => [['purchase_order_transaction_id' => $line->id, 'quantity' => $quantity]],
    ]);

    $first = $receive(60)->items()->sole();
    expect($stage()['number'])->toBe(4)
        ->and($stage()['reached'])->toBeTrue()
        ->and($steps())->toBe(['warehouse_arrived_at' => true, 'produced_at' => false, 'warehouse_checked_at' => false, 'warehouse_packed_at' => false]);

    $second = $receive(40)->items()->sole();
    $check  = App\Actions\Procurement\AgentGoodsReceipt\CheckAgentGoodsReceiptItem::make();
    $check->handle($first, ['quantity_rejected' => 0]);
    expect($steps()['warehouse_checked_at'])->toBeFalse();

    $check->handle($second, ['quantity_rejected' => 0]);
    expect($steps())->toBe(['warehouse_arrived_at' => true, 'produced_at' => true, 'warehouse_checked_at' => true, 'warehouse_packed_at' => false]);

    App\Actions\Procurement\PurchaseOrderTransaction\PackPurchaseOrderTransaction::make()->action($line);
    expect($steps()['warehouse_packed_at'])->toBeTrue()
        ->and(collect(App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages::make()->forUi($purchaseOrder, []))->firstWhere('key', 'ready')['number'])->toBe(5);
});

test('agent receiving goods in its own warehouse ships only what its goods receipts accepted, and never marks handed over', function () {
    $agent    = StoreAgent::make()->action(group: $this->group, modelData: Agent::factory()->definition());
    $orgAgent = StoreOrgAgent::make()->action($this->organisation, $agent, []);
    App\Actions\SupplyChain\Agent\UpdateAgent::make()->action($agent, ['payment_terms' => '30 days']);

    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line          = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 100]);
    $purchaseOrder->updateQuietly([
        'state'                 => PurchaseOrderStateEnum::CONFIRMED,
        'delivery_state'        => App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        'submitted_at'          => now(),
        'confirmed_at'          => now(),
    ]);
    $line->updateQuietly(['state' => PurchaseOrderTransactionStateEnum::CONFIRMED]);

    $stages = fn () => collect(App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages::make()->forUi($purchaseOrder->refresh(), ['handed_over_at']))->keyBy('key');
    expect($stages())->not->toHaveKey('in_warehouse')
        ->and(collect($stages()['shipped']['marks'])->pluck('field')->all())->toBe(['handed_over_at']);

    App\Actions\SupplyChain\Agent\UpdateAgent::make()->action($agent, ['receives_goods_in_own_warehouse' => true]);
    expect($agent->refresh()->receivesGoodsInOwnWarehouse())->toBeTrue()
        ->and($agent->settings['payment_terms'])->toBe('30 days')
        ->and($stages())->toHaveKey('in_warehouse')
        ->and($stages()['shipped']['marks'])->toBe([])
        ->and($stages()['shipped']['steps'])->toBe([])
        ->and(fn () => App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage::make()->action($purchaseOrder, ['field' => 'handed_over_at', 'date' => now()->toDateString()]))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'goods receipt');

    $ship = fn (array $modelData = []) => App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh(), $modelData);
    $receive = fn (float $quantity) => App\Actions\Procurement\AgentGoodsReceipt\StoreAgentGoodsReceipt::make()->action($agent, [
        'lines' => [['purchase_order_transaction_id' => $line->id, 'quantity' => $quantity]],
    ]);

    expect(fn () => $ship())->toThrow(Illuminate\Validation\ValidationException::class, 'goods receipt');

    App\Actions\Procurement\AgentGoodsReceipt\CheckAgentGoodsReceiptItem::make()->handle($receive(60)->items()->sole(), ['quantity_rejected' => 5]);
    expect(fn () => $ship(['unit_quantities' => [$line->id => 56]]))->toThrow(Illuminate\Validation\ValidationException::class, 'Only 55 units');

    $stockDelivery = $ship();
    expect((float) $stockDelivery->items()->sole()->unit_quantity)->toBe(55.0)
        ->and(fn () => $ship())->toThrow(Illuminate\Validation\ValidationException::class, 'goods receipt')
        ->and(App\Actions\GoodsIn\StockDelivery\AddPurchaseOrderItemsToStockDelivery::addableItems($stockDelivery)->where('purchase_order_transactions.id', $line->id)->exists())->toBeFalse();

    $purchaseOrder->refresh()->updateQuietly(['delivery_state' => App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum::DISPATCHED, 'qc_passed_at' => now()]);
    $receive(40);
    $addable = App\Actions\GoodsIn\StockDelivery\AddPurchaseOrderItemsToStockDelivery::addableItems($stockDelivery)->where('purchase_order_transactions.id', $line->id)->sole();
    expect((float) $addable->quantity_awaiting_delivery)->toBe(40.0);

    $purchaseOrder->refresh()->updateQuietly(['approved_ready_at' => now()->subDay(), 'compliance_complete_at' => now()]);
    $handover = App\Actions\SupplyChain\Agent\UI\GetAgentCleanHandoverScore::make();
    expect($handover->isCleanHandover($purchaseOrder->refresh()))->toBeFalse();

    $receive(5);
    expect($handover->isCleanHandover($purchaseOrder->refresh()))->toBeTrue()
        ->and($handover->handle($agent)['quarters'][0]['chs'])->toEqual(100);
});

test('UI agent shopping dashboard renders', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show.shopping.dashboard', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/AgentShoppingDashboard')
            ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->pluck('label')->all() === [$this->orgAgent->slug, 'What to order', 'Ordering', 'POs to submit', 'Ordered', 'Preparing', 'On the way', 'Delivered'])
            ->has('title')
            ->has('stats')
            ->has('coverBuckets', 9)
            ->where('coverBuckets.0.bucket', 'out')
            ->where('coverBuckets.8.bucket', 'never')
            ->has('coverBuckets.0.on_order_value')
            ->where('coverTotal', fn ($total) => $total === collect($page->toArray()['props']['coverBuckets'])->sum('count'))
            ->has('leadTime.days')
            ->has('orderCapacity.warehouse')
            ->where('canPrepareOrder', true)
            ->missing('prepareOrder')
            ->loadDeferredProps(fn (AssertableInertia $deferred) => $deferred
                ->has('prepareOrder.buckets', 4)
                ->has('prepareOrder.url'))
            ->missing('openAgentPurchaseOrders')
            ->missing('suppliers');
    });
});

test('agent prepare order estimate comes from the same lines it would order', function () {
    $prepare = App\Actions\Procurement\OrgAgent\PrepareAgentPurchaseOrders::make();
    $lines   = $prepare->linesByBucket($this->orgAgent);
    $summary = collect($prepare->summary($this->orgAgent))->keyBy('bucket');

    expect(array_keys($lines))->toBe(App\Actions\Procurement\OrgAgent\PrepareAgentPurchaseOrders::ORDER_BUCKETS);

    foreach ($lines as $bucket => $bucketLines) {
        expect($summary[$bucket]['count'])->toBe(count($bucketLines))
            ->and($summary[$bucket]['order_lines'])->toBe(collect($bucketLines)->where('in_order', true)->count())
            ->and($summary[$bucket]['order_lines'])->toBeLessThanOrEqual($summary[$bucket]['count']);

        foreach ($bucketLines as $line) {
            expect($line['quantity_units'])->toBe($line['cartons'] * $line['units_per_carton']);
        }
    }

    expect(collect($lines['out'])->every(fn (array $line) => $line['in_order']))->toBeTrue();
});

test('agent prepare order puts the lines on draft purchase orders per supplier and does not order them twice', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);

    $orgStock = OrgStock::where('organisation_id', $this->organisation->id)
        ->whereNotExists(fn ($query) => $query->selectRaw('1')
            ->from('purchase_order_transactions')
            ->whereColumn('purchase_order_transactions.org_stock_id', 'org_stocks.id'))
        ->firstOrFail();
    $orgStockBefore = $orgStock->only(['state', 'quantity_available', 'is_excluded_from_auto_ordering', 'measured_lead_time_days', 'estimated_lead_time_days']);

    try {
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0.5, 'is_excluded_from_auto_ordering' => false, 'measured_lead_time_days' => null, 'estimated_lead_time_days' => null]);
        $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
        DB::table('supplier_products')->where('id', $orgSupplierProduct->supplier_product_id)->update(['currency_id' => $this->organisation->currency_id]);

        $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
            ['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id],
            ['available' => true]
        );
        OrgStockHasOrgSupplierProduct::updateOrCreate(
            ['org_stock_id' => $orgStock->id, 'org_supplier_product_id' => $orgSupplierProduct->id],
            ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => 1000]
        );

        $prepare = App\Actions\Procurement\OrgAgent\PrepareAgentPurchaseOrders::make();
        $line    = collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id);

        expect($line)->not->toBeNull()
            ->and($line['in_order'])->toBeTrue()
            ->and($line['cartons'])->toBe(1)
            ->and($line['quantity_units'])->toBe(100)
            ->and($line)->toHaveKeys(['cbm', 'cbm_estimated'])
            ->and($line['cbm_estimated'])->toBeTrue()
            ->and(collect($prepare->summary($this->orgAgent))->firstWhere('bucket', 'out'))->toHaveKeys(['cbm', 'order_cbm', 'cbm_estimated_lines', 'order_cbm_estimated_lines'])
            ->and(collect($prepare->summary($this->orgAgent))->firstWhere('bucket', 'out')['order_lines'])->toBeGreaterThanOrEqual(1);

        DB::table('supplier_products')->where('id', $orgSupplierProduct->supplier_product_id)->update(['cbm' => 0.25]);
        $measured = collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id);
        expect($measured['cbm'])->toBe(0.25)
            ->and($measured['cbm_estimated'])->toBeFalse();

        $shippedBefore = (int) $orgStock->stats->on_the_way_po_count;
        $orgStock->stats->update(['on_the_way_po_count' => 1]);
        expect(collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id))->toBeNull();
        $orgStock->stats->update(['on_the_way_po_count' => $shippedBefore]);

        expect(fn () => $prepare->handle($this->orgAgent, 1.0, ['out']))->toThrow(ValidationException::class);

        $result = $prepare->handle($this->orgAgent, null, ['out']);

        $line          = App\Models\Procurement\PurchaseOrderTransaction::where('org_supplier_product_id', $orgSupplierProduct->id)->latest('id')->firstOrFail();
        $purchaseOrder = $line->purchaseOrder;

        expect($result['added'])->toBeGreaterThanOrEqual(1)
            ->and($result['purchase_orders'])->toBeGreaterThanOrEqual(1)
            ->and((float) $line->quantity_ordered)->toBe(100.0)
            ->and(ShoppingListItem::where('org_supplier_product_id', $orgSupplierProduct->id)->exists())->toBeFalse()
            ->and($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS)
            ->and($purchaseOrder->parent_type)->toBe('OrgSupplier')
            ->and($purchaseOrder->parent_id)->toBe($orgSupplier->id)
            ->and(collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id))->toBeNull();

        $setLine = App\Actions\Procurement\OrgAgent\SetAgentDraftLine::make();
        App\Actions\Procurement\PurchaseOrder\SetPurchaseOrderReadyToSend::make()->handle($purchaseOrder, true, 'tester');

        $this->post(route('grp.org.procurement.org_agents.show.shopping.drafts.line', [$this->organisation->slug, $this->orgAgent->slug, $orgSupplierProduct->id]), ['quantity_units' => 300])
            ->assertSessionHasNoErrors();

        expect((float) $line->fresh()->quantity_ordered)->toBe(300.0)
            ->and($purchaseOrder->purchaseOrderTransactions()->where('org_supplier_product_id', $orgSupplierProduct->id)->count())->toBe(1)
            ->and($purchaseOrder->fresh()->data)->not->toHaveKey('ready_to_send');

        $secondDraft  = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
        $onEveryDraft = fn () => App\Models\Procurement\PurchaseOrderTransaction::whereIn('purchase_order_id', [$purchaseOrder->id, $secondDraft->id])
            ->where('org_supplier_product_id', $orgSupplierProduct->id)
            ->pluck('quantity_ordered')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();

        $line->fresh()->replicate()->fill(['quantity_ordered' => 500])->save();
        $line->fresh()->replicate()->fill(['quantity_ordered' => 700, 'purchase_order_id' => $secondDraft->id])->save();
        expect($onEveryDraft())->toHaveCount(3);

        $setLine->handle($this->orgAgent, $orgSupplierProduct, 400);

        expect($onEveryDraft())->toBe([400.0])
            ->and($line->fresh()->purchase_order_id)->toBe($purchaseOrder->id);

        DeletePurchaseOrder::make()->action($secondDraft->fresh());

        expect($setLine->handle($this->orgAgent, $orgSupplierProduct, 0))->toBeNull()
            ->and($purchaseOrder->purchaseOrderTransactions()->where('org_supplier_product_id', $orgSupplierProduct->id)->exists())->toBeFalse()
            ->and($purchaseOrder->fresh()->data['removed_org_supplier_product_ids'])->toContain($orgSupplierProduct->id)
            ->and(collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id))->toBeNull();

        $purchaseOrder->update(['data' => []]);
        expect(collect($prepare->linesByBucket($this->orgAgent)['out'])->firstWhere('org_supplier_product_id', $orgSupplierProduct->id))->not->toBeNull();

        $item = StoreShoppingListItem::make()->action($orgSupplierProduct, ['quantity_units' => 200]);

        expect(App\Actions\Procurement\OrgAgent\MoveAgentShoppingListsToDrafts::make()->handle($this->orgAgent->agent, dryRun: true)['picked'])->toBeGreaterThanOrEqual(1)
            ->and($item->fresh()->state)->toBe(ShoppingListItemStateEnum::OPEN);

        App\Actions\Procurement\OrgAgent\MoveAgentShoppingListsToDrafts::make()->handle($this->orgAgent->agent);

        expect($item->fresh()->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) App\Models\Procurement\PurchaseOrderTransaction::findOrFail($item->fresh()->purchase_order_transaction_id)->quantity_ordered)->toBe(200.0);

        $orderedLine = App\Models\Procurement\PurchaseOrderTransaction::findOrFail($item->fresh()->purchase_order_transaction_id);
        $orderedLine->purchaseOrder->update(['state' => PurchaseOrderStateEnum::CONFIRMED]);

        $this->delete(route('grp.models.purchase-order.transaction.delete', [$orderedLine->purchase_order_id, $orderedLine->id]))
            ->assertSessionHasErrors('state');

        expect($orderedLine->fresh())->not->toBeNull();

        $orderedLine->purchaseOrder->update(['state' => PurchaseOrderStateEnum::IN_PROCESS]);
        DeletePurchaseOrder::make()->action($orderedLine->purchaseOrder->fresh());

        expect($item->fresh()->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($item->fresh()->purchase_order_transaction_id)->toBeNull();

        $statsBefore = $orgStock->stats->only(['days_of_cover', 'predicted_daily_usage']);
        $buckets     = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::make();

        $orgStock->update(['quantity_available' => 100]);
        $orgStock->stats->update(['days_of_cover' => 20, 'predicted_daily_usage' => 5]);
        DB::table('supplier_products')->where('id', $orgSupplierProduct->supplier_product_id)->update(['measured_lead_time_days' => 2]);

        expect($buckets->orgSupplierProductIdsInBucket($this->orgAgent, 'w4'))->toContain($orgSupplierProduct->id)
            ->and($buckets->orgSupplierProductIdsInBucket($this->orgAgent, 'ok'))->not->toContain($orgSupplierProduct->id);

        $orgStock->stats->update($statsBefore);
    } finally {
        $orgStock->update($orgStockBefore);
    }
});

test('agent drafts are sent once every organisation is ready, or early with a reason taking the drafts of every organisation', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

    $theirOrgAgent    = OrgAgent::where('organisation_id', $otherOrganisation->id)->where('agent_id', $this->orgAgent->agent_id)->first()
        ?? StoreOrgAgent::make()->action($otherOrganisation, $this->orgAgent->agent, []);

    $supplier         = StoreSupplier::make()->action($this->orgAgent->agent, Supplier::factory()->definition());
    $ourOrgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $this->orgAgent->id)->firstOrFail();
    $theirOrgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $otherOrganisation->id)->firstOrFail();

    $draftWithLine = function (OrgSupplier $orgSupplier) {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
        DB::table('purchase_order_transactions')->insert([
            'group_id'          => $purchaseOrder->group_id,
            'organisation_id'   => $purchaseOrder->organisation_id,
            'purchase_order_id' => $purchaseOrder->id,
            'org_stock_id'      => $this->orgStocks[0]->id,
            'state'             => PurchaseOrderTransactionStateEnum::IN_PROCESS->value,
            'delivery_state'    => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
            'quantity_ordered'  => 100,
            'net_amount'        => 250,
            'data'              => '{}',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return $purchaseOrder;
    };

    $ours   = $draftWithLine($ourOrgSupplier);
    $theirs = $draftWithLine($theirOrgSupplier);

    $send = App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent::make();

    $drafts = App\Actions\Procurement\OrgAgent\UI\ShowAgentDraftsToSend::drafts($this->orgAgent)->where('po.supplier_id', $supplier->id)->pluck('po.id');
    expect($drafts->sort()->values()->all())->toBe(collect([$ours->id, $theirs->id])->sort()->values()->all());

    expect(fn () => $send->handle($this->orgAgent, [$supplier->id]))->toThrow(ValidationException::class)
        ->and($ours->fresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    expect(App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent::organisationsNotReady($ours))->toBe([$otherOrganisation->code]);
    expect(App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent::organisationsNotReady((clone $ours)->forceFill(['data' => ['from_upload_id' => 1]])))->toBe([]);

    $this->patch(route('grp.models.purchase-order.submit', $ours->id))->assertSessionHasErrors('purchase_order');
    expect($ours->fresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    $tell = App\Actions\Procurement\OrgAgent\TellOrganisationsAboutSupplierDraft::make();
    expect($tell->handle($this->orgAgent, $supplier, request()->user() ?? auth()->user()))->toBeInt()
        ->and($ours->fresh()->data['others_asked']['at'])->not->toBeNull()
        ->and(fn () => $tell->handle($this->orgAgent, $supplier, auth()->user()))->toThrow(ValidationException::class);

    $result = $send->handle($this->orgAgent, [$supplier->id], 'Supplier closes for the holidays', 'tester');

    expect($result)->toBe(['sent' => 2])
        ->and($ours->fresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($ours->fresh()->data['sent_without_waiting']['reason'])->toBe('Supplier closes for the holidays')
        ->and($ours->fresh()->data['sent_without_waiting']['waiting'])->toBe([$theirs->reference])
        ->and($theirs->fresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($theirs->fresh()->data['sent_without_waiting']['reason'])->toBe('Supplier closes for the holidays');

    $oursAgain = $draftWithLine($ourOrgSupplier);
    $theirs    = $draftWithLine($theirOrgSupplier);
    App\Actions\Procurement\PurchaseOrder\SetPurchaseOrderReadyToSend::make()->handle($theirs->fresh(), true, 'their buyer');

    expect($theirs->fresh()->data['ready_to_send']['by'])->toBe('their buyer');
    expect(App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent::organisationsNotReady($oursAgain))->toBe([]);

    $result = $send->handle($this->orgAgent, [$supplier->id]);

    expect($result)->toBe(['sent' => 2])
        ->and($oursAgain->fresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($theirs->fresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($theirs->fresh()->data)->not->toHaveKey('ready_to_send')
        ->and($theirs->fresh()->data)->not->toHaveKey('sent_without_waiting');

    expect(fn () => $send->handle($theirOrgAgent, [$supplier->id]))->toThrow(ValidationException::class);
});

test('agent purchase orders board starts at submitted so the agent never sees a draft', function () {
    $board = App\Actions\Procurement\PurchaseOrder\UI\ShowAgentPurchaseOrdersBoard::make()->handle($this->orgAgent->agent);

    expect(array_column($board['columns'], 'key'))->toBe(['submitted', 'confirmed', 'settled']);
});

test('UI agent drafts to send', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show.shopping.drafts.index', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/AgentDraftsToSend')
            ->has('cards')
            ->has('agentName')
            ->where('orgCurrency', $this->organisation->currency->code)
            ->where('pageHead.subNavigation.3.number', collect($page->toArray()['props']['cards'])->where('has_ours', true)->count())
            ->where('sendRoute.name', 'grp.org.procurement.org_agents.show.shopping.drafts.send')
            ->where('cards', fn ($cards) => collect($cards)->every(fn (array $card) => count($card['parts']) >= 1 && $card['lines'] >= 1
                && array_key_exists('org_exchange', $card) && ($card['currency'] !== $this->organisation->currency->code || $card['org_exchange'] === null)));
    });
});

test('UI agent drafts to send remembers the view the user picked', function () {
    $url = route('grp.org.procurement.org_agents.show.shopping.drafts.index', [$this->organisation->slug, $this->orgAgent->slug]);

    $this->get($url.'?view=cards')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tabs.current', 'cards')
        ->missing('lines')
        ->reloadOnly('lines', fn (AssertableInertia $reload) => $reload->has('lines')));

    $this->get($url.'?view=orders')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tabs.current', 'orders')
        ->has('tabs.navigation', 2)
        ->has('lines'));

    expect(auth()->user()->fresh()->settings['views']['agent_drafts_to_send'])->toBe('orders');

    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'orders'));
    $this->get($url.'?view=nonsense')->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'orders'));

    $this->get($url.'?view=cards')->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'cards'));
});

test('UI agent order pipeline renders', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show.order_pipeline', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/AgentOrderPipeline')
            ->has('title')
            ->has('shoppingList.open_items_count')
            ->has('leadTime.days')
            ->has('suppliers')
            ->has('openAgentPurchaseOrders')
            ->has('openStockDeliveries')
            ->has('stockDeliveriesRoute.name')
            ->has('agentPurchaseOrdersRoute.name');
    });
});

test('UI agent cover bucket items index', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show.shopping.items.index', [$this->organisation->slug, $this->orgAgent->slug]).'?cover=out');
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/AgentCoverBucketItems')
            ->where('bucket', 'out')
            ->where('bucketLabel', 'Out of stock')
            ->has('items.data')
            ->has('items.meta.total')
            ->where('items.data', fn ($items) => collect($items)->every(fn (array $item) => collect(['image', 'stock_in_locations', 'quarterly_usage', 'stock_cover', 'stock_deliveries', 'other_open_purchase_orders', 'units_per_pack', 'quantity_ordered', 'org_exchange'])->every(fn (string $key) => array_key_exists($key, $item))))
            ->has('miniCart.count')
            ->where('miniCart.listRoute.name', 'grp.org.procurement.org_agents.show.shopping.drafts.index')
            ->where('addRoute.name', 'grp.org.procurement.org_agents.show.shopping.drafts.line')
            ->has('addRoute.name');
    });
});

test('UI agent browse lists the whole catalogue as a standard table and searches, filters and sorts it', function () {
    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $code       = $orgSupplierProduct->supplierProduct->code;
    $supplierId = $orgSupplierProduct->supplierProduct->supplier_id;
    $url        = route('grp.org.procurement.org_agents.show.shopping.browse.index', [$this->organisation->slug, $this->orgAgent->slug]);

    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/AgentCoverBucketItems')
        ->where('bucket', null)
        ->where('bucketLabel', 'Ordering')
        ->has('filterOptions.cover', 9)
        ->has('filterOptions.rank', 5)
        ->where('filterOptions.supplier', fn ($suppliers) => collect($suppliers)->contains('value', $supplierId))
        ->where('filterOptions.cover', fn ($groups) => collect($groups)->sum('count') === $page->toArray()['props']['items']['meta']['total'])
        ->where('active.cover', null)
        ->where('active.drafts', false)
        ->has('bucketLabels.out.label')
        ->has('items.data')
        ->where('items.meta.total', fn ($total) => $total >= 1));

    $this->get($url.'?'.http_build_query(['filter' => ['global' => strtolower(substr($code, 2, 8))]]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('items.data', fn ($items) => collect($items)->contains('code', $code) && collect($items)->every(fn (array $item) => array_key_exists('bucket', $item))));

    $this->get($url.'?cover=w1')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('bucket', 'w1')
        ->where('active.cover', 'w1')
        ->where('items.data', fn ($items) => collect($items)->every(fn (array $item) => $item['bucket'] === 'w1')));

    $this->get($url.'?'.http_build_query(['filter' => ['global' => 'no-such-product-zzz']]))->assertInertia(fn (AssertableInertia $page) => $page->where('items.meta.total', 0));

    $this->get($url.'?'.http_build_query(['filter' => ['supplier' => $supplierId]]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('items.data', fn ($items) => collect($items)->contains('code', $code)));

    $this->get($url.'?drafts=1&'.http_build_query(['filter' => ['supplier' => $supplierId]]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('active.drafts', true)
        ->where('active.supplier', $supplierId)
        ->where('items.data', fn ($items) => !collect($items)->contains('code', $code)));

    $hidden = $url.'?hide_ordered=1&'.http_build_query(['filter' => ['global' => $code]]);
    $this->get($hidden)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('active.hide_ordered', true)
        ->where('items.data', fn ($items) => collect($items)->contains('code', $code)));

    $submittedOrder = StorePurchaseOrder::make()->action($orgSupplierProduct->orgSupplier, [], strict: false);
    DB::table('purchase_order_transactions')->insert([
        'group_id'            => $submittedOrder->group_id,
        'organisation_id'     => $submittedOrder->organisation_id,
        'purchase_order_id'   => $submittedOrder->id,
        'supplier_product_id' => $orgSupplierProduct->supplier_product_id,
        'org_stock_id'        => $this->orgStocks[0]->id,
        'state'               => PurchaseOrderTransactionStateEnum::SUBMITTED->value,
        'delivery_state'      => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
        'quantity_ordered'    => 100,
        'net_amount'          => 250,
        'data'                => '{}',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);
    $submittedOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    $this->get($hidden)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('items.data', fn ($items) => !collect($items)->contains('code', $code)));
    $this->get($url.'?'.http_build_query(['filter' => ['global' => $code]]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('active.hide_ordered', false)
        ->where('items.data', fn ($items) => collect($items)->contains('code', $code)));

    foreach (['code', '-code', 'name', '-supplier_code'] as $sort) {
        $this->get($url.'?sort='.$sort)->assertOk();
    }

    $ascending  = collect($this->get($url.'?sort=code&perPage=100')->viewData('page')['props']['items']['data'])->pluck('code');
    $descending = collect($this->get($url.'?sort=-code&perPage=100')->viewData('page')['props']['items']['data'])->pluck('code');

    expect($ascending->first())->not->toBe($descending->first())
        ->and($ascending->values()->all())->toBe($ascending->sort(SORT_STRING)->values()->all());
});

test('UI agent browse pictures a product with its own photo, not the one of a bundle that contains it', function () {
    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $code = $orgSupplierProduct->supplierProduct->code;

    $orgStock      = createUnlinkedOrgStock($this);
    $otherOrgStock = createUnlinkedOrgStock($this);
    $orgStockId = $orgStock->id;
    DB::table('org_stock_has_org_supplier_products')->insert([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id]),
        'org_stock_id'                  => $orgStockId,
        'org_supplier_product_id'       => $orgSupplierProduct->id,
        'status'                        => true,
        'local_priority'                => 1,
        'created_at'                    => now(),
        'updated_at'                    => now(),
    ]);

    [, , $shop] = createShop();
    [, $base]   = createProduct($shop);

    $ownProduct = $base->replicate();
    $ownProduct->fill(['code' => 'OWN-'.$code, 'slug' => Str::lower('own-'.$code), 'web_images' => ['main' => ['gallery' => 'own-photo']]])->save();
    $bundle = $base->replicate();
    $bundle->fill(['code' => 'KIT-'.$code, 'slug' => Str::lower('kit-'.$code), 'web_images' => ['main' => ['gallery' => 'bundle-photo']]])->save();

    DB::table('product_has_org_stocks')->insert([
        ['product_id' => $ownProduct->id, 'org_stock_id' => $orgStockId, 'quantity' => 1],
        ['product_id' => $bundle->id, 'org_stock_id' => $orgStockId, 'quantity' => 1],
        ['product_id' => $bundle->id, 'org_stock_id' => $otherOrgStock->id, 'quantity' => 1],
    ]);

    $url  = route('grp.org.procurement.org_agents.show.shopping.browse.index', [$this->organisation->slug, $this->orgAgent->slug]).'?'.http_build_query(['filter' => ['global' => $code]]);
    $item = collect($this->get($url)->viewData('page')['props']['items']['data'])->firstWhere('code', $code);

    DB::table('product_has_org_stocks')->whereIn('product_id', [$ownProduct->id, $bundle->id])->delete();
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();

    expect($bundle->id)->toBeGreaterThan($ownProduct->id)
        ->and($item['image'])->toBe('own-photo');
});

test('UI agent browse shows what the other organisations hold on their drafts for a product', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

    OrgAgent::where('organisation_id', $otherOrganisation->id)->where('agent_id', $this->orgAgent->agent_id)->first()
        ?? StoreOrgAgent::make()->action($otherOrganisation, $this->orgAgent->agent, []);

    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $supplierProduct  = $orgSupplierProduct->supplierProduct;
    $theirOrgSupplier = OrgSupplier::where('supplier_id', $supplierProduct->supplier_id)->where('organisation_id', $otherOrganisation->id)->firstOrFail();

    $url      = route('grp.org.procurement.org_agents.show.shopping.browse.index', [$this->organisation->slug, $this->orgAgent->slug]).'?'.http_build_query(['filter' => ['global' => $supplierProduct->code]]);
    $ourItem  = fn () => collect($this->get($url)->viewData('page')['props']['items']['data'])->firstWhere('code', $supplierProduct->code);

    expect($ourItem()['other_organisations_drafts'])->toBe([]);

    $theirDraft = StorePurchaseOrder::make()->action($theirOrgSupplier, [], strict: false);
    DB::table('purchase_order_transactions')->insert([
        'group_id'            => $theirDraft->group_id,
        'organisation_id'     => $theirDraft->organisation_id,
        'purchase_order_id'   => $theirDraft->id,
        'supplier_product_id' => $supplierProduct->id,
        'org_stock_id'        => $this->orgStocks[0]->id,
        'state'               => PurchaseOrderTransactionStateEnum::IN_PROCESS->value,
        'delivery_state'      => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
        'quantity_ordered'    => 300,
        'net_amount'          => 750,
        'data'                => '{}',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $item = $ourItem();

    expect($item['other_organisations_drafts'])->toBe([
        ['organisation_code' => $otherOrganisation->code, 'organisation_name' => $otherOrganisation->name, 'reference' => $theirDraft->reference, 'buyer_name' => DB::table('users')->where('id', $theirDraft->buyer_id)->value('contact_name'), 'units' => 300],
    ])->and($item['ordered_quantity'])->toBe(0);

    $ourDraft = StorePurchaseOrder::make()->action($orgSupplierProduct->orgSupplier, [], strict: false);

    $this->get(route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $ourDraft->slug]).'?tab=items')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('agent_ordering.addRoute.name', 'grp.org.procurement.org_agents.show.shopping.drafts.line')
            ->where('items.data', fn ($items) => collect($items)->pluck('code')->all() === [$supplierProduct->code]
                && $items[0]['other_organisations_drafts'][0]['organisation_code'] === $otherOrganisation->code)
            ->etc());

    $this->get(route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $ourDraft->slug]).'?tab=products')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.data', fn ($products) => collect($products)->pluck('code')->contains($supplierProduct->code)
                && array_key_exists('stock_cover', $products[0])
                && array_key_exists('other_organisations_drafts', $products[0]))
            ->has('products.ordering.cover')
            ->where('products.ordering.active.cover', null)
            ->etc());

    $productsUrl = route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $ourDraft->slug]).'?tab=products';

    expect(collect($this->get($productsUrl)->viewData('page')['props']['products']['data'])->firstWhere('code', $supplierProduct->code)['other_organisations_drafts'])->toEqual([[
        'organisation_code' => $otherOrganisation->code,
        'organisation_name' => $otherOrganisation->name,
        'reference'         => $theirDraft->reference,
        'buyer_name'        => DB::table('users')->where('id', $theirDraft->buyer_id)->value('contact_name'),
        'units'             => 300,
    ]]);

    $bucket      = collect($this->get($productsUrl)->viewData('page')['props']['products']['data'])->firstWhere('code', $supplierProduct->code)['bucket'];
    $otherBucket = collect(array_keys(App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::BUCKETS))->first(fn ($cover) => $cover !== $bucket);

    $this->get($productsUrl.'&'.http_build_query(['products_filter' => ['cover' => $otherBucket]]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.ordering.active.cover', $otherBucket)
            ->where('products.data', fn ($products) => !collect($products)->pluck('code')->contains($supplierProduct->code))
            ->etc());
});

test('agent draft purchase order items show our SKO code next to the supplier code', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $supplierProduct = $orgSupplierProduct->supplierProduct;
    $orgStock        = $this->orgStocks[0];

    $originalLinks   = DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->get()->map(fn ($link) => (array) $link)->all();

    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    DB::table('org_stock_has_org_supplier_products')->insert([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->where('stock_id', $orgStock->stock_id)->where('supplier_product_id', $supplierProduct->id)->value('id')
            ?? DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $supplierProduct->id]),
        'org_stock_id'            => $orgStock->id,
        'org_supplier_product_id' => $orgSupplierProduct->id,
        'status'                  => true,
        'local_priority'          => 100,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);

    expect($orgStock->code)->not->toBe($supplierProduct->code);

    $draft = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
    StorePurchaseOrderTransaction::make()->action($draft, $supplierProduct->historicSupplierProduct, $orgStock, PurchaseOrderTransaction::factory()->definition());

    $this->get(route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $draft->slug]).'?tab=items')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.code', $supplierProduct->code)
            ->where('items.data.0.org_stock_code', $orgStock->code)
            ->etc());

    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    DB::table('org_stock_has_org_supplier_products')->insert($originalLinks);
});

test('agent purchase order products tab hides products whose SKO is discontinued or the supplier no longer has, unless already on the draft', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    $supplierProduct = $orgSupplierProduct->supplierProduct;

    $orgStockId = DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->where('status', true)->value('org_stock_id');
    if (!$orgStockId) {
        $orgStockId = $this->orgStocks[0]->id;
        DB::table('org_stock_has_org_supplier_products')->insert([
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->where('stock_id', $this->orgStocks[0]->stock_id)->where('supplier_product_id', $supplierProduct->id)->value('id')
                ?? DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $this->orgStocks[0]->stock_id, 'supplier_product_id' => $supplierProduct->id]),
            'org_stock_id'            => $orgStockId,
            'org_supplier_product_id' => $orgSupplierProduct->id,
            'status'                  => true,
            'local_priority'          => 100,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }
    $originalState = DB::table('org_stocks')->where('id', $orgStockId)->value('state');

    $draft       = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
    $productsUrl = route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $draft->slug]).'?'.http_build_query(['tab' => 'products', 'products_filter' => ['global' => $supplierProduct->code]]);
    $listed      = fn () => collect($this->get($productsUrl)->viewData('page')['props']['products']['data'])->pluck('code')->contains($supplierProduct->code);

    try {
        DB::table('org_stocks')->where('id', $orgStockId)->update(['state' => OrgStockStateEnum::ACTIVE->value]);
        expect($listed())->toBeTrue();

        $supplierProduct->updateQuietly(['is_available' => false]);
        expect($listed())->toBeFalse();
        $supplierProduct->updateQuietly(['is_available' => true]);

        foreach ([OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED] as $state) {
            DB::table('org_stocks')->where('id', $orgStockId)->update(['state' => $state->value]);
            expect($listed())->toBeFalse();
        }

        DB::table('purchase_order_transactions')->insert([
            'group_id'                => $draft->group_id,
            'organisation_id'         => $draft->organisation_id,
            'purchase_order_id'       => $draft->id,
            'supplier_product_id'     => $supplierProduct->id,
            'org_supplier_product_id' => $orgSupplierProduct->id,
            'org_stock_id'            => $orgStockId,
            'state'                   => PurchaseOrderTransactionStateEnum::IN_PROCESS->value,
            'delivery_state'          => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
            'quantity_ordered'        => 100,
            'net_amount'              => 100,
            'data'                    => '{}',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
        expect($listed())->toBeTrue();
    } finally {
        DB::table('org_stocks')->where('id', $orgStockId)->update(['state' => $originalState]);
    }
});

test('agent purchase order products tab lists its supplier\'s product for an SKO another supplier is preferred for', function () {
    [$orgSupplier, $orgSupplierProduct]    = createAgentOrgSupplierWithProduct($this);
    [, $preferredOrgSupplierProduct]       = createAgentOrgSupplierWithProduct($this);
    $orgStock                              = $this->orgStocks[0];
    $originalState                         = $orgStock->state;

    $link = fn (OrgSupplierProduct $product, int $priority) => DB::table('org_stock_has_org_supplier_products')->updateOrInsert(
        ['org_stock_id' => $orgStock->id, 'org_supplier_product_id' => $product->id],
        [
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->where('stock_id', $orgStock->stock_id)->where('supplier_product_id', $product->supplier_product_id)->value('id')
                ?? DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $product->supplier_product_id]),
            'status'         => true,
            'local_priority' => $priority,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]
    );

    foreach ([$orgSupplierProduct, $preferredOrgSupplierProduct] as $product) {
        $product->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
    }
    $link($orgSupplierProduct, 0);
    $link($preferredOrgSupplierProduct, 100);
    DB::table('org_stocks')->where('id', $orgStock->id)->update(['state' => OrgStockStateEnum::ACTIVE->value]);

    $code  = $orgSupplierProduct->supplierProduct->code;
    $draft = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);

    try {
        $products = $this->get(route('grp.org.procurement.org_agents.show.purchase-orders.show', [$this->organisation->slug, $this->orgAgent->slug, $draft->slug]).'?'.http_build_query(['tab' => 'products', 'products_filter' => ['global' => $code]]))
            ->viewData('page')['props']['products']['data'];
        $browse   = GetAgentStockCoverBuckets::make()->scopedQuery($this->orgAgent)->where('osp.id', $orgSupplierProduct->id)->exists();

        expect(collect($products)->pluck('code'))->toContain($code)
            ->and($browse)->toBeFalse();
    } finally {
        DB::table('org_stocks')->where('id', $orgStock->id)->update(['state' => $originalState instanceof OrgStockStateEnum ? $originalState->value : $originalState]);
    }
});

test('a buyer short of a supplier minimum asks the other organisations, who see it at once and get email and Slack grouped', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

    OrgAgent::where('organisation_id', $otherOrganisation->id)->where('agent_id', $this->orgAgent->agent_id)->first()
        ?? StoreOrgAgent::make()->action($otherOrganisation, $this->orgAgent->agent, []);

    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $supplierProduct        = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['minimum_carton_order' => 20]);
    $theirOrgSupplier = OrgSupplier::where('supplier_id', $supplierProduct->supplier_id)->where('organisation_id', $otherOrganisation->id)->firstOrFail();

    $theirBuyer = App\Actions\SysAdmin\Guest\StoreGuest::make()->action($this->group, array_merge(App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []]))->getUser();
    $theirBuyer->update(['settings' => array_merge($theirBuyer->settings ?? [], ['notifications' => ['purchase_order_minimum_poke' => ['email']]])]);
    $theirDraft = StorePurchaseOrder::make()->action($theirOrgSupplier, [], strict: false);
    $theirDraft->updateQuietly(['buyer_id' => $theirBuyer->id]);

    Illuminate\Support\Facades\Notification::fake();
    $poke = fn () => $this->postJson(route('grp.models.org_supplier_product.minimum_poke', ['orgSupplierProduct' => $orgSupplierProduct->id]), ['cartons_short' => 6])->assertOk()->json('notified');

    expect($poke())->toBe(1)
        ->and($poke())->toBe(0);

    Illuminate\Support\Facades\Notification::assertSentTo(
        $theirBuyer,
        App\Notifications\PurchaseOrderMinimumPokeNotification::class,
        fn ($notification) => $notification->purchaseOrder->is($theirDraft) && str_contains($notification->body, $theirDraft->reference)
    );
    Illuminate\Support\Facades\Notification::assertNotSentTo($theirBuyer, App\Notifications\PurchaseOrderMinimumPokeDigestNotification::class);

    expect(App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderMinimumPokeDigests::run())->toBeGreaterThanOrEqual(1)
        ->and(App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderMinimumPokeDigests::run())->toBe(0)
        ->and(DB::table('purchase_order_minimum_pokes')->where('recipient_id', $theirBuyer->id)->whereNull('digested_at')->exists())->toBeFalse();

    Illuminate\Support\Facades\Notification::assertSentTo(
        $theirBuyer,
        App\Notifications\PurchaseOrderMinimumPokeDigestNotification::class,
        fn ($notification) => count($notification->requests) === 1 && str_contains($notification->requests[0]['line'], $supplierProduct->code)
    );

    app()->detectEnvironment(fn () => 'production');
    $productionMail = (new App\Notifications\PurchaseOrderMinimumPokeDigestNotification([['line' => 'x', 'url' => '']]))->toMail($theirBuyer);
    app()->detectEnvironment(fn () => 'testing');

    expect($productionMail->mailer)->toBe('ses');
});

test('agent misplaced shopping list cleanup only accepts non-orderable buckets', function () {
    expect(fn () => App\Actions\Procurement\OrgAgent\RemoveMisplacedAgentShoppingListItems::make()->handle($this->orgAgent, 'out'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(App\Actions\Procurement\OrgAgent\RemoveMisplacedAgentShoppingListItems::make()->handle($this->orgAgent, 'ok'))
        ->toBeInt();
});

test('UI supplier shopping dashboard renders', function () {
    [$orgSupplier] = independentOrgSupplierFixture($this);

    $response = $this->get(route('grp.org.procurement.org_suppliers.show.shopping.dashboard', [$this->organisation->slug, $orgSupplier->slug]));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/SupplierShoppingDashboard')
            ->has('title')
            ->has('stats')
            ->has('coverBuckets', 8)
            ->where('coverBuckets.0.bucket', 'out')
            ->where('coverBuckets.7.bucket', 'never')
            ->where('coverTotal', fn ($total) => $total === collect($page->toArray()['props']['coverBuckets'])->sum('count'))
            ->has('coverBuckets.0.ranks')
            ->has('leadTime.days')
            ->where('leadTime.source', 'estimate')
            ->has('orderCapacity.warehouse')
            ->has('latePurchaseOrders')
            ->has('openStockDeliveries')
            ->has('shoppingListRoute.name')
            ->has('stockDeliveriesRoute.name');
    });
});

test('UI supplier drafts to submit shows only our drafts with products, styled like the agent page', function () {
    [$orgSupplier, , $orgSupplierProduct] = independentOrgSupplierFixture($this);

    $draft = App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder::make()->action($orgSupplier, []);

    $this->get(route('grp.org.procurement.org_suppliers.show.shopping.drafts.index', [$this->organisation->slug, $orgSupplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', 0)->where('draft', null));

    StorePurchaseOrderTransaction::make()->action($draft, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 200]);

    $response = $this->get(route('grp.org.procurement.org_suppliers.show.shopping.drafts.index', [$this->organisation->slug, $orgSupplier->slug]));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/SupplierDraftsToSend')
        ->has('data.data', 1)
        ->where('draft.purchase_order_id', $draft->id)
        ->where('draft.lines', 1)
        ->where('data.data.0.cartons', fn ($cartons) => (float) $cartons === 2.0)
        ->where('pageHead.subNavigation', fn ($subNavigation) => collect($subNavigation)->firstWhere('route.name', 'grp.org.procurement.org_suppliers.show.shopping.drafts.index')['number'] === 1));

    $this->get(route('grp.org.procurement.org_suppliers.show.shopping.drafts.index', [$this->organisation->slug, $this->orgSupplier->slug]))
        ->assertNotFound();
});

test('supplier shopping dashboard is only for suppliers we buy from directly', function () {
    expect($this->orgSupplier->org_agent_id)->not->toBeNull();

    $this->get(route('grp.org.procurement.org_suppliers.show.shopping.dashboard', [$this->organisation->slug, $this->orgSupplier->slug]))
        ->assertNotFound();
});

test('supplier lead time is read from the products, never editable on the relationship', function () {
    [$orgSupplier, $supplierProduct] = independentOrgSupplierFixture($this);

    expect(App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime::run($orgSupplier))
        ->toMatchArray(['days' => 9, 'source' => 'estimate', 'measured_products' => 0]);

    $supplierProduct->update(['measured_lead_time_days' => 21, 'lead_time_samples' => 4]);

    expect(App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime::run($orgSupplier))
        ->toMatchArray(['days' => 21, 'source' => 'measured']);
});

test('UI supplier cover bucket items index', function () {
    [$orgSupplier] = independentOrgSupplierFixture($this);

    $response = $this->get(route('grp.org.procurement.org_suppliers.show.shopping.items.index', [$this->organisation->slug, $orgSupplier->slug]).'?cover=never');
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/SupplierCoverBucketItems')
            ->where('bucket', 'never')
            ->where('bucketLabel', 'We never stocked')
            ->has('items.data')
            ->has('orgSupplier.currency');
    });
});

test('organisation stock cover buckets judge each active sko against its lead time', function () {
    $orgStock = $this->orgStocks[0];
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'estimated_lead_time_days' => 10, 'quantity_available' => 50]);
    $orgStock->stock->update(['state' => StockStateEnum::ACTIVE]);
    DB::table('stock_delivery_items')->where('org_stock_id', $orgStock->id)->whereNull('deleted_at')->update(['deleted_at' => now()]);
    DB::table('purchase_order_transactions')->where('org_stock_id', $orgStock->id)->whereNull('deleted_at')->update(['deleted_at' => now()]);
    $buckets = App\Actions\Procurement\GetOrganisationStockCoverBuckets::make();

    $bucketFor = function (array $stats) use ($orgStock, $buckets) {
        $orgStock->stats->update($stats);

        return $buckets->bucketOf($orgStock->fresh());
    };

    expect($bucketFor(['days_of_cover' => 5, 'predicted_daily_usage' => 10, 'stock_value' => 100]))->toBe('w1')
        ->and($bucketFor(['days_of_cover' => 15, 'predicted_daily_usage' => 3, 'stock_value' => 100]))->toBe('w2')
        ->and($bucketFor(['days_of_cover' => 60, 'predicted_daily_usage' => 1, 'stock_value' => 100]))->toBe('ok')
        ->and($bucketFor(['days_of_cover' => 300, 'predicted_daily_usage' => 0.2, 'stock_value' => 100]))->toBe('excess')
        ->and($bucketFor(['days_of_cover' => null, 'predicted_daily_usage' => 0, 'stock_value' => 100]))->toBe('dead');

    $orgStock->update(['measured_lead_time_days' => null, 'estimated_lead_time_days' => 60]);
    expect($bucketFor(['days_of_cover' => 100, 'predicted_daily_usage' => 1, 'stock_value' => 100]))->toBe('w2')
        ->and($bucketFor(['days_of_cover' => 150, 'predicted_daily_usage' => 1, 'stock_value' => 100]))->toBe('excess');
    $orgStock->update(['estimated_lead_time_days' => 10]);

    $comingDelivery = createStockDeliveryWithItems($this, 'COVER-COMING-'.uniqid(), [600 * max(1, (int) $orgStock->packed_in)]);
    expect($bucketFor(['days_of_cover' => 5, 'predicted_daily_usage' => 10, 'stock_value' => 100]))->toBe('ok');

    $comingDelivery->update(['state' => App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum::CANCELLED]);
    expect($buckets->bucketOf($orgStock->fresh()))->toBe('w1');

    $orgStock->update(['quantity_available' => 0]);
    expect($buckets->bucketOf($orgStock->fresh()))->toBeNull();

    orgStockOnSaleAndReceived($orgStock);
    expect($buckets->bucketOf($orgStock->fresh()))->toBe('out');

    $counts = collect(App\Actions\Procurement\GetOrganisationStockCoverBuckets::run($this->organisation))->pluck('count', 'bucket');
    expect($counts->keys()->all())->toBe(array_keys(App\Actions\Procurement\GetOrganisationStockCoverBuckets::BUCKETS))
        ->and($counts['out'])->toBeGreaterThanOrEqual(1);
});

test('UI organisation stock cover items index', function () {
    $orgStock = orgStockOnSaleAndReceived($this->orgStocks[0]);
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'quantity_available' => 0]);

    $response = $this->get(route('grp.org.procurement.stock_cover.index', [$this->organisation->slug, 'elements[cover]' => 'out']));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) use ($orgStock) {
        $page
            ->component('Procurement/OrganisationStockCoverItems')
            ->where('title', 'Stock levels')
            ->where('data.data', fn ($rows) => collect($rows)->pluck('bucket')->unique()->values()->all() === ['out']
                && collect($rows)->pluck('code')->contains($orgStock->code))
            ->has('queryBuilderProps.default.elementGroups')
            ->has('exportRoute.name');
    });
});

test('organisation stock cover export downloads the filtered buckets as csv', function () {
    $orgStock = orgStockOnSaleAndReceived($this->orgStocks[0]);
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'quantity_available' => 0]);

    $response = $this->get(route('grp.org.procurement.stock_cover.export', [$this->organisation->slug, 'elements[cover]' => 'out,w1']));
    $response->assertOk();

    expect($response->streamedContent())->toContain('Days of cover')->toContain($orgStock->code)->toContain('Out of stock');

    $this->get(route('grp.org.procurement.stock_cover.export', [$this->organisation->slug, 'elements[cover]' => 'nope']))->assertRedirect();
});

test('deliveries in the warehouse are never late, dispatched ones get their time at sea and POs without dates are late', function () {
    $goods = App\Actions\Goods\UI\ShowGoodsDashboard::make();
    $delivery = fn (string $state, ?string $enteredAt, ?string $purchaseOrderEta = null) => (object) [
        'state'              => $state,
        'state_entered_at'   => $enteredAt,
        'delivery_date'      => null,
        'purchase_order_eta' => $purchaseOrderEta,
    ];

    expect($goods->stockDeliveryTiming($delivery('received', now()->subMonths(3)->toDateTimeString())))
        ->toBe(['eta' => now()->addDay()->toDateString(), 'is_late' => false])
        ->and($goods->stockDeliveryTiming($delivery('dispatched', now()->subDays(10)->toDateTimeString(), now()->subMonth()->toDateString())))
        ->toBe(['eta' => now()->subDays(10)->addDays(60)->toDateString(), 'is_late' => false])
        ->and($goods->stockDeliveryTiming($delivery('dispatched', now()->subDays(70)->toDateTimeString()))['is_late'])->toBeTrue()
        ->and($goods->stockDeliveryTiming($delivery('confirmed', now()->toDateTimeString(), now()->subDay()->toDateString()))['is_late'])->toBeTrue()
        ->and($goods->stockDeliveryTiming($delivery('confirmed', now()->subDays(5)->toDateTimeString()))['is_late'])->toBeFalse()
        ->and($goods->isPurchaseOrderLate((object) ['estimated_received_at' => null, 'submitted_at' => null, 'measured_lead_time_days' => null]))->toBeTrue()
        ->and($goods->isPurchaseOrderLate((object) ['estimated_received_at' => now()->addWeek()->toDateString(), 'submitted_at' => null, 'measured_lead_time_days' => null]))->toBeFalse();
});

test('the manufacturing hub source is named after the hub organisations', function () {
    $buckets = App\Actions\Procurement\GetOrganisationStockCoverBuckets::make();
    $wasHub  = $this->otherOrganisation->is_manufacturing_hub;
    $this->otherOrganisation->update(['is_manufacturing_hub' => true]);

    expect($buckets->sourceOptions($this->group->id)['hub'])->toContain($this->otherOrganisation->name)
        ->and($buckets->sourceOptions($this->group->id)['agent'])->toBe('Agents');

    $this->otherOrganisation->update(['is_manufacturing_hub' => $wasHub]);
});

test('procurement dashboard lists stock levels linking to each bucket', function () {
    $response = $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->has('stockLevels', 8)
            ->where('stockLevels.0.label', 'Out of stock')
            ->where('stockLevels.0.route.name', 'grp.org.procurement.stock_cover.index')
            ->where('stockLevels.0.route.parameters._query', ['elements[cover]' => 'out'])
            ->where('stockLevels.5.bucket', 'ok')
            ->where('stockLevels.6.bucket', 'excess')
            ->etc();
    });
});

test('stock outs are projected eight weeks ahead from forecast demand, each sko carrying the sales it would lose', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);
    $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'is_on_demand' => false, 'measured_lead_time_days' => null, 'estimated_lead_time_days' => 10, 'quantity_available' => 10]);
    $orgStock->stats->update(['predicted_daily_usage' => 1, 'demand_variability' => 0, 'forecast_source' => 'croston', 'demand_forecast' => null]);

    $windowEnd   = today()->startOfMonth();
    $windowStart = $windowEnd->copy()->subMonths(6);
    $seriesId    = DB::table('org_stock_time_series')->insertGetId(['org_stock_id' => $orgStock->id, 'frequency' => App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum::MONTHLY->value]);
    DB::table('org_stock_time_series_records')->insert(['org_stock_time_series_id' => $seriesId, 'frequency' => 'M', 'from' => $windowStart->toDateString(), 'sales_org_currency_external' => 2 * $windowStart->diffInDays($windowEnd)]);

    App\Actions\Procurement\ProjectOrganisationStockOuts::run($this->organisation);

    $projection = $this->organisation->procurementStats->fresh()->stock_out_projection;
    expect($projection['from'])->toBe(today()->addDay()->toDateString())
        ->and($projection['sources']['all'])->toHaveCount(56)
        ->and((float) $orgStock->stats->fresh()->projected_lost_revenue)->toEqualWithDelta((40 - 9) * 2, 0.01);

    $history = App\Actions\Procurement\GetStockOutsHistory::run($this->organisation, '1m');
    expect($history['projection'])->not->toBeEmpty()
        ->and($history['projection'][0])->toHaveKeys(['date', 'out_of_stock', 'lost_per_day'])
        ->and(collect($history['projection'])->pluck('date')->min())->toBeGreaterThan(collect($history['series'])->pluck('date')->max() ?? '');

    $this->get(route('grp.org.procurement.stock_cover.index', [$this->organisation->slug, 'sort' => '-projected_lost_revenue']))->assertOk();

    $onDemandStock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $onDemand      = orgStockOnSaleAndReceived(createOrgStocks($this->organisation, [$onDemandStock])[0]);
    $onDemand->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0, 'is_on_demand' => true]);
    $hydrator = App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateOutOfStock::make();

    expect($hydrator->stockOutOrgStockIds([$onDemand->id], today()))->toBe([]);
    $onDemand->update(['is_on_demand' => false]);
    expect($hydrator->stockOutOrgStockIds([$onDemand->id], today()))->toBe([$onDemand->id]);
});

test('procurement dashboard charts stock outs and their estimated lost revenue', function () {
    $todayKey = ['organisation_id' => $this->organisation->id, 'date' => today()->toDateString()];
    $previousTodayHistory = DB::table('organisation_stock_histories')->where($todayKey)->first();
    DB::table('organisation_stock_histories')->updateOrInsert($todayKey, [
        'group_id'                            => $this->organisation->group_id,
        'number_org_stocks'                   => 40,
        'number_out_of_stock_org_stocks'      => 10,
        'number_location_org_stocks'          => 40,
        'estimated_lost_revenue_org_currency' => 125.5,
    ]);
    $organisationStockHistoryId = DB::table('organisation_stock_histories')->where($todayKey)->value('id');

    $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug, 'period' => '1m']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockOuts.period', '1m')
            ->where('stockOuts.unit', 'day')
            ->where('stockOuts.now.out_of_stock', 10)
            ->where('stockOuts.now.percentage', 25)
            ->where('stockOuts.lost_total', 126)
            ->has('stockOuts.series', 1)
            ->etc());

    $hydrator = App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateOutOfStock::make();

    $aliveOrgStockIds      = $hydrator->aliveOrgStockIds($this->organisation->id, today());
    $inStockOrgStockIds    = DB::table('org_stock_histories')->where('organisation_stock_history_id', $organisationStockHistoryId)->where('quantity_in_locations', '>=', 1)->pluck('org_stock_id')->all();
    $outOfStockOrgStockIds = array_values(array_diff($aliveOrgStockIds, $inStockOrgStockIds));
    expect($outOfStockOrgStockIds)->not->toBeEmpty();

    $stockOutIds = $hydrator->stockOutOrgStockIds($outOfStockOrgStockIds, today());
    $onSaleOrgStock = orgStockOnSaleAndReceived(App\Models\Inventory\OrgStock::whereIn('id', array_diff($outOfStockOrgStockIds, $stockOutIds))->firstOrFail());
    $stockOutIds[] = $onSaleOrgStock->id;
    $hydrator->handle($organisationStockHistoryId);
    $organisationStockHistory = DB::table('organisation_stock_histories')->where('id', $organisationStockHistoryId)->first();

    expect($organisationStockHistory->number_out_of_stock_org_stocks)->toBe(count($stockOutIds))
        ->and($organisationStockHistory->number_org_stocks)->toBe(count($aliveOrgStockIds) - count($outOfStockOrgStockIds) + count($stockOutIds))
        ->and((float)$organisationStockHistory->estimated_lost_revenue_org_currency)->toEqualWithDelta($hydrator->estimatedLostRevenue($stockOutIds, today()), 0.05);

    $sourceRows = DB::table('organisation_stock_history_sources')->where('organisation_stock_history_id', $organisationStockHistoryId)->get();
    expect($sourceRows->pluck('source')->sort()->values()->all())->toBe(collect(array_keys(App\Actions\Procurement\GetOrganisationStockCoverBuckets::SOURCES))->sort()->values()->all())
        ->and((int) $sourceRows->sum('number_out_of_stock_org_stocks'))->toBe(count($stockOutIds))
        ->and((int) $sourceRows->sum('number_org_stocks'))->toBe($organisationStockHistory->number_org_stocks);

    $source = $hydrator->sourceByOrgStockId($this->organisation->id)[$onSaleOrgStock->id];
    $onSaleOrgStock->update(['quantity_available' => 0]);

    $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug, 'period' => '1m', 'source' => $source]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockOuts.source', $source)
            ->where('stockOuts.sources.agent.label', 'Agents')
            ->where("stockOuts.sources.$source.out_of_stock", $sourceRows->firstWhere('source', $source)->number_out_of_stock_org_stocks)
            ->where('stockOuts.now.out_of_stock', $sourceRows->firstWhere('source', $source)->number_out_of_stock_org_stocks)
            ->where('stockLevels.0.bucket', 'out')
            ->where('stockLevels.0.count', fn ($count) => $count >= 1)
            ->where('stockLevels.0.in_transit', fn ($inTransit) => $inTransit >= 0)
            ->has('stockLevels.0.arrivals')
            ->etc());

    $onSaleOrgStock->update(['is_fresh' => true]);
    $hydrator->handle($organisationStockHistoryId);

    expect(DB::table('organisation_stock_histories')->where('id', $organisationStockHistoryId)->value('number_out_of_stock_org_stocks'))->toBe(count($stockOutIds) - 1);

    $freshOrgStock = $onSaleOrgStock;

    $freshOrgStock->update(['is_fresh' => false]);

    if ($previousTodayHistory) {
        DB::table('organisation_stock_histories')->where('id', $organisationStockHistoryId)->update(Arr::except((array) $previousTodayHistory, ['id']));
    } else {
        DB::table('organisation_stock_histories')->where('id', $organisationStockHistoryId)->delete();
    }
});

test('supplier misplaced shopping list cleanup only accepts non-orderable buckets', function () {
    [$orgSupplier] = independentOrgSupplierFixture($this);

    expect(fn () => App\Actions\Procurement\OrgSupplier\RemoveMisplacedSupplierShoppingListItems::make()->handle($orgSupplier, 'out'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(App\Actions\Procurement\OrgSupplier\RemoveMisplacedSupplierShoppingListItems::make()->handle($orgSupplier, 'ok'))
        ->toBeInt();
});

test('a supplier product on a discontinuing SKO is dead stock, never out of stock (HELP-4445)', function () {
    [$orgSupplier, $supplierProduct, $orgSupplierProduct] = independentOrgSupplierFixture($this);
    $buckets = App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets::make();

    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        App\Actions\Goods\Stock\StoreStock::make()->action($this->group, array_merge(App\Models\Goods\Stock::factory()->definition(), ['state' => App\Enums\Goods\Stock\StockStateEnum::ACTIVE]))
    );
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    DB::table('org_stock_has_org_supplier_products')->insert([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $supplierProduct->id]),
        'org_stock_id'                  => $orgStock->id,
        'org_supplier_product_id'       => $orgSupplierProduct->id,
        'status'                        => true,
        'created_at'                    => now(),
        'updated_at'                    => now(),
    ]);
    $orgStock->update(['state' => App\Enums\Inventory\OrgStock\OrgStockStateEnum::DISCONTINUING, 'quantity_available' => 0]);

    expect($buckets->orgSupplierProductIdsInBucket($orgSupplier, 'out'))->not->toContain($orgSupplierProduct->id)
        ->and($buckets->orgSupplierProductIdsInBucket($orgSupplier, 'dead'))->toContain($orgSupplierProduct->id)
        ->and(collect((fn () => $this->candidates($orgSupplier, null, null))->call(new App\Actions\Procurement\ShoppingListItem\SuggestSupplierShoppingList()))
            ->pluck('org_supplier_product_id'))->not->toContain($orgSupplierProduct->id);
});

test('supplier order budget does not block adds to the shopping list', function () {
    [$orgSupplier, , $orgSupplierProduct] = independentOrgSupplierFixture($this);

    Cache::put("supplier-order-capacity:{$orgSupplier->id}", [
        ['delivers_to_us_per_30d' => 0.01, 'source' => 'sales', 'samples' => 0],
        [
            'total_locations'       => 100,
            'empty_locations'       => 50,
            'free_ratio'            => 0.5,
            'inbound_open_po_lines' => 0,
            'supplier_share_used'   => 0,
            'supplier_share_limit'  => 10,
        ],
    ], now()->addMinutes(15));

    $linkedOrgStock = App\Actions\Procurement\OrgSupplier\GetSupplierOrderCapacity::linkedOrgStock($orgSupplierProduct);
    $linkedOrgStock?->update(['quantity_available' => 10, 'health_rank' => null]);

    $first = StoreShoppingListItem::make()->action($orgSupplierProduct, ['quantity_units' => 5]);

    expect(App\Actions\Procurement\OrgSupplier\GetSupplierOrderCapacity::run($orgSupplier)['blocked']['at_capacity'])->toBeTrue()
        ->and($second = StoreShoppingListItem::make()->action($orgSupplierProduct, ['quantity_units' => 1]))
        ->toBeInstanceOf(ShoppingListItem::class);

    DeleteShoppingListItem::make()->action($second);
    DeleteShoppingListItem::make()->action($first);
    Cache::forget("supplier-order-capacity:{$orgSupplier->id}");
});

test('agent order budget does not block adds to the shopping list', function () {
    $this->orgSupplier->update(['org_agent_id' => $this->orgAgent->id, 'agent_id' => $this->orgAgent->agent_id]);
    $this->orgSupplierProduct->update(['org_agent_id' => $this->orgAgent->id]);

    $stock    = $this->supplierProduct->stocks()->first() ?? $this->stock;
    $orgStock = OrgStock::where('organisation_id', $this->organisation->id)->where('stock_id', $stock->id)->first()
        ?? createOrgStocks($this->organisation, [$stock])[0];

    if (!$this->supplierProduct->stocks()->where('stocks.id', $stock->id)->exists()) {
        $this->supplierProduct->stocks()->attach($stock->id, ['available' => true, 'priority' => 1]);
    }

    $stockHasSupplierProduct = App\Models\Goods\StockHasSupplierProduct::where('stock_id', $stock->id)
        ->where('supplier_product_id', $this->supplierProduct->id)
        ->first();

    if (!OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
        ->where('org_supplier_product_id', $this->orgSupplierProduct->id)->exists()) {
        App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreOrgStockHasOrgSupplierProduct::make()->action(
            stockHasSupplierProduct: $stockHasSupplierProduct,
            orgStock: $orgStock,
            orgSupplierProduct: $this->orgSupplierProduct,
            modelData: ['status' => true, 'local_priority' => 1],
            strict: false
        );
    }

    OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $this->orgSupplierProduct->id)->where('org_stock_id', '!=', $orgStock->id)->update(['status' => false]);

    expect(App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::linkedOrgStock($this->orgSupplierProduct)?->id)
        ->toBe($orgStock->id);

    $this->supplierProduct->update(['cost' => 10, 'currency_id' => $this->organisation->currency_id]);
    ShoppingListItem::where('organisation_id', $this->organisation->id)->where('agent_id', $this->orgAgent->agent_id)->forceDelete();
    ShoppingListItem::where('org_supplier_product_id', $this->orgSupplierProduct->id)->forceDelete();

    Cache::put("agent-order-capacity:{$this->orgAgent->id}", [
        ['lands_for_us_per_30d' => 5, 'source' => 'sales', 'samples' => 0],
        [
            'total_locations'       => 100,
            'empty_locations'       => 50,
            'free_ratio'            => 0.5,
            'inbound_open_po_lines' => 0,
            'agent_share_used'      => 0,
            'agent_share_limit'     => 10,
        ],
    ], now()->addMinutes(15));

    $orgStock->update(['quantity_available' => 10, 'health_rank' => null]);

    $firstItem = App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem::make()
        ->action($this->orgSupplierProduct, ['quantity_units' => 1]);

    expect(App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::run($this->orgAgent)['blocked']['at_capacity'])->toBeTrue();

    $overBudgetItem = App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem::make()
        ->action($this->orgSupplierProduct, ['quantity_units' => 1]);

    expect($overBudgetItem->agent_id)->toBe($this->orgAgent->agent_id);
    $overBudgetItem->forceDelete();

    $orgStock->update(['quantity_available' => 0]);

    $outOfStockItem = App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem::make()
        ->action($this->orgSupplierProduct, ['quantity_units' => 1]);

    expect($outOfStockItem->agent_id)->toBe($this->orgAgent->agent_id);
    $outOfStockItem->forceDelete();

    $orgStock->update(['quantity_available' => 10, 'health_rank' => HealthRankEnum::A]);

    $aRankItem = App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem::make()
        ->action($this->orgSupplierProduct, ['quantity_units' => 1]);

    expect($aRankItem->agent_id)->toBe($this->orgAgent->agent_id);
    $aRankItem->forceDelete();
    $firstItem->forceDelete();

    Cache::forget("agent-order-capacity:{$this->orgAgent->id}");
});

test('repair supplier drift repoints org supplier products to the right org supplier', function () {
    $supplierA = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierB = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplierA = $supplierA->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orgSupplierB = $supplierB->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplierA, [
        'code'             => 'DRIFT-1',
        'name'             => 'Drifted Asset',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    $drifted = OrgSupplierProduct::where('org_supplier_id', $orgSupplierA->id)
        ->where('supplier_product_id', $supplierProduct->id)
        ->firstOrFail();

    $supplierProduct->updateQuietly(['supplier_id' => $supplierB->id]);
    expect($drifted->org_supplier_id)->toBe($orgSupplierA->id);

    $dryRun = RepairOrgSupplierProductsSupplierDrift::make()->handle();
    expect($dryRun['repointed'])->toBeGreaterThan(0)
        ->and($drifted->refresh()->org_supplier_id)->toBe($orgSupplierA->id);

    RepairOrgSupplierProductsSupplierDrift::make()->handle(fix: true);

    expect($drifted->refresh()->org_supplier_id)->toBe($orgSupplierB->id);
});

test('repair supplier drift leaves rows whose correct twin already exists', function () {
    $supplierA = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierB = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplierA = $supplierA->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orgSupplierB = $supplierB->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplierB, [
        'code'             => 'DRIFT-2',
        'name'             => 'Drifted Asset 2',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    expect(OrgSupplierProduct::where('org_supplier_id', $orgSupplierB->id)
        ->where('supplier_product_id', $supplierProduct->id)
        ->exists())->toBeTrue();

    $baseline = RepairOrgSupplierProductsSupplierDrift::make()->handle();

    $drifted = OrgSupplierProduct::where('org_supplier_id', $orgSupplierB->id)
        ->where('supplier_product_id', $supplierProduct->id)
        ->first()
        ->replicate()
        ->fill(['org_supplier_id' => $orgSupplierA->id, 'slug' => 'drift-2-'.Str::random(6)]);
    $drifted->saveQuietly();

    $result = RepairOrgSupplierProductsSupplierDrift::make()->handle(fix: true);

    expect($result['collisions'])->toBe($baseline['collisions'] + 1)
        ->and(OrgSupplierProduct::find($drifted->id))->toBeNull()
        ->and(OrgSupplierProduct::where('organisation_id', $this->organisation->id)->where('supplier_product_id', $supplierProduct->id)->count())->toBe(1);
});

test('repair supplier drift moves open purchase order lines to the twin and discontinues a drifted row with history', function () {
    $supplierA = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierB = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplierA = $supplierA->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orgSupplierB = $supplierB->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplierB, [
        'code'             => 'DRIFT-3',
        'name'             => 'Drifted Asset 3',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    $twin = OrgSupplierProduct::where('org_supplier_id', $orgSupplierB->id)
        ->where('supplier_product_id', $supplierProduct->id)
        ->firstOrFail();

    $drifted = $twin->replicate()->fill(['org_supplier_id' => $orgSupplierA->id, 'slug' => 'drift-3-'.Str::random(6)]);
    $drifted->saveQuietly();

    $addLineOnDrifted = function (PurchaseOrderStateEnum $state) use ($orgSupplierB, $supplierProduct, $drifted): PurchaseOrderTransaction {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplierB, PurchaseOrder::factory()->definition());
        $line          = StorePurchaseOrderTransaction::make()->action(
            $purchaseOrder,
            $supplierProduct->historicSupplierProduct,
            $this->orgStocks[0],
            PurchaseOrderTransaction::factory()->definition()
        );
        $purchaseOrder->updateQuietly(['state' => $state]);
        $line->updateQuietly(['org_supplier_product_id' => $drifted->id]);

        return $line;
    };

    $settledLine   = $addLineOnDrifted(PurchaseOrderStateEnum::SETTLED);
    $submittedLine = $addLineOnDrifted(PurchaseOrderStateEnum::SUBMITTED);

    RepairOrgSupplierProductsSupplierDrift::make()->handle(fix: true);

    expect($submittedLine->refresh()->org_supplier_product_id)->toBe($twin->id)
        ->and($settledLine->refresh()->org_supplier_product_id)->toBe($drifted->id)
        ->and($drifted->refresh()->state)->toBe(OrgSupplierProductStateEnum::DISCONTINUED->value)
        ->and($twin->refresh()->state)->toBe(OrgSupplierProductStateEnum::ACTIVE->value);
});

test('repair supplier drift only treats a drifted row as a redundant copy when its org supplier keeps the product live through a same code copy', function () {
    $supplierA = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierB = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplierA = $supplierA->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $productData = [
        'code'             => 'DRIFT-COPY-'.Str::random(4),
        'name'             => 'Drifted Copy',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ];

    $copyUnderA = StoreSupplierProduct::make()->action($supplierA, $productData);
    $copyUnderB = StoreSupplierProduct::make()->action($supplierB, $productData);

    $twin = OrgSupplierProduct::where('org_supplier_id', $orgSupplierA->id)
        ->where('supplier_product_id', $copyUnderA->id)
        ->firstOrFail();

    $drifted = $twin->replicate()->fill([
        'supplier_product_id' => $copyUnderB->id,
        'slug'                => 'drift-copy-'.Str::random(6),
        'state'               => OrgSupplierProductStateEnum::ACTIVE->value,
    ]);
    $drifted->saveQuietly();

    $repair = RepairOrgSupplierProductsSupplierDrift::make();

    expect($repair->isRedundantCopy($drifted->refresh()))->toBeTrue();

    $twin->updateQuietly(['state' => OrgSupplierProductStateEnum::DISCONTINUED->value]);
    expect($repair->isRedundantCopy($drifted->refresh()))->toBeFalse();
    $twin->updateQuietly(['state' => OrgSupplierProductStateEnum::ACTIVE->value]);

    $copyUnderA->updateQuietly(['is_available' => false]);
    expect($repair->isRedundantCopy($drifted->refresh()))->toBeFalse();
    $copyUnderA->updateQuietly(['is_available' => true]);

    $stockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $copyUnderB->id], ['available' => true]);
    $link      = OrgStockHasOrgSupplierProduct::create([
        'org_stock_id'                  => $this->orgStocks[0]->id,
        'org_supplier_product_id'       => $drifted->id,
        'stock_has_supplier_product_id' => $stockLink->id,
        'status'                        => true,
        'local_priority'                => 0,
    ]);
    expect($repair->isRedundantCopy($drifted->refresh()))->toBeFalse();
    $link->delete();

    expect($repair->isRedundantCopy($drifted->refresh()))->toBeTrue();
});

test('merging duplicate supplier products keeps every organisation on the product and frees the duplicate code', function () {
    $duplicateSupplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $keptSupplier      = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());

    $productData = fn (string $code, int $unitsPerPack = 10) => [
        'code'             => $code,
        'name'             => 'Duplicated '.$code,
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => $unitsPerPack,
        'units_per_carton' => 100
    ];

    $suffix         = Str::random(4);
    $withTwin       = StoreSupplierProduct::make()->action($duplicateSupplier, $productData("DUP-A-$suffix"));
    $keptWithTwin   = StoreSupplierProduct::make()->action($keptSupplier, $productData("DUP-A-$suffix"));
    $withoutTwin    = StoreSupplierProduct::make()->action($duplicateSupplier, $productData("DUP-B-$suffix"));
    $keptNoTwin     = StoreSupplierProduct::make()->action($keptSupplier, $productData("DUP-B-$suffix"));
    $otherPack      = StoreSupplierProduct::make()->action($duplicateSupplier, $productData("DUP-C-$suffix"));
    StoreSupplierProduct::make()->action($keptSupplier, $productData("DUP-C-$suffix", 12));

    $duplicateRow = OrgSupplierProduct::where('supplier_product_id', $withTwin->id)->where('organisation_id', $this->organisation->id)->firstOrFail();
    $twinRow      = OrgSupplierProduct::where('supplier_product_id', $keptWithTwin->id)->where('organisation_id', $this->organisation->id)->firstOrFail();
    $twinRow->updateQuietly(['state' => OrgSupplierProductStateEnum::DISCONTINUED->value]);

    $loneRow = OrgSupplierProduct::where('supplier_product_id', $withoutTwin->id)->where('organisation_id', $this->organisation->id)->firstOrFail();
    OrgSupplierProduct::where('supplier_product_id', $keptNoTwin->id)->get()->each(function (OrgSupplierProduct $row) {
        $row->stats()->delete();
        $row->delete();
    });

    $linkTo = function (OrgSupplierProduct $row, SupplierProduct $supplierProduct, int $priority) {
        $stockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $supplierProduct->id], ['available' => true]);

        return OrgStockHasOrgSupplierProduct::create([
            'org_stock_id'                  => $this->orgStocks[0]->id,
            'org_supplier_product_id'       => $row->id,
            'stock_has_supplier_product_id' => $stockLink->id,
            'status'                        => true,
            'local_priority'                => $priority,
        ]);
    };
    foreach ([$keptWithTwin, $keptNoTwin] as $keptProduct) {
        StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $keptProduct->id], ['available' => true]);
    }
    $linkTo($duplicateRow, $withTwin, 100);
    $loneLink = $linkTo($loneRow, $withoutTwin, 0);

    $dryRun = MergeSupplierProducts::make()->mergeSupplier($duplicateSupplier, $keptSupplier);
    expect($dryRun['merged'])->toBe(2)
        ->and($dryRun['refused'])->toHaveKey("DUP-C-$suffix")
        ->and($withTwin->refresh()->code)->toBe("DUP-A-$suffix");

    $duplicateOrgSupplier = $duplicateSupplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $openOrder            = StorePurchaseOrder::make()->action($duplicateOrgSupplier, PurchaseOrder::factory()->definition());
    $openLine             = StorePurchaseOrderTransaction::make()->action($openOrder, $withTwin->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $openOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    MergeSupplierProducts::make()->mergeSupplier($duplicateSupplier, $keptSupplier, fix: true);

    expect($openLine->refresh()->supplier_product_id)->toBe($keptWithTwin->id)
        ->and($openLine->org_supplier_product_id)->toBe($twinRow->id);

    $twinLink = OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $twinRow->id)->where('org_stock_id', $this->orgStocks[0]->id)->firstOrFail();

    expect($twinRow->refresh()->state)->toBe(OrgSupplierProductStateEnum::ACTIVE->value)
        ->and($twinLink->local_priority)->toBe(100)
        ->and(StockHasSupplierProduct::find($twinLink->stock_has_supplier_product_id)->supplier_product_id)->toBe($keptWithTwin->id)
        ->and(OrgSupplierProduct::find($duplicateRow->id))->toBeNull()
        ->and($loneRow->refresh()->supplier_product_id)->toBe($keptNoTwin->id)
        ->and(StockHasSupplierProduct::find($loneLink->refresh()->stock_has_supplier_product_id)->supplier_product_id)->toBe($keptNoTwin->id)
        ->and($withTwin->refresh()->code)->toBe("DUP-A-$suffix-merged")
        ->and($withTwin->state)->toBe(SupplierProductStateEnum::DISCONTINUED)
        ->and($otherPack->refresh()->code)->toBe("DUP-C-$suffix")
        ->and(StockHasSupplierProduct::where('supplier_product_id', $withTwin->id)->exists())->toBeFalse();
});

test('merging a duplicate supplier that an organisation buys from alone moves that org supplier to the supplier kept', function () {
    $duplicate = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $kept      = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $kept->update(['currency_id' => $duplicate->currency_id]);

    $duplicateOrgSupplier = $duplicate->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $keptOrgSupplierId    = $kept->orgSuppliers()->where('organisation_id', $this->organisation->id)->value('id');
    DB::table('org_supplier_stats')->where('org_supplier_id', $keptOrgSupplierId)->delete();
    DB::table('org_suppliers')->where('id', $keptOrgSupplierId)->delete();

    App\Actions\SupplyChain\Supplier\MergeSuppliers::run($duplicate, $kept);

    expect($duplicateOrgSupplier->refresh()->supplier_id)->toBe($kept->id)
        ->and($duplicateOrgSupplier->status)->toBeTrue()
        ->and($duplicate->refresh()->status)->toBeFalse();
});

test('storing an org supplier product for a moved supplier product repoints the organisation row instead of adding a second one', function () {
    $supplierA = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierB = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplierA = $supplierA->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orgSupplierB = $supplierB->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplierA, [
        'code'             => 'DRIFT-4',
        'name'             => 'Drifted Asset 4',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    $original = OrgSupplierProduct::where('org_supplier_id', $orgSupplierA->id)
        ->where('supplier_product_id', $supplierProduct->id)
        ->firstOrFail();

    $supplierProduct->updateQuietly(['supplier_id' => $supplierB->id]);

    $stored = StoreOrgSupplierProduct::make()->action($orgSupplierB, $supplierProduct->refresh());

    expect($stored->id)->toBe($original->id)
        ->and($stored->org_supplier_id)->toBe($orgSupplierB->id)
        ->and(OrgSupplierProduct::where('organisation_id', $this->organisation->id)->where('supplier_product_id', $supplierProduct->id)->count())->toBe(1);
});

test('storing an org supplier product twice for the same org supplier returns the first row', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'ONCE-1',
        'name'             => 'Stored once',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    $first = OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)->where('supplier_product_id', $supplierProduct->id)->firstOrFail();
    expect($first->source_id)->toBeNull();

    $second = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct, ['source_id' => '1:999999']);

    expect($second->id)->toBe($first->id)
        ->and(OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)->where('supplier_product_id', $supplierProduct->id)->count())->toBe(1)
        ->and($first->refresh()->source_id)->toBe('1:999999')
        ->and($first->stats()->count())->toBe(1);
});

test('agent repairs products filed under AWChina: moves them to the real supplier, or retires them into the twin it already has', function () {
    [$awcOrgSupplier, $toMove] = createAgentOrgSupplierWithProduct($this);
    $awc = $awcOrgSupplier->supplier;
    $awc->update(['code' => 'AWC2']);

    [$realOrgSupplier, $twinOrgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $realSupplier = $realOrgSupplier->supplier;

    $duplicate = StoreOrgSupplierProduct::make()->action($awcOrgSupplier, StoreSupplierProduct::make()->action($awc, [
        'code'             => mb_strtolower($twinOrgSupplierProduct->supplierProduct->code),
        'name'             => 'Duplicate under AWChina',
        'cost'             => 100,
        'stock_id'         => $this->stocks[1]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]));
    $duplicateStock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $duplicateOrgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($duplicate->organisation, $duplicateStock, OrgStock::factory()->definition());
    $duplicateLink = App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock::make()->action($duplicateOrgStock, $duplicate);
    $twinOrgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::DISCONTINUED, 'is_available' => false]);
    $twinOrgSupplierProduct->supplierProduct->update(['state' => App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum::DISCONTINUED]);

    $toMoveOrgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $toMove->organisation,
        StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])),
        OrgStock::factory()->definition()
    );
    App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock::make()->action($toMoveOrgStock, $toMove);

    $awcDraft = StorePurchaseOrder::make()->action($awcOrgSupplier, [], strict: false);
    App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($awcDraft, $toMove, ['quantity_ordered' => 5]);
    App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($awcDraft, $duplicate, ['quantity_ordered' => 3]);

    $sentToAwc = StorePurchaseOrder::make()->action($awcOrgSupplier, [], strict: false);
    App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($sentToAwc, $toMove, ['quantity_ordered' => 1]);
    $sentToAwc->update(['state' => PurchaseOrderStateEnum::CONFIRMED]);

    $this->withoutVite()
        ->get(route('grp.org.procurement.org_suppliers.show.repair_supplier_products.index', [$awcOrgSupplier->organisation->slug, $awcOrgSupplier->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->firstWhere('id', $toMove->supplier_product_id)['sent_purchase_orders'][0]['reference'] === $sentToAwc->reference
                && collect($rows)->firstWhere('id', $toMove->supplier_product_id)['sent_purchase_orders'][0]['state'] === PurchaseOrderStateEnum::CONFIRMED->value
                && (float) collect($rows)->firstWhere('id', $toMove->supplier_product_id)['draft_purchase_orders'][0]['quantity'] === 5.0));

    DB::table('org_supplier_products')->where('id', $toMove->id)->update(['org_agent_id' => null]);

    $repair = App\Actions\SupplyChain\SupplierProduct\RepairSupplierProductsSupplier::make();

    expect(fn () => $repair->action($realSupplier, collect([$toMove->supplierProduct])))->toThrow(Illuminate\Validation\ValidationException::class, $toMove->supplierProduct->code);

    $sentToAwc->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    expect(fn () => $repair->action($realSupplier, collect([$twinOrgSupplierProduct->supplierProduct])))->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(fn () => $repair->action($awc, collect([$toMove->supplierProduct])))->toThrow(Illuminate\Validation\ValidationException::class);

    $repaired = $repair->action($realSupplier, Supplier::find($awc->id)->supplierProducts()->with('supplier', 'orgSupplierProducts.orgSupplier')->get());

    $realDraftLines = App\Models\Procurement\PurchaseOrderTransaction::whereHas('purchaseOrder', fn ($query) => $query
        ->where('state', PurchaseOrderStateEnum::IN_PROCESS)->where('parent_type', 'OrgSupplier')->where('parent_id', $realOrgSupplier->id))
        ->pluck('quantity_ordered', 'org_supplier_product_id');

    expect($awcDraft->purchaseOrderTransactions()->count())->toBe(0)
        ->and($sentToAwc->purchaseOrderTransactions()->count())->toBe(0)
        ->and((float) $realDraftLines[$toMove->id])->toBe(6.0)
        ->and((float) $realDraftLines[$twinOrgSupplierProduct->id])->toBe(3.0);

    $twinLink = OrgStockHasOrgSupplierProduct::where('org_stock_id', $duplicateLink->org_stock_id)->where('org_supplier_product_id', $twinOrgSupplierProduct->id)->first();

    expect($repaired)->toBe(['moved' => 1, 'merged' => 1])
        ->and($toMove->supplierProduct->refresh()->supplier_id)->toBe($realSupplier->id)
        ->and($toMove->refresh()->org_supplier_id)->toBe($realOrgSupplier->id)
        ->and($twinLink?->local_priority)->toBe(10)
        ->and($duplicateLink->refresh()->local_priority)->toBe(0)
        ->and($duplicate->refresh()->state)->toBe(OrgSupplierProductStateEnum::DISCONTINUED->value)
        ->and($duplicate->is_available)->toBeFalse()
        ->and($duplicate->supplierProduct->refresh()->state->value)->toBe('discontinued')
        ->and($twinOrgSupplierProduct->refresh()->state)->toBe(OrgSupplierProductStateEnum::ACTIVE->value)
        ->and($twinOrgSupplierProduct->is_available)->toBeTrue()
        ->and($twinOrgSupplierProduct->supplierProduct->refresh()->state->value)->toBe('active');

    $agentOrganisation = $this->agent->organisation;
    $awcInAgent        = OrgSupplier::where('supplier_id', $awc->id)->where('organisation_id', $agentOrganisation->id)->first()
        ?? StoreOrgSupplier::make()->action($agentOrganisation, $awc);
    $realInAgent       = OrgSupplier::where('supplier_id', $realSupplier->id)->where('organisation_id', $agentOrganisation->id)->first()
        ?? StoreOrgSupplier::make()->action($agentOrganisation, $realSupplier);

    $this->withoutVite()
        ->get(route('grp.org.agent.org_suppliers.show.repair_supplier_products.index', [$agentOrganisation->slug, $awcInAgent->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('Procurement/RepairSupplierProducts')
            ->where('data.meta.total', 0)
            ->where('active.state', 'active')
            ->where('suppliers', fn ($suppliers) => collect($suppliers)->pluck('id')->contains($realSupplier->id)));

    $this->get(route('grp.org.agent.org_suppliers.show.repair_supplier_products.index', [$agentOrganisation->slug, $realInAgent->slug]))->assertNotFound();
    $this->get(route('grp.org.procurement.org_suppliers.show.repair_supplier_products.index', [$awcOrgSupplier->organisation->slug, $awcOrgSupplier->slug]))
        ->assertInertia(fn ($page) => $page->component('Procurement/RepairSupplierProducts'));
});

test('a preferred supplier set on the master SKO becomes the preferred supplier of that SKO in every organisation', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock, OrgStock::factory()->definition());

    $links = collect([10, 0])->map(function (int $localPriority) use ($stock, $orgStock) {
        [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
        DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
        $stockLink = StockHasSupplierProduct::firstOrCreate(['stock_id' => $stock->id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id], ['available' => true]);
        $orgLink   = OrgStockHasOrgSupplierProduct::create([
            'org_stock_id'                  => $orgStock->id,
            'org_supplier_product_id'       => $orgSupplierProduct->id,
            'stock_has_supplier_product_id' => $stockLink->id,
            'status'                        => true,
            'local_priority'                => $localPriority,
        ]);

        return [$stockLink, $orgLink];
    });
    [[$wasPreferredStockLink, $wasPreferredOrgLink], [$backupStockLink, $backupOrgLink]] = $links->all();

    actingAs($this->adminGuest->getUser());
    $this->withoutVite()
        ->get(route('grp.goods.stocks.show', ['stock' => $stock->slug, 'tab' => StockTabsEnum::SUPPLIER_PRODUCTS->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/Stock')
            ->has('supplier_products.data', 2)
            ->where('supplier_products.data', fn ($rows) => collect($rows)->firstWhere('supplier_product_id', $wasPreferredStockLink->supplier_product_id)['preferred_in'] === $this->organisation->code
                && collect($rows)->every(fn ($row) => $row['is_preferred'] === false)));

    $this->patch(route('grp.models.stock.supplier_product.set_preferred', [$stock->id, $backupStockLink->supplier_product_id]))->assertRedirect();

    expect($backupStockLink->refresh()->priority)->toBe(10)
        ->and($wasPreferredStockLink->refresh()->priority)->toBe(0)
        ->and($backupOrgLink->refresh()->local_priority)->toBe(10)
        ->and($wasPreferredOrgLink->refresh()->local_priority)->toBe(0);

    $notLinked = SupplierProduct::whereNotIn('id', [$backupStockLink->supplier_product_id, $wasPreferredStockLink->supplier_product_id])->firstOrFail();
    expect(fn () => App\Actions\Goods\StockHasSupplierProduct\SetStockPreferredSupplierProduct::make()->action($stock, $notLinked))->toThrow(ValidationException::class);
});

test('the agent Ordered page prints every ordered purchase order as one, grouped by supplier (HELP-4342)', function () {
    $stages        = App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::class;
    $combined      = App\Actions\Procurement\OrgAgent\PdfAgentOrderedPurchaseOrders::class;
    $organisation  = $this->orgAgent->organisation;
    $orgStock      = OrgStock::where('organisation_id', $organisation->id)->firstOrFail();
    $suffix        = Str::upper(Str::random(6));

    $purchaseOrders = collect(['ZZB', 'ZZA'])->map(function (string $prefix) use ($orgStock, $suffix) {
        $supplier      = StoreSupplier::make()->action($this->agent, [...Supplier::factory()->definition(), 'code' => $prefix.$suffix]);
        $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $this->orgAgent->id)->firstOrFail();
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
        DB::table('purchase_order_transactions')->insert([
            'group_id'          => $purchaseOrder->group_id,
            'organisation_id'   => $purchaseOrder->organisation_id,
            'purchase_order_id' => $purchaseOrder->id,
            'org_stock_id'      => $orgStock->id,
            'state'             => PurchaseOrderTransactionStateEnum::SUBMITTED->value,
            'delivery_state'    => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
            'quantity_ordered'  => 10,
            'net_amount'        => 25,
            'data'              => '{}',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);

        return $purchaseOrder;
    });

    $ours = $combined::make()->sections($this->orgAgent)->filter(fn (array $section) => $purchaseOrders->contains('id', $section['purchaseOrder']->id))->values();

    expect($ours->pluck('supplier.code')->all())->toBe(['ZZA'.$suffix, 'ZZB'.$suffix])
        ->and($ours->every(fn (array $section) => $section['lines']->count() === 1))->toBeTrue();

    actingAs($this->adminGuest->getUser());
    $this->withoutVite()
        ->get(route('grp.org.procurement.org_agents.show.shopping.stage', [$organisation->slug, $this->orgAgent->slug, $stages::ORDERED]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions.0.route.name', 'grp.org.procurement.org_agents.show.shopping.ordered.pdf'));

    $this->get(route('grp.org.procurement.org_agents.show.shopping.ordered.pdf', [$organisation->slug, $this->orgAgent->slug, $combined::make()->filename($this->orgAgent)]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $purchaseOrders->each(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->update(['state' => PurchaseOrderStateEnum::CANCELLED]));
});
