<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Dispatching\CrossDispatch\UpdateCrossDispatchSettings;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgPartner\PreparePartnerShoppingListOrder;
use App\Actions\Procurement\OrgPartner\StoreRescuePurchaseOrder;
use App\Actions\Procurement\OrgPartner\UI\GetOrgPartnerShowcase;
use App\Models\CRM\Customer;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\StockDelivery;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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


test('UI Index org partners', function () {
    $response = $this->get(route('grp.org.procurement.org_partners.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Procurement/Partners')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->where('currency_code', $this->organisation->currency->code)
            ->where('can_create_purchase_orders', true)
            ->has('partners.0', fn (AssertableInertia $card) => $card
                ->where('id', $this->orgPartner->id)
                ->where('name', $this->orgPartner->partner->name)
                ->where('is_hub', $this->orgPartner->partner->is_manufacturing_hub)
                ->has('stats', fn (AssertableInertia $stats) => $this->orgPartner->partner->is_manufacturing_hub
                    ? $stats->has('open_shopping_list_items')->where('rescuable', null)->etc()
                    : $stats->has('purchase_orders')->has('last_submitted_at')->has('current')->where('rescuable', null)->etc())
                ->etc())
            ->missing('rescuable')
            ->loadDeferredProps(fn (AssertableInertia $page) => $page->has(
                'rescuable.'.$this->orgPartner->id,
                fn (AssertableInertia $rescuable) => $this->orgPartner->partner->is_manufacturing_hub
                    ? $rescuable->has('buckets', 3)->has('top')->etc()
                    : $rescuable->has('buckets', 3)->missing('buckets.0.left_out')->has('top')->etc()
            ));
    });

    expect($this->getJson(route('grp.json.org_partner.rescue_left_out', [$this->orgPartner->id]))->assertOk()->json())
        ->toEqual(json_decode(json_encode(GetPartnerStockCoverBuckets::make()->rescueLeftOut($this->orgPartner)), true));

    $user          = $this->adminGuest->getUser();
    $originalRoles = $user->roles->pluck('name')->toArray();
    actingAsUserWithRoles($user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName('accounting-clerk', $this->organisation)]);
    $this->get(route('grp.org.procurement.org_partners.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_create_purchase_orders', false)->etc());
    $this->post(route('grp.models.org-partner.purchase-order.store', [$this->orgPartner->id]))->assertForbidden();
    actingAsUserWithRoles($user, []);
    $this->getJson(route('grp.json.org_partner.rescue_left_out', [$this->orgPartner->id]))->assertForbidden();
    actingAsUserWithRoles($user, $originalRoles);
});

test('UI partner rescue items for a sister company', function () {
    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    $partner->update(['is_manufacturing_hub' => false]);

    try {
        $response = $this->get(route('grp.org.procurement.org_partners.show.rescue.index', [$this->organisation->slug, $this->orgPartner->id]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Procurement/PartnerRescueItems')
                ->has('title')
                ->has('items.data')
                ->where('orgPartner.id', $this->orgPartner->id)
                ->where('currency_code', $this->organisation->currency->code);
        });
    } finally {
        $partner->update(['is_manufacturing_hub' => $wasHub]);
    }
});

