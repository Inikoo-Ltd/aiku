<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\Hydrators\StockHydrateStateFromOrgStocks;
use App\Actions\Goods\Stock\RepairStocksStateFromOrgStocks;
use App\Actions\Goods\Stock\StoreStock;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\OrgStockDiscontinueTool;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Config;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Inventory\OrgStock\ApplyScheduledOrgStockStateChanges;
use App\Actions\Inventory\OrgStock\DiscontinueGroupOrgStocks;
use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Actions\Inventory\OrgStock\GetOrgStockDiscontinuePreview;
use App\Actions\Inventory\OrgStock\SuggestOrgStockDiscontinue;
use App\Models\Inventory\OrgStockDiscontinueSuggestion;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\Organisation;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Mockery;
use RuntimeException;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/InventoryHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        setPermissionsTeamId($this->group->id);
        $this->guest = createAdminGuest($this->group);

        $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

        Config::set("inertia.pages.paths", [resource_path("js/Pages/Grp")]);
        $this->user = $this->guest->getUser();
        actingAs($this->user);
    }
);

describe('discontinue preview', function () {
    beforeEach(function () {
        ensureFirstWarehouseHasCountry($this->organisation);
        list(, , $this->shop) = createShop();
        $this->customer = createCustomer($this->shop);
        list($this->orgStocks, $this->product) = createProduct($this->shop);
    });

    test('preview counts the open purchase order and the portfolio carrying the stock', function () {
        $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
        $this->product->orgStocks()->syncWithoutDetaching([$orgStock->id => ['quantity' => 1]]);

        $supplier      = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
        StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $orgStock->stock_id]));
        $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
            ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
        StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $orgStock, PurchaseOrderTransaction::factory()->definition());

        $platform = $this->group->platforms()->where('type', PlatformTypeEnum::MANUAL)->firstOrFail();
        $channel  = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'discontinue-preview']);
        StorePortfolio::make()->action($channel, $this->product, []);

        $preview = GetOrgStockDiscontinuePreview::make()->action($this->organisation, [$orgStock->id]);

        expect($preview)->toHaveCount(1)
            ->and($preview[0]['code'])->toBe($orgStock->code)
            ->and($preview[0]['purchase_orders']['count'])->toBe(1)
            ->and($preview[0]['purchase_orders']['references'])->toBe([(string) $purchaseOrder->reference])
            ->and($preview[0]['portfolios']['count'])->toBe(1)
            ->and($preview[0]['portfolios']['customers'])->toBe(1)
            ->and($preview[0]['portfolios']['by_platform'])->toBe([PlatformTypeEnum::MANUAL->value => 1])
            ->and($preview[0]['mailshots']['known'])->toBeFalse()
            ->and($preview[0]['updated_at'])->not->toBeNull()
            ->and($preview[0]['organisations'])->toHaveKey($this->organisation->code, $orgStock->state->value);
    });

    test('preview of a stock with nothing hanging off it is all zeros', function () {
        $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

        $preview = GetOrgStockDiscontinuePreview::make()->action($this->organisation, [$orgStock->id]);

        expect($preview[0]['purchase_orders']['count'])->toBe(0)
            ->and($preview[0]['stock_deliveries']['count'])->toBe(0)
            ->and($preview[0]['portfolios']['count'])->toBe(0)
            ->and($preview[0]['external_shops'])->toBe([])
            ->and($preview[0]['webpages']['count'])->toBe(0)
            ->and($preview[0]['orders']['count'])->toBe(0)
            ->and($preview[0]['is_exclusive'])->toBeFalse();
    });

    test('preview lists the product web page with its state and public link', function () {
        if (!$this->shop->website) {
            createWebsite($this->shop);
            $this->shop->refresh();
        }
        $this->product->refresh();
        $webpage = $this->product->webpage ?? StoreProductWebpage::make()->action($this->product);
        $orgStock = $this->product->orgStocks()->first();

        $pages = collect(GetOrgStockDiscontinuePreview::make()->action($this->organisation, [$orgStock->id])[0]['webpages']['pages']);
        $page  = $pages->firstWhere('url', $webpage->url);

        expect($page)->not->toBeNull()
            ->and($page['state'])->toBe($webpage->state->value)
            ->and($page['canonical_url'])->toBe($webpage->getCanonicalUrl());
    });

    test('preview route answers for the warehouse', function () {
        $warehouse = $this->organisation->warehouses()->first() ?? createWarehouse();

        $this->getJson(route('grp.org.warehouses.show.inventory.org_stocks.discontinue_preview', [
            $this->organisation->slug,
            $warehouse->slug,
            'org_stock_ids' => [$this->orgStocks[1]->id],
        ]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->orgStocks[1]->id);
    });

    test('a warehouse stock controller cannot discontinue, only supply chain can', function () {
        $warehouse = $this->organisation->warehouses()->first() ?? createWarehouse();
        $user      = $this->guest->getUser();

        setPermissionsTeamId($user->group_id);
        $originalRoles = $user->roles->pluck('name')->toArray();
        $reset = function (array $roles) use ($user) {
            setPermissionsTeamId($user->group_id);
            $user->syncRoles($roles);
            Cache::tags('auth-user:'.$user->id)->flush();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            actingAs($user->refresh());
        };

        $reset([RolesEnum::getRoleName('stock-controller', $warehouse), RolesEnum::getRoleName('warehouse-admin', $warehouse)]);
        $previewUrl = route('grp.org.warehouses.show.inventory.org_stocks.discontinue_preview', [$this->organisation->slug, $warehouse->slug, 'org_stock_ids' => [$this->orgStocks[1]->id]]);
        $this->getJson($previewUrl)->assertForbidden();
        $this->post(route('grp.org.warehouses.show.inventory.org_stocks.discontinue', [$this->organisation->slug, $warehouse->slug]), [
            'org_stock_ids' => [$this->orgStocks[1]->id], 'state' => 'discontinued', 'reason' => 'no',
        ])->assertForbidden();
        $page = $this->get(route('grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show', [$this->organisation->slug, $warehouse->slug, $this->orgStocks[1]->slug]))
            ->assertOk()->viewData('page')['props'];
        expect($page['discontinue_route'])->toBeNull()
            ->and(collect($page['pageHead']['actions'])->pluck('key'))->not->toContain('discontinue');

        $reset([RolesEnum::getRoleName('supply-chain', $this->group), RolesEnum::getRoleName('warehouse-viewer', $warehouse)]);
        expect($user->authTo(GroupPermissionsEnum::SUPPLY_CHAIN->value))->toBeTrue();
        $this->getJson($previewUrl)->assertOk();
        $page = $this->get(route('grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show', [$this->organisation->slug, $warehouse->slug, $this->orgStocks[1]->slug]))
            ->assertOk()->viewData('page')['props'];
        expect($page['discontinue_route'])->not->toBeNull();

        $reset($originalRoles);
    });
});

