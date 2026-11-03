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
                    ? $rescuable->has('buckets', 4)->has('top')->etc()
                    : $rescuable->has('buckets', 3)->has('buckets.0.left_out')->has('top')->etc()
            ));
    });

    $user          = $this->adminGuest->getUser();
    $originalRoles = $user->roles->pluck('name')->toArray();
    actingAsUserWithRoles($user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName('accounting-clerk', $this->organisation)]);
    $this->get(route('grp.org.procurement.org_partners.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can_create_purchase_orders', false)->etc());
    $this->post(route('grp.models.org-partner.purchase-order.store', [$this->orgPartner->id]))->assertForbidden();
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
            ->and($buckets->orderBuckets($orgPartner))->toBe(['out', 'w1', 'w2', 'w3'])
            ->and((int) DB::selectOne('select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, 'null::numeric', 14, $orgPartner).' as quantity
                from (select 0.5 as predicted_daily_usage) s, (select 3 as quantity_available) os, (select null::int as stock_family_id) stocks,
                    (select null::int as measured_lead_time_days, null::int as estimated_lead_time_days, 0 as id, 6 as packed_in) p,
                    lateral ('.(new ReflectionMethod($buckets, 'rowFacts'))->invoke($buckets, $orgPartner, 14)->toRawSql().') rf')->quantity)
            ->toBe((int) ceil(0.5 * GetPartnerStockCoverBuckets::MINIMUM_COVER_DAYS))
            ->and(collect($buckets->rescuable($orgPartner, 0)['buckets'])->pluck('bucket')->all())->toBe(['out', 'w1', 'w2', 'w3'])
            ->and($buckets->rescuable($orgPartner, 0)['buckets'][0])->toHaveKeys(['cheapest', 'order_cheapest'])
            ->and(fn () => StoreRescuePurchaseOrder::make()->handle($orgPartner))->toThrow(ValidationException::class);

        $this->post(route('grp.models.org-partner.shopping_list_order.store', ['orgPartner' => $orgPartner->id]))
            ->assertSessionHasErrors('rescue');
    } finally {
        $partner->update(['is_manufacturing_hub' => $wasHub]);
    }

    expect(fn () => PreparePartnerShoppingListOrder::make()->handle($this->orgPartner->refresh()))->toThrow(ValidationException::class);
});

test('a rescue line orders at least a month of sales, never more than the partner can spare', function () {
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
    $quantity = fn (string $spare, float $usage) => (int) DB::selectOne(
        'select '.(new ReflectionMethod($buckets, 'rescueQuantity'))->invoke($buckets, $spare, 14, $this->orgPartner).' as quantity
        from (select '.$usage.' as predicted_daily_usage) s, (select 3 as quantity_available, 2 as packed_in) os,
            (select null::int as measured_lead_time_days, null::int as estimated_lead_time_days, '.$sellerStock->id.' as id, 1 as packed_in) p, (select null::int as stock_family_id) stocks,
            lateral ('.(new ReflectionMethod($buckets, 'rowFacts'))->invoke($buckets, $this->orgPartner, 14)->toRawSql().') rf'
    )->quantity;

    $partner = $this->orgPartner->partner;
    $wasHub  = $partner->is_manufacturing_hub;
    try {
        $partner->is_manufacturing_hub = false;
        $rounded   = $quantity('500', 0.1);
        $unsold    = $quantity('500', 0.01);
        $tooScarce = $quantity('10', 0.1);
    } finally {
        $partner->is_manufacturing_hub = $wasHub;
    }

    $supplierProduct->updateQuietly(['units_per_carton' => $unitsPerCarton]);
    DB::table('org_stock_has_org_supplier_products')->where('id', $linkId)->delete();

    expect($rounded)->toBe(12)
        ->and($unsold)->toBe(1)
        ->and($tooScarce)->toBe(3);
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