test('new purchase order to a sister company opens it, or the one already being prepared', function () {
    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    $partner->update(['is_manufacturing_hub' => false]);

    try {
        $response = $this->post(route('grp.models.org-partner.purchase-order.store', ['orgPartner' => $this->orgPartner->id]));

        $purchaseOrder = $this->orgPartner->purchaseOrders()->latest('id')->first();

        $purchaseOrderUrl = route('grp.org.procurement.org_partners.show.purchase-orders.show', [
            $this->organisation->slug,
            $this->orgPartner->id,
            $purchaseOrder->slug,
        ]);
        $response->assertRedirect($purchaseOrderUrl);

        $count = $this->orgPartner->purchaseOrders()->count();
        $this->post(route('grp.models.org-partner.purchase-order.store', ['orgPartner' => $this->orgPartner->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($purchaseOrderUrl);
        expect($this->orgPartner->purchaseOrders()->count())->toBe($count);
    } finally {
        $partner->update(['is_manufacturing_hub' => $wasHub]);
    }
});

test('ordering from the manufacturing hub fills the shopping list, never a rescue purchase order', function () {
    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    $partner->update(['is_manufacturing_hub' => true]);

    try {
        $orgPartner = $this->orgPartner->refresh();
        $this->get(route('grp.org.procurement.org_partners.show.rescue.index', [$this->organisation->slug, $orgPartner->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('orgPartner.is_hub', true)->where('draftReference', null)->etc());

        $buckets = GetPartnerStockCoverBuckets::make();
        expect((new ReflectionMethod($buckets, 'spareExpression'))->invoke($buckets, $orgPartner, 14))->toBe('null::numeric')
            ->and((int) DB::selectOne('select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, 'null::numeric', 14, $orgPartner).' as quantity
                from (select 0.5 as predicted_daily_usage) s, (select 3 as quantity_available) os, (select null::int as stock_family_id) stocks,
                    (select null::int as measured_lead_time_days, null::int as estimated_lead_time_days, 0 as id, 6 as packed_in) p,
                    lateral ('.(new ReflectionMethod($buckets, 'rowFacts'))->invoke($buckets, $orgPartner, 14)->toRawSql().') rf')->quantity)
            ->toBe((int) ceil(0.5 * 2 * 14 - 3))
            ->and(collect($buckets->rescuable($orgPartner, 0)['buckets'])->pluck('bucket')->all())->toBe(['out', 'w1', 'w2'])
            ->and($buckets->rescuable($orgPartner, 0)['buckets'][0])->toHaveKeys(['cheapest', 'order_cheapest'])
            ->and(fn () => StoreRescuePurchaseOrder::make()->handle($orgPartner))->toThrow(ValidationException::class);

        $hubQuantity = fn (float $usage, float $stock, int $batch) => (int) DB::selectOne('select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, 'null::numeric', 54, $orgPartner).' as quantity
            from (select '.$usage.' as predicted_daily_usage) s, (select '.$stock.' as quantity_available) os, (select null::int as stock_family_id) stocks,
                (select 108 as critical_days, 0 as partner_sko_price, '.$batch.' as order_quantum) rf')->quantity;

        expect($hubQuantity(6.0686, 555, 1))->toBe(101)
            ->and($hubQuantity(0.1328, 1.061, 26))->toBe(26)
            ->and($hubQuantity(0.4085, 20, 18))->toBe(18)
            ->and($hubQuantity(0.0563, 0, 1))->toBe(6);

        $this->post(route('grp.models.org-partner.shopping_list_order.store', ['orgPartner' => $orgPartner->id]))
            ->assertSessionHasErrors('rescue');
    } finally {
        $partner->update(['is_manufacturing_hub' => $wasHub]);
    }

    expect(fn () => PreparePartnerShoppingListOrder::make()->handle($this->orgPartner->refresh()))->toThrow(ValidationException::class);
});

test('a rescue line orders at least a month of sales, never more than the partner can spare', function () {
    $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
    $this->orgPartner->refresh();
    $buckets  = GetPartnerStockCoverBuckets::make();
    $quantity = fn (string $spare) => DB::selectOne(
        'select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, $spare, 14, $this->orgPartner).' as quantity
        from (select 0.5 as predicted_daily_usage) s, (select 3 as quantity_available, 1 as packed_in) os,
            (select null::int as measured_lead_time_days, null::int as estimated_lead_time_days, 0 as id, 1 as packed_in) p, (select null::int as stock_family_id) stocks,
            lateral ('.(new ReflectionMethod($buckets, 'rowFacts'))->invoke($buckets, $this->orgPartner, 14)->toRawSql().') rf'
    )->quantity;

    expect((int) $quantity('500'))->toBe((int) ceil(0.5 * GetPartnerStockCoverBuckets::MINIMUM_COVER_DAYS))
        ->and((int) $quantity('12'))->toBe(12);
});

test('a rescue line to a sister company is rounded up to the partner\'s carton while it can spare it and it lasts under a year', function () {
    $stock       = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $sellerStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->partner, $stock);
    $linkId      = DB::table('org_stock_has_org_supplier_products')->insertGetId([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $stock->id, 'supplier_product_id' => $this->orgSupplierProduct->supplier_product_id]),
        'org_stock_id'            => $sellerStock->id,
        'org_supplier_product_id' => $this->orgSupplierProduct->id,
        'status'                  => true,
        'local_priority'          => 1,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
    $supplierProduct = $this->orgSupplierProduct->supplierProduct;
    $unitsPerCarton  = $supplierProduct->units_per_carton;
    $supplierProduct->updateQuietly(['units_per_carton' => 24]);

    $buckets  = GetPartnerStockCoverBuckets::make();
    $row      = fn (string $spare, float $usage) => DB::selectOne(
        'select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, $spare, 14, $this->orgPartner).' as quantity, rf.carton_skos
        from (select '.$usage.' as predicted_daily_usage) s, (select 3 as quantity_available, 2 as packed_in) os,
            (select null::int as measured_lead_time_days, null::int as estimated_lead_time_days, '.$sellerStock->id.' as id, 1 as packed_in) p, (select null::int as stock_family_id) stocks,
            lateral ('.(new ReflectionMethod($buckets, 'rowFacts'))->invoke($buckets, $this->orgPartner, 14)->toRawSql().') rf'
    );
    $quantity = fn (string $spare, float $usage) => (int) $row($spare, $usage)->quantity;

    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    try {
        $partner->is_manufacturing_hub = false;
        $rounded   = $quantity('500', 0.1);
        $unsold    = $quantity('500', 0.01);
        $tooScarce = $quantity('10', 0.1);
        $supplierProduct->units_per_carton = null;
        $supplierProduct->saveQuietly();
        $noCarton = $row('500', 0.1);
    } finally {
        $partner->is_manufacturing_hub = $wasHub;
    }

    expect(fn () => $supplierProduct->updateQuietly(['units_per_carton' => 0]))->toThrow(\Illuminate\Database\QueryException::class);

    $supplierProduct->updateQuietly(['units_per_carton' => $unitsPerCarton]);
    DB::table('org_stock_has_org_supplier_products')->where('id', $linkId)->delete();

    expect($rounded)->toBe(12)
        ->and($unsold)->toBe(1)
        ->and($tooScarce)->toBe(3)
        ->and((int) $noCarton->quantity)->toBe(3)
        ->and((int) $noCarton->carton_skos)->toBe(1);
});

test('a sku on a rescue being prepared for another sister company is not offered again', function () {
    $orgStock      = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])));
    $buckets       = GetPartnerStockCoverBuckets::make();
    $isComing      = fn () => (bool) DB::selectOne(
        'select '.(new ReflectionMethod($buckets, 'alreadyComingExpression'))->invoke($buckets, $this->orgPartner).' as coming from org_stocks os where os.id = ?',
        [$orgStock->id]
    )->coming;
    expect($isComing())->toBeFalse();
    $purchaseOrder = StorePurchaseOrder::make()->action($this->orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'OTHER-PARTNER-DRAFT-'.PurchaseOrder::max('id')]), strict: false);
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $this->orgSupplierProduct->supplierProduct->historicSupplierProduct, $orgStock, PurchaseOrderTransaction::factory()->definition());

    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'parent_type' => 'OrgPartner', 'parent_id' => $this->orgPartner->id + 1000, 'organisation_id' => $this->orgPartner->organisation_id]);
    expect($isComing())->toBeTrue();

    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::CANCELLED]);
    expect($isComing())->toBeFalse();
});