describe('discontinue confirm', function () {
    beforeEach(function () {
        $stocks = array_map(
            fn () => StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])),
            range(1, 3)
        );
        $this->orgStocks = createOrgStocks($this->organisation, $stocks);

        $otherOrganisation = Organisation::where('code', 'other')->first();
        if (!$otherOrganisation) {
            $otherOrganisation = StoreOrganisation::make()->action(
                $this->group,
                array_merge(Organisation::factory()->definition(), ['code' => 'other', 'type' => OrganisationTypeEnum::SHOP])
            );
        }
        $this->otherOrganisation = $otherOrganisation;
        $this->otherOrgStocks    = createOrgStocks($otherOrganisation, $stocks);

        ensureFirstWarehouseHasCountry($this->organisation);
    });

    test('confirm discontinues the stock group wide, keeps an excepted organisation and audits the reason', function () {
        $orgStock      = $this->orgStocks[0];
        $otherOrgStock = $this->otherOrgStocks[0];

        $stats = DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids'       => [$orgStock->id],
            'state'               => OrgStockStateEnum::DISCONTINUED->value,
            'organisation_states' => ['other' => OrgStockStateEnum::ACTIVE->value],
            'reason'              => 'Supplier stopped making it',
            'source'              => 'mcp',
            'request_text'        => 'discontinue this one please',
        ]);

        $audit = Audit::where('auditable_type', 'OrgStock')->where('auditable_id', $orgStock->id)->where('event', 'state_change')->latest('id')->first();

        $siblings = OrgStock::where('stock_id', $orgStock->stock_id)->count();

        expect($stats['changed'])->toBe(1)
            ->and($stats['unchanged'])->toBe($siblings - 1)
            ->and($orgStock->refresh()->state)->toBe(OrgStockStateEnum::DISCONTINUED)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE)
            ->and($audit)->not->toBeNull()
            ->and($audit->old_values['state'])->toBe('active')
            ->and($audit->new_values['to_state'])->toBe('discontinued')
            ->and($audit->new_values['reason'])->toBe('Supplier stopped making it')
            ->and($audit->new_values['source'])->toBe('mcp')
            ->and($audit->new_values['request_text'])->toBe('discontinue this one please')
            ->and($audit->new_values['overrides'])->toBe(['other' => 'active']);
    });

    test('changing every sko of a stock carries the state up to the stock', function () {
        $orgStock = $this->orgStocks[2];
        $stock    = $orgStock->stock;
        $change   = fn (OrgStockStateEnum $state) => DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => $state->value,
            'scope'         => 'group',
            'reason'        => 'Stock follows its skos',
        ]);

        $change(OrgStockStateEnum::DISCONTINUED);
        expect($stock->refresh()->state)->toBe(StockStateEnum::DISCONTINUED);

        $this->otherOrgStocks[2]->update(['state' => OrgStockStateEnum::DISCONTINUING]);
        expect(StockHydrateStateFromOrgStocks::make()->getStockStateFromOrgStocks($stock->refresh()))->toBe(StockStateEnum::DISCONTINUING);

        $change(OrgStockStateEnum::ACTIVE);
        expect($stock->refresh()->state)->toBe(StockStateEnum::ACTIVE);
    });

    test('state repair derives every stock from its skos, whatever state it was left in', function () {
        [$retired, $stillSold, $sellingOff] = array_map(fn (OrgStock $orgStock) => $orgStock->stock, $this->orgStocks);

        OrgStock::where('stock_id', $retired->id)->update(['state' => OrgStockStateEnum::DISCONTINUED]);
        OrgStock::where('stock_id', $sellingOff->id)->update(['state' => OrgStockStateEnum::DISCONTINUING]);
        $this->otherOrgStocks[2]->update(['state' => OrgStockStateEnum::DISCONTINUED]);
        $retired->update(['state' => StockStateEnum::ACTIVE]);
        $stillSold->update(['state' => StockStateEnum::DISCONTINUED]);
        $sellingOff->update(['state' => StockStateEnum::DISCONTINUED]);

        expect(RepairStocksStateFromOrgStocks::run($retired->refresh(), false))->toBe([StockStateEnum::ACTIVE, StockStateEnum::DISCONTINUED])
            ->and($retired->refresh()->state)->toBe(StockStateEnum::ACTIVE)
            ->and(RepairStocksStateFromOrgStocks::run($retired, true))->toBe([StockStateEnum::ACTIVE, StockStateEnum::DISCONTINUED])
            ->and($retired->refresh()->state)->toBe(StockStateEnum::DISCONTINUED)
            ->and(RepairStocksStateFromOrgStocks::run($stillSold->refresh(), true))->toBe([StockStateEnum::DISCONTINUED, StockStateEnum::ACTIVE])
            ->and(RepairStocksStateFromOrgStocks::run($sellingOff->refresh(), true))->toBe([StockStateEnum::DISCONTINUED, StockStateEnum::DISCONTINUING])
            ->and(RepairStocksStateFromOrgStocks::run($sellingOff->refresh(), true))->toBeNull();
    });

    test('a stock without skos keeps its state', function () {
        $stock = StoreStock::make()->action($this->group, Stock::factory()->definition());
        $stock->update(['state' => StockStateEnum::DISCONTINUED]);

        expect(StockHydrateStateFromOrgStocks::make()->getStockStateFromOrgStocks($stock->refresh()))->toBe(StockStateEnum::DISCONTINUED);
    });

    test('discontinue suggestions pile up per stock across the group and show on product command control', function () {
        $orgStock      = $this->orgStocks[0];
        $otherOrgStock = $this->otherOrgStocks[0];
        $stock         = $orgStock->stock;
        $url           = route('grp.models.org_stock.discontinue_suggestion.store', [$orgStock->id]);
        $state         = $orgStock->state;

        $this->from(route('grp.goods.dashboard'))->post($url, ['reasons' => [], 'note' => ''])->assertSessionHasErrors(['source', 'reasons', 'note']);
        $this->from(route('grp.goods.dashboard'))->post($url, ['source' => 'buying', 'reasons' => ['not_a_reason'], 'note' => 'Customers keep sending it back'])->assertSessionHasErrors(['reasons.0']);
        expect(OrgStockDiscontinueSuggestion::where('stock_id', $stock->id)->count())->toBe(0);

        $this->post($url, ['source' => 'buying', 'reasons' => ['high_complaints', 'slow_seller'], 'note' => 'Customers keep sending it back'])->assertRedirect()->assertSessionHasNoErrors();
        SuggestOrgStockDiscontinue::make()->action($otherOrgStock, auth()->user(), ['source' => 'goods_in', 'reasons' => ['slow_seller', 'arrived_broken'], 'note' => 'Half the carton arrived in pieces']);

        expect(OrgStockDiscontinueSuggestion::tallyForOrgStocks([$orgStock->id, $otherOrgStock->id])->all())->toBe([
            $orgStock->id      => ['times' => 2, 'people' => 1],
            $otherOrgStock->id => ['times' => 2, 'people' => 1],
        ])->and($orgStock->refresh()->state)->toBe($state);

        $props = [];
        $this->get(route('grp.goods.dashboard', ['condition' => 'suggested', 'sort' => '-suggestions']))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $page->loadDeferredProps('dashboard', function (AssertableInertia $reload) use (&$props) {
                    $props = $reload->toArray()['props'];
                });
            });

        $row = collect($props['rows']['data'])->firstWhere('id', $stock->id);

        expect(collect($props['rows']['data'])->every(fn (array $row) => $row['suggestions'] > 0))->toBeTrue()
            ->and($props['kpis']['suggested'])->toBeGreaterThanOrEqual(1)
            ->and($row['suggestions'])->toBe(2)
            ->and($row['suggestion_people'])->toBe(1)
            ->and($row['suggestion_evidence']['reasons'][0])->toBe(['label' => 'Slow seller', 'times' => 2])
            ->and(array_column($row['suggestion_evidence']['sources'], 'times', 'label'))->toBe(['Goods in' => 1, 'Buying' => 1])
            ->and(array_column($row['suggestion_evidence']['notes'], 'note'))->toBe(['Half the carton arrived in pieces', 'Customers keep sending it back'])
            ->and($row)->toHaveKeys(['complaints', 'complaint_customers', 'refunds', 'replacements', 'next_order', 'supplier_claims', 'orders', 'complaint_rate']);

        expect($row['complaint_customers'])->toBe(0)
            ->and($row['feedback_level'])->toBeNull()
            ->and($props['kpis']['feedback_levels'])->toHaveKeys(['mild', 'bad', 'severe', 'disaster']);

        $this->get(route('grp.goods.dashboard', ['feedback' => 'mild', 'sort' => '-feedback', 'search' => $stock->code]))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps('dashboard', fn (AssertableInertia $reload) => $reload->where('rows.total', 0)));
    });

    test('group routes preview and change org stocks picked across organisations in one request', function () {
        $orgStock      = $this->orgStocks[1];
        $otherOrgStock = $this->otherOrgStocks[2];
        $orgStockIds   = [$orgStock->id, $otherOrgStock->id];

        $previews = $this->getJson(route('grp.goods.org_stocks.discontinue_preview', ['org_stock_ids' => $orgStockIds]))
            ->assertOk()
            ->assertJsonCount(2)
            ->json();

        expect(collect($previews)->pluck('id')->sort()->values()->all())->toBe(collect($orgStockIds)->sort()->values()->all());

        $this->post(route('grp.goods.org_stocks.discontinue'), [
            'org_stock_ids'       => $orgStockIds,
            'state'               => OrgStockStateEnum::SUSPENDED->value,
            'scope'               => 'group',
            'reason'              => 'Bulk hold from the dashboard',
            'expected_updated_at' => collect($previews)->pluck('updated_at', 'id')->all(),
            'source'              => 'ui',
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::SUSPENDED)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::SUSPENDED);
    });

    test('group change is all or nothing when one organisation refuses', function () {
        $orgStock      = $this->orgStocks[1];
        $otherOrgStock = $this->otherOrgStocks[2];

        expect(fn () => DiscontinueGroupOrgStocks::make()->action($this->group, [
            'org_stock_ids'       => [$orgStock->id, $otherOrgStock->id],
            'state'               => OrgStockStateEnum::DISCONTINUED->value,
            'scope'               => 'organisation',
            'reason'              => 'Stale preview in the second organisation',
            'expected_updated_at' => [$otherOrgStock->id => now()->subYear()->toIso8601String()],
        ]))->toThrow(ValidationException::class);

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);
    });

    test('a discontinued sko is refused on a purchase order line', function () {
        $orgStock = $this->orgStocks[0];
        DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => OrgStockStateEnum::DISCONTINUING->value,
            'reason'        => 'Running down',
        ]);

        $supplier = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
        StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $orgStock->stock_id]));
        $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
            ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

        expect(fn () => StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $orgStock->refresh(), PurchaseOrderTransaction::factory()->definition()))
            ->toThrow(ValidationException::class);

        DiscontinueOrgStocks::make()->action($this->organisation, ['org_stock_ids' => [$orgStock->id], 'state' => OrgStockStateEnum::ACTIVE->value]);

        expect(StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $orgStock->refresh(), PurchaseOrderTransaction::factory()->definition()))
            ->toBeInstanceOf(PurchaseOrderTransaction::class);
    });

    test('discontinuing a sko removes its open warehouse restock requests and nothing else', function () {
        $orgStock = $this->orgStocks[1];
        $line     = fn (array $attributes) => \App\Models\Procurement\PartnerShoppingListItem::create(array_merge([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'stock_id'        => $orgStock->stock_id,
            'org_stock_id'    => $orgStock->id,
            'quantity'        => 1,
        ], $attributes));

        $restock        = $line([]);
        $otherRestock   = $line(['organisation_id' => $this->otherOrganisation->id, 'org_stock_id' => $this->otherOrgStocks[1]->id]);
        $partnerRequest = $line(['organisation_id' => $this->otherOrganisation->id, 'partner_organisation_id' => $this->organisation->id]);

        expect(GetOrgStockDiscontinuePreview::make()->action($this->organisation, [$orgStock->id])[0]['restock_requests'])->toBe(1);

        DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids'       => [$orgStock->id],
            'state'               => OrgStockStateEnum::DISCONTINUING->value,
            'organisation_states' => ['other' => OrgStockStateEnum::ACTIVE->value],
            'reason'              => 'Running down',
        ]);

        expect($restock->refresh()->trashed())->toBeTrue()
            ->and($otherRestock->refresh()->trashed())->toBeFalse()
            ->and($partnerRequest->refresh()->trashed())->toBeFalse();

        DiscontinueOrgStocks::make()->action($this->organisation, ['org_stock_ids' => [$orgStock->id], 'state' => OrgStockStateEnum::ACTIVE->value]);
    });

    test('confirm refuses a sko that moved since the preview', function () {
        $orgStock = $this->orgStocks[1];

        expect(fn () => DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids'       => [$orgStock->id],
            'state'               => OrgStockStateEnum::DISCONTINUED->value,
            'reason'              => 'x',
            'expected_updated_at' => [$orgStock->id => now()->subDay()->toIso8601String()],
        ]))->toThrow(ValidationException::class)
            ->and($orgStock->refresh()->state)->not->toBe(OrgStockStateEnum::DISCONTINUED);
    });

    test('a future effective date is stored and applied by the sweep when the day comes', function () {
        $orgStock      = $this->orgStocks[2];
        $otherOrgStock = $this->otherOrgStocks[2];

        $stats = DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids'       => [$orgStock->id],
            'state'               => OrgStockStateEnum::DISCONTINUED->value,
            'organisation_states' => ['other' => OrgStockStateEnum::SUSPENDED->value],
            'reason'              => 'Last batch sells until October',
            'effective_at'        => now()->addDays(10)->toDateString(),
        ]);

        expect($stats['scheduled'])->toBe(2)
            ->and($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE)
            ->and($orgStock->data[DiscontinueOrgStocks::SCHEDULED_KEY]['to_state'])->toBe('discontinued')
            ->and(ApplyScheduledOrgStockStateChanges::run()['waiting'])->toBe(2)
            ->and($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);

        foreach ([$orgStock, $otherOrgStock] as $scheduled) {
            $scheduled->refresh();
            $scheduled->update(['data' => array_merge($scheduled->data, [DiscontinueOrgStocks::SCHEDULED_KEY => array_merge($scheduled->data[DiscontinueOrgStocks::SCHEDULED_KEY], ['effective_at' => now()->subMinute()->toIso8601String()])])]);
        }

        expect(ApplyScheduledOrgStockStateChanges::run()['applied'])->toBe(2)
            ->and($orgStock->refresh()->state)->toBe(OrgStockStateEnum::DISCONTINUED)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::SUSPENDED)
            ->and($orgStock->data)->not->toHaveKey(DiscontinueOrgStocks::SCHEDULED_KEY);
    });

    test('product command control shows the stock in every organisation and a status set shows on the next load', function () {
        $orgStock = $this->orgStocks[1];
        $stock    = $orgStock->stock;
        $row      = function () use ($stock) {
            $rows = [];
            $this->get(route('grp.goods.dashboard', ['search' => $stock->code]))->assertOk()
                ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                    $page->loadDeferredProps('dashboard', function (AssertableInertia $reload) use (&$rows) {
                        $rows = $reload->toArray()['props']['rows']['data'];
                    });
                });

            return collect($rows)->firstWhere('id', $stock->id);
        };

        $before = $row();
        expect($before['state'])->toBe('active')
            ->and($before['organisations'])->toHaveKeys([$this->organisation->code, 'other'])
            ->and($before['organisations']['other']['condition'])->not->toBe('sell');

        DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => OrgStockStateEnum::DISCONTINUING->value,
            'reason'        => 'Running down',
        ]);

        $after = $row();
        expect($after['state'])->toBe('discontinuing')
            ->and($after['organisations']['other']['condition'])->toBe('sell');
    });
});