test('partner order lines show the partner stock and its carton as a guide, and other open orders only for the same sko', function () {
    $stock         = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $buyerOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->organisation, $stock);
    $sellerStock   = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->partner, $stock);
    $buyerOrgStock->updateQuietly(['packed_in' => 6]);
    $sellerStock->updateQuietly(['packed_in' => 6, 'quantity_in_locations' => 40]);
    $partnerLinkId = DB::table('org_stock_has_org_supplier_products')->insertGetId([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $stock->id, 'supplier_product_id' => $this->orgSupplierProduct->supplier_product_id]),
        'org_stock_id'            => $sellerStock->id,
        'org_supplier_product_id' => $this->orgSupplierProduct->id,
        'status'                  => true,
        'local_priority'          => 1,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);

    $action        = IndexPurchaseOrderTransactions::make();
    $partnerStocks = (new ReflectionMethod($action, 'partnerStocks'))->invoke($action, $this->orgPartner, collect([$buyerOrgStock->id]));

    $hub         = $this->orgPartner->partner;
    $hubFlags    = DB::table('organisations')->pluck('is_manufacturing_hub', 'id');
    try {
        DB::table('organisations')->update(['is_manufacturing_hub' => false]);
        DB::table('organisations')->where('id', $hub->id)->update(['is_manufacturing_hub' => true]);
        $hubName = (new ReflectionMethod($action, 'partnerStocks'))->invoke($action, $this->orgPartner, collect([$buyerOrgStock->id]))->get($buyerOrgStock->id)->hub_name;
    } finally {
        foreach ($hubFlags as $organisationId => $isHub) {
            DB::table('organisations')->where('id', $organisationId)->update(['is_manufacturing_hub' => $isHub]);
        }
    }

    expect($hubName)->toBe($hub->name)
        ->and((float) $partnerStocks->get($buyerOrgStock->id)->stock)->toBe(40.0)
        ->and((int) $partnerStocks->get($buyerOrgStock->id)->units_per_carton)->toBe((int) $this->orgSupplierProduct->supplierProduct->units_per_carton);

    $otherOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->organisation, StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])));
    $otherOrder    = StorePurchaseOrder::make()->action($this->orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'OTHER-SKO-'.PurchaseOrder::max('id')]), strict: false);
    $otherLine     = StorePurchaseOrderTransaction::make()->action($otherOrder, $this->orgSupplierProduct->supplierProduct->historicSupplierProduct, $otherOrgStock, PurchaseOrderTransaction::factory()->definition());
    $otherLine->updateQuietly(['supplier_product_id' => null, 'org_supplier_product_id' => null]);
    $otherOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'organisation_id' => $this->orgPartner->organisation_id]);

    $row       = (object) ['supplier_product_id' => null, 'org_stock_id' => $buyerOrgStock->id];
    $paginator = new \Illuminate\Pagination\LengthAwarePaginator([$row], 1, 10);
    IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $this->orgPartner->organisation_id);
    $otherOrder->updateQuietly(['state' => PurchaseOrderStateEnum::CANCELLED]);

    DB::table('org_stock_has_org_supplier_products')->where('id', $partnerLinkId)->delete();

    expect($paginator->items()[0]->other_open_purchase_orders)->toBeEmpty();
});

test('other open orders count only lines for the same sko still waiting for a delivery', function () {
    $supplier    = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $historicSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'OTHER-OPEN-PO',
        'name'             => 'Other open orders',
        'cost'             => 10,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]))->supplierProduct->historicSupplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $deliveredLine = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10]));
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[1], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 20]));

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());
    StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh(), ['purchase_order_transaction_ids' => [$deliveredLine->id]]);
    $purchaseOrder->purchaseOrderTransactions()->update(['supplier_product_id' => null]);

    $rows = collect([$this->orgStocks[0], $this->orgStocks[1], $this->orgStocks[2]])
        ->map(fn ($orgStock) => (object) ['supplier_product_id' => null, 'org_stock_id' => $orgStock->id]);
    $paginator = new \Illuminate\Pagination\LengthAwarePaginator($rows, 3, 10);
    IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $this->organisation->id);
    [$delivered, $waiting, $notOrdered] = $paginator->items();
    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::CANCELLED]);

    $ofThisOrder = fn ($row) => $row->other_open_purchase_orders->where('slug', $purchaseOrder->slug);

    expect($ofThisOrder($delivered))->toBeEmpty()
        ->and($ofThisOrder($waiting)->pluck('quantity_ordered')->all())->toBe([20.0])
        ->and($ofThisOrder($notOrdered))->toBeEmpty();
});