describe('discontinue authorisation', function () {
    beforeEach(function () {
        $stocks = array_map(
            fn () => StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])),
            range(1, 2)
        );
        $this->authOrgStocks = createOrgStocks($this->organisation, $stocks);

        $otherOrganisation = Organisation::where('code', 'other')->first();
        if (!$otherOrganisation) {
            $otherOrganisation = StoreOrganisation::make()->action(
                $this->group,
                array_merge(Organisation::factory()->definition(), ['code' => 'other', 'type' => OrganisationTypeEnum::SHOP])
            );
        }
        $this->authOtherOrganisation = $otherOrganisation;
        $this->authOtherOrgStocks    = createOrgStocks($otherOrganisation, $stocks);

        ensureFirstWarehouseHasCountry($this->organisation);

        $this->authUser          = $this->guest->getUser();
        setPermissionsTeamId($this->authUser->group_id);
        $this->authOriginalRoles = $this->authUser->roles->pluck('name')->toArray();
    });

    afterEach(function () {
        setPermissionsTeamId($this->authUser->group_id);
        $this->authUser->syncRoles($this->authOriginalRoles);
        $this->authUser->update(['can_use_mcp_discontinue' => false]);
        Cache::tags('auth-user:'.$this->authUser->id)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    test('a user with the group supply-chain permission can change status group wide', function () {
        $orgStock      = $this->authOrgStocks[0];
        $otherOrgStock = $this->authOtherOrgStocks[0];

        $stats = DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => OrgStockStateEnum::DISCONTINUING->value,
            'reason'        => 'group wide change',
        ], $this->authUser);

        expect($stats['changed'])->toBeGreaterThanOrEqual(1)
            ->and($orgStock->refresh()->state)->toBe(OrgStockStateEnum::DISCONTINUING)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::DISCONTINUING);
    });

    test('buyers and supply chain workers are refused, only supply chain managers discontinue', function () {
        $orgStock = $this->authOrgStocks[0];

        foreach ([
            RolesEnum::getRoleName(RolesEnum::PROCUREMENT_CLERK->value, $this->organisation),
            RolesEnum::getRoleName(RolesEnum::SUPPLY_CHAIN_WORKER->value, $this->group),
        ] as $role) {
            setPermissionsTeamId($this->authUser->group_id);
            $this->authUser->syncRoles([$role]);
            Cache::tags('auth-user:'.$this->authUser->id)->flush();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            expect(fn () => DiscontinueOrgStocks::make()->action($this->organisation, [
                'org_stock_ids' => [$orgStock->id],
                'state'         => OrgStockStateEnum::DISCONTINUING->value,
                'scope'         => 'organisation',
                'reason'        => 'not a manager',
            ], $this->authUser))->toThrow(ValidationException::class);
        }

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);
    });

    test('a user with neither permission is refused by the action and the controller', function () {
        setPermissionsTeamId($this->authUser->group_id);
        $this->authUser->syncRoles([]);
        Cache::tags('auth-user:'.$this->authUser->id)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $orgStock  = $this->authOrgStocks[0];
        $warehouse = $this->organisation->warehouses()->first() ?? createWarehouse();

        expect(fn () => DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => OrgStockStateEnum::DISCONTINUING->value,
            'scope'         => 'organisation',
            'reason'        => 'no permission',
        ], $this->authUser))->toThrow(ValidationException::class);

        actingAs($this->authUser->refresh());
        $this->post(route('grp.org.warehouses.show.inventory.org_stocks.discontinue', [$this->organisation->slug, $warehouse->slug]), [
            'org_stock_ids' => [$orgStock->id], 'state' => 'discontinuing', 'reason' => 'no',
        ])->assertForbidden();

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);
    });

    test('the mcp discontinue tool applies the central permission check on top of mcp enrolment', function () {
        setPermissionsTeamId($this->authUser->group_id);
        $this->authUser->syncRoles([]);
        Cache::tags('auth-user:'.$this->authUser->id)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->authUser->update(['can_use_mcp_discontinue' => true]);

        $orgStock = $this->authOrgStocks[0];

        AikuServer::actingAs($this->authUser)->tool(OrgStockDiscontinueTool::class, [
            'organisation' => $this->organisation->code,
            'codes'        => [$orgStock->code],
            'state'        => 'discontinuing',
            'reason'       => 'no permission via mcp',
            'request_text' => 'please discontinue',
        ])->assertHasErrors(['Discontinuing SKOs needs the Supply Chain Manager permission']);

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);
    });

    test('a group-wide change is atomic: a failure partway through rolls back every organisation', function () {
        $orgStock      = $this->authOrgStocks[0];
        $otherOrgStock = $this->authOtherOrgStocks[0];

        $mock = Mockery::mock(UpdateOrgStock::class);
        $mock->shouldReceive('action')->andReturnUsing(function (OrgStock $target, array $data) {
            if ($target->organisation->code === 'other') {
                throw new RuntimeException('boom mid loop');
            }
            $target->update(['state' => $data['state']]);

            return $target;
        });
        app()->instance(UpdateOrgStock::class, $mock);

        expect(fn () => DiscontinueOrgStocks::make()->action($this->organisation, [
            'org_stock_ids' => [$orgStock->id],
            'state'         => OrgStockStateEnum::DISCONTINUING->value,
            'reason'        => 'atomic test',
        ]))->toThrow(RuntimeException::class);

        expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE)
            ->and($otherOrgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);
    });
});