test('rescue order creates nothing when a sister company has nothing to rescue', function () {
    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    $partner->update(['is_manufacturing_hub' => false]);

    try {
        $orgPartner = $this->orgPartner->refresh();
        $before     = $orgPartner->purchaseOrders()->count();

        expect(GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner))->toBe([])
            ->and(fn () => StoreRescuePurchaseOrder::make()->handle($orgPartner))->toThrow(ValidationException::class)
            ->and($orgPartner->purchaseOrders()->count())->toBe($before)
            ->and($orgPartner->purchaseOrders()->where('is_partner_rescue', true)->exists())->toBeFalse();
    } finally {
        $partner->update(['is_manufacturing_hub' => $wasHub]);
    }
});

test('UI show org partners', function () {
    $response = $this->get(route('grp.org.procurement.org_partners.show', [$this->organisation->slug, $this->orgPartner->id]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Procurement/Partner')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgPartner->partner->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('partner showcase lists the customer account the partner buys under', function () {
    [, , $shop] = createOwnShop('partner-customer-accounts');
    $customer = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    DB::table('customers')->where('id', $customer->id)->update(['as_organisation_id' => $this->orgPartner->partner_id]);

    $accounts = GetOrgPartnerShowcase::run($this->orgPartner)['customerAccounts'];

    expect(collect($accounts)->pluck('reference'))->toContain($customer->reference)
        ->and(collect($accounts)->firstWhere('reference', $customer->reference)['shop'])->toBe($shop->name);
});

test('dispatch from other warehouses is off by default, with the default terms and nothing stored', function () {
    $orgPartner = crossDispatchDirection($this->organisation);

    expect($orgPartner->data)->not->toHaveKey('cross_dispatch')
        ->and($orgPartner->isCrossDispatchEnabled())->toBeFalse()
        ->and($orgPartner->crossDispatchSettings())->toBe([
            'enabled_by_origin'       => false,
            'enabled_by_dispatcher'   => false,
            'goods_price_basis'       => 'dispatcher_cost',
            'dispatch_fee_per_parcel' => 0.0,
            'shipping_pass_through'   => 'at_cost',
            'settlement_period'       => 'monthly',
            'allow_customs_crossing'  => false,
            'tax_treatment'           => 'origin_shop_rules',
        ]);
});

test('each company switches only its own side, and a direction is on once both have', function () {
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;
    $dataBefore = $orgPartner->data;

    userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$this->organisation->slug, $orgPartner->id]), [
        'enabled'               => true,
        'enabled_by_dispatcher' => true,
    ])->assertRedirect();

    $orgPartner->refresh();
    expect($orgPartner->crossDispatchSettings()['enabled_by_origin'])->toBeTrue()
        ->and($orgPartner->crossDispatchSettings()['enabled_by_dispatcher'])->toBeFalse()
        ->and($orgPartner->isCrossDispatchEnabled())->toBeFalse()
        ->and(Arr::except($orgPartner->data, 'cross_dispatch'))->toBe($dataBefore);

    userWithOnlyPermissions($this->organisation, ['org-admin.'.$dispatcher->id]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$dispatcher->slug, $orgPartner->id]), [
        'enabled'                 => true,
        'dispatch_fee_per_parcel' => 1.5,
        'settlement_period'       => 'quarterly',
    ])->assertRedirect();

    $orgPartner->refresh();
    expect($orgPartner->isCrossDispatchEnabled())->toBeTrue()
        ->and($orgPartner->crossDispatchSettings()['dispatch_fee_per_parcel'])->toBe(1.5)
        ->and($orgPartner->crossDispatchSettings()['settlement_period'])->toBe('quarterly');

    $this->patch(route('grp.org.settings.cross_dispatch.update', [$dispatcher->slug, $orgPartner->id]), ['enabled' => false])->assertRedirect();

    $orgPartner->refresh();
    expect($orgPartner->isCrossDispatchEnabled())->toBeFalse()
        ->and($orgPartner->crossDispatchSettings()['enabled_by_origin'])->toBeTrue();

    $this->patch(route('grp.org.settings.cross_dispatch.update', [$dispatcher->slug, $orgPartner->id]), ['settlement_period' => 'daily'])
        ->assertSessionHasErrors('settlement_period');
});

test('only an organisation admin of one of the two companies changes the dispatch from other warehouses settings', function () {
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;
    $outsider   = Organisation::where('code', 'xdw3')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'xdw3', 'type' => OrganisationTypeEnum::SHOP]));

    userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id, 'org-admin.'.$outsider->id]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$dispatcher->slug, $orgPartner->id]), ['enabled' => true])->assertForbidden();
    $this->get(route('grp.org.settings.cross_dispatch.index', [$dispatcher->slug]))->assertForbidden();
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$outsider->slug, $orgPartner->id]), ['enabled' => true])->assertNotFound();

    $hiddenPair = OrgPartner::where('organisation_id', $this->organisation->id)->where('partner_id', $outsider->id)->firstOrFail();
    userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$this->organisation->slug, $hiddenPair->id]), ['enabled' => true])->assertNotFound();
    expect(fn () => UpdateCrossDispatchSettings::make()->action($orgPartner, $outsider, ['enabled' => true]))->toThrow(NotFoundHttpException::class)
        ->and($hiddenPair->refresh()->data)->not->toHaveKey('cross_dispatch');

    userWithOnlyPermissions($this->organisation, []);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$outsider->slug, $orgPartner->id]), ['enabled' => true])->assertForbidden();

    userWithOnlyPermissions($this->organisation, ["org-supervisor.{$this->organisation->id}.procurement"]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$this->organisation->slug, $orgPartner->id]), ['enabled' => true])->assertForbidden();

    expect($orgPartner->refresh()->data)->not->toHaveKey('cross_dispatch');
});

test('changes to the dispatch from other warehouses settings are kept in the history', function () {
    OrgPartner::enableAuditing();
    $orgPartner  = crossDispatchDirection($this->organisation);
    $admin       = userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
    $auditsCount = $orgPartner->audits()->count();

    $this->patch(route('grp.org.settings.cross_dispatch.update', [$this->organisation->slug, $orgPartner->id]), [
        'enabled'                 => true,
        'dispatch_fee_per_parcel' => 0,
        'settlement_period'       => 'monthly',
    ])->assertRedirect();

    $audit = $orgPartner->audits()->orderByDesc('id')->first();
    expect($orgPartner->audits()->count())->toBe($auditsCount + 1)
        ->and($audit->event)->toBe('updated')
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->new_values)->toBe(['data.cross_dispatch.enabled_by_origin' => true]);

    $this->patch(route('grp.org.settings.cross_dispatch.update', [$this->organisation->slug, $orgPartner->id]), ['enabled' => true])->assertRedirect();
    expect($orgPartner->audits()->count())->toBe($auditsCount + 1);
});

test('the dispatch from other warehouses settings and the other keys of the partner row never overwrite each other from an old copy', function () {
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;
    $dataBefore = $orgPartner->data;

    try {
        $oldCopy = OrgPartner::find($orgPartner->id);
        UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => true]);
        $oldCopy->mergeIntoData('shopping', ['lead_time_days' => 7]);
        $oldCopy->mergeIntoData('intercompany_customers', [987654 => 5]);

        expect($oldCopy->data)->toBe($orgPartner->fresh()->data)
            ->and($oldCopy->crossDispatchSettings()['enabled_by_origin'])->toBeTrue()
            ->and(data_get($oldCopy->data, 'shopping.lead_time_days'))->toBe(7);

        $oldCopy = OrgPartner::find($orgPartner->id);
        UpdateCrossDispatchSettings::make()->action($orgPartner, $this->organisation, ['enabled' => false]);
        OrgPartner::find($orgPartner->id)->mergeIntoData('intercompany_customers', [987655 => 6]);
        UpdateCrossDispatchSettings::make()->action($oldCopy, $dispatcher, ['enabled' => true]);

        $stored = $orgPartner->fresh();
        expect($stored->crossDispatchSettings()['enabled_by_origin'])->toBeFalse()
            ->and($stored->crossDispatchSettings()['enabled_by_dispatcher'])->toBeTrue()
            ->and($stored->data['intercompany_customers'])->toMatchArray([987654 => 5, 987655 => 6])
            ->and(data_get($stored->data, 'shopping.lead_time_days'))->toBe(7);
    } finally {
        $orgPartner->fresh()->update(['data' => $dataBefore]);
    }
});

test('the dispatch from other warehouses screen shows the history of a direction to the admins of both companies', function () {
    OrgPartner::enableAuditing();
    $orgPartner = crossDispatchDirection($this->organisation);
    $dispatcher = $orgPartner->partner;

    $admin = userWithOnlyPermissions($this->organisation, ['org-admin.'.$dispatcher->id]);
    $this->patch(route('grp.org.settings.cross_dispatch.update', [$dispatcher->slug, $orgPartner->id]), ['enabled' => true])->assertRedirect();

    $audit = $orgPartner->audits()->orderByDesc('id')->first();
    expect($audit->new_values)->toBe(['data.cross_dispatch.enabled_by_dispatcher' => true])
        ->and($audit->organisation_id)->toBe($this->organisation->id);

    $listsTheChange = fn (AssertableInertia $page) => $page
        ->where('history.data', fn ($rows) => collect($rows)->contains(
            fn (array $row) => $row['id'] === $audit->id
                && $row['user_id'] === $admin->id
                && $row['record'] === $this->organisation->name.' → '.$dispatcher->name
                && $row['new_values'] === ['data.cross_dispatch.enabled_by_dispatcher' => true]
        ))
        ->etc();

    $this->get(route('grp.org.settings.cross_dispatch.index', [$dispatcher->slug]))->assertInertia($listsTheChange);

    userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
    $this->get(route('grp.org.settings.cross_dispatch.index', [$this->organisation->slug]))->assertInertia($listsTheChange);
});

test('the dispatch from other warehouses screen lists both directions of the organisation, each with its own switch', function () {
    $outgoing   = crossDispatchDirection($this->organisation);
    $dispatcher = $outgoing->partner;
    $incoming   = crossDispatchDirection($dispatcher, $this->organisation);
    $shop       = createShop()[2];
    $shopType   = $shop->type;
    $otherShop  = Shop::where('organisation_id', $dispatcher->id)->first()
        ?? StoreShop::make()->action($dispatcher, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::DROPSHIPPING]));
    createWarehouse();
    Warehouse::where('organisation_id', $dispatcher->id)->exists() || StoreWarehouse::run($dispatcher, Warehouse::factory()->definition());

    try {
        $shop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
        $otherShop->updateQuietly(['type' => ShopTypeEnum::DROPSHIPPING]);
        UpdateCrossDispatchSettings::make()->action($outgoing, $dispatcher, ['enabled' => true]);

        userWithOnlyPermissions($this->organisation, ['org-admin.'.$this->organisation->id]);
        $this->get(route('grp.org.settings.cross_dispatch.index', [$this->organisation->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Org/Dispatching/CrossDispatchSettings')
                ->where('options.settlement_period', ['weekly', 'monthly', 'quarterly'])
                ->where('data.data', function ($rows) use ($outgoing, $incoming, $dispatcher) {
                    $rows = collect($rows)->keyBy('id');

                    return $rows->every(fn (array $row) => $row['origin_name'] !== $row['dispatcher_name'])
                        && Arr::only($rows[$outgoing->id], ['is_origin', 'our_enabled', 'their_enabled', 'is_on', 'currency_code']) === ['is_origin' => true, 'our_enabled' => false, 'their_enabled' => true, 'is_on' => false, 'currency_code' => $dispatcher->currency->code]
                        && Arr::only($rows[$incoming->id], ['is_origin', 'our_enabled', 'their_enabled']) === ['is_origin' => false, 'our_enabled' => false, 'their_enabled' => false]
                        && $rows[$incoming->id]['update_route']['parameters'] === [$this->organisation->slug, $incoming->id];
                })
                ->etc());
    } finally {
        $shop->updateQuietly(['type' => $shopType]);
    }
});
