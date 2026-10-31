<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\Stock\StoreStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\SysAdmin\User;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryUnderOverDeliveredItems;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Procurement\PurchaseOrder\ImportPurchaseOrderTransactions;
use App\Actions\Procurement\PurchaseOrder\RepricePartnerPurchaseOrders;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToReadyToShipStockDeliveryItem;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateOutOfStockForecast;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgPartner\PreparePartnerShoppingListOrder;
use App\Models\CRM\Customer;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\SendPartnerPurchaseOrderToSeller;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Actions\Production\PartnerShippingList\CherryPickPartnerShoppingListItems;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\PartnerShippingList\StoreJobOrdersFromToProduceItems;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Order\UpdateState\DispatchOrder;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\Production\PartnerShippingList\SendPartnerOrderToWarehouse;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Procurement\PartnerShoppingListItem\ImportPartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\SubmitPartnerShoppingList;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerStockDeliveryFromDeliveryNote;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Procurement\PartnerShoppingListItem\SyncPartnerStockDeliveryOnDispatch;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Catalogue\Shop;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Goods\Stock;
use App\Models\SysAdmin\Organisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Inventory\Location;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Enums\Catalogue\HealthRankEnum;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Procurement\OrgPartner\GetPartnerIntercompanyCustomer;
use App\Actions\Procurement\OrgPartner\UI\ShowPartnerBrowse;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

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

describe('partner shopping list', function () {
    afterEach(function () {
        DB::rollBack();
    });

    beforeEach(function () {
        DB::beginTransaction();

        $seller = $this->orgPartner->partner;

        $sellerShop = $seller->shops()->first();
        if (!$sellerShop) {
            $sellerShop = StoreShop::run($seller, Shop::factory()->definition());
        }
        $this->sellerShop = $sellerShop;

        [, $this->sellerProduct] = createProduct($sellerShop);

        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $this->buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];
    });

    test('the shopping list and a partner purchase order take a spreadsheet of our SKO codes and SKOs', function () {
        $csv  = tempnam(sys_get_temp_dir(), 'partner-skos').'.csv';
        $file = function (string $rows) use ($csv) {
            file_put_contents($csv, "code,quantity\n".$rows);

            return new \Illuminate\Http\UploadedFile($csv, 'skos.csv', 'text/csv', null, true);
        };

        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        $upload = ImportPartnerShoppingListItems::make()->action($this->orgPartner->refresh(), $file(strtolower($this->buyerOrgStock->code).",3\nNOT-A-SKO,2\n"));

        expect($upload->number_success)->toBe(1)
            ->and($upload->number_fails)->toBe(1)
            ->and((float) PartnerShoppingListItem::draftPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->value('quantity'))->toBe(3.0);

        $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
        $this->buyerOrgStock->updateQuietly(['packed_in' => 6]);
        $this->sellerProduct->orgStocks()->first()->updateQuietly(['packed_in' => 6]);
        $purchaseOrder = $this->orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest()->first()
            ?? StorePurchaseOrder::make()->action($this->orgPartner->refresh(), []);
        $upload        = ImportPurchaseOrderTransactions::make()->action($purchaseOrder, $file($this->buyerOrgStock->code.",2\n"));

        expect($upload->number_success)->toBe(1)
            ->and((float) $purchaseOrder->purchaseOrderTransactions()->where('org_stock_id', $this->buyerOrgStock->id)->value('quantity_ordered'))->toBe(12.0);
    });

    test('hub quantities go up to whole batches unless the batch is broken on purpose', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $sellerOrgStock->updateQuietly(['packed_in' => 1]);
        $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'BATCHSL', 'name' => 'Batch factory']);
        StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BATCH-SL', 'name' => 'Batched shopping list artefact', 'recommended_batch_size' => 45])
            ->update(['org_stock_id' => $sellerOrgStock->id]);

        $item = submittedPartnerShoppingListItem($this->orgPartner->refresh(), $this->buyerOrgStock, ['quantity' => 50]);
        expect((float) $item->quantity)->toBe(90.0);

        $item = UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => 10]);
        expect((float) $item->quantity)->toBe(45.0);

        $item = UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => 10, 'break_batch' => true]);
        expect((float) $item->quantity)->toBe(10.0);
    });

    test('a mixed bundle holding the SKO does not price it (HELP-3104)', function () {
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $item           = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 1]);
        $basePrice      = (float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity;

        $bundle        = $this->sellerProduct->replicate();
        $bundle->code  = 'BUNDLE-'.$this->sellerProduct->id;
        $bundle->slug  = 'bundle-'.$this->sellerProduct->id;
        $bundle->price = 999;
        $bundle->save();

        DB::table('product_has_org_stocks')->insert([
            ['product_id' => $bundle->id, 'org_stock_id' => $sellerOrgStock->id, 'quantity' => 1],
            ['product_id' => $bundle->id, 'org_stock_id' => $this->buyerOrgStock->id, 'quantity' => 1],
        ]);

        $price = DB::table('partner_shopping_list_items')
            ->where('id', $item->id)
            ->selectRaw(PartnerShoppingListItem::pricePerSkoSql([$this->sellerShop->id]).' as price_per_sko')
            ->value('price_per_sko');

        expect(round((float) $price, 4))->toBe(round($basePrice, 4));
    });

    test('a sample holding a fraction of the SKO neither prices nor sells it while a whole pack does (HELP-4403)', function () {
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $item           = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 1]);
        $basePrice      = (float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity;

        $sample        = $this->sellerProduct->replicate();
        $sample->code  = 'SAMPLE-'.$this->sellerProduct->id;
        $sample->slug  = 'sample-'.$this->sellerProduct->id;
        $sample->price = 1;
        $sample->save();

        DB::table('product_has_org_stocks')->insert([
            'product_id' => $sample->id, 'org_stock_id' => $sellerOrgStock->id, 'quantity' => 0.002,
        ]);

        $price = DB::table('partner_shopping_list_items')
            ->where('id', $item->id)
            ->selectRaw(PartnerShoppingListItem::pricePerSkoSql([$this->sellerShop->id]).' as price_per_sko')
            ->value('price_per_sko');

        expect(round((float) $price, 4))->toBe(round($basePrice, 4))
            ->and(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $sellerOrgStock->stock_id)->id)->toBe($this->sellerProduct->id);
    });

    test('submitting an order adds out-of-stock artefact-linked products to the to-produce list', function () {
        $seller         = $this->orgPartner->partner;
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $production     = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'TPA-'.$sellerOrgStock->id, 'name' => 'Artefact', 'org_stock_id' => $sellerOrgStock->id]);
        $sellerOrgStock->update(['quantity_in_locations' => 2]);

        $customer = StoreCustomer::make()->action($this->sellerShop, Customer::factory()->definition());
        $order    = createOrder($customer, $this->sellerProduct);
        $order->transactions()->update(['quantity_ordered' => 5]);
        SubmitOrder::make()->action($order);

        $item = PartnerShoppingListItem::where('transaction_id', $order->transactions()->first()->id)->first();
        expect($item)->not->toBeNull()
            ->and($item->partner_organisation_id)->toBeNull()
            ->and($item->organisation_id)->toBe($seller->id)
            ->and((float) $item->quantity)->toBe(round(3.0 * (float) $sellerOrgStock->pivot->quantity, 3));

        DispatchOrder::make()->action($order->refresh(), null);
        expect($item->refresh()->state)->toBe(ShoppingListItemStateEnum::ORDERED);
    });

    test('to produce lines become one job order per artisan', function () {
        $seller         = $this->orgPartner->partner;
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $production     = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $artefact = Artefact::where('production_id', $production->id)->where('org_stock_id', $sellerOrgStock->id)->first()
            ?? StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'TPA-'.$sellerOrgStock->id, 'name' => 'Artefact', 'org_stock_id' => $sellerOrgStock->id]);

        $employeeData = Employee::factory()->make(['organisation_id' => $seller->id])->toArray();
        $employeeData['worker_number']   = 'W'.rand(1000, 9999);
        $employeeData['alias']           = 'Alias '.rand(1000, 9999);
        $employeeData['type']            = EmployeeTypeEnum::EMPLOYEE;
        $employeeData['employment_type'] = EmploymentTypeEnum::FULL_TIME;
        $employeeData['state']           = EmployeeStateEnum::WORKING;
        $artisan = StoreEmployee::make()->action($seller, $employeeData);
        AttachArtisan::make()->action($artefact, ['employee_id' => $artisan->id]);

        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 7.5]);

        $result = StoreJobOrdersFromToProduceItems::make()->action($production, [$item->id]);

        expect($result['job_orders'])->toHaveCount(1)
            ->and($result['skipped'])->toBe([]);
        $jobOrder = $result['job_orders'][0];
        expect($jobOrder->employee_id)->toBe($artisan->id)
            ->and($jobOrder->jobOrderItems()->count())->toBe(1)
            ->and($jobOrder->jobOrderItems()->first()->quantity)->toBe(8)
            ->and($item->refresh()->job_order_id)->toBe($jobOrder->id);

        $again = StoreJobOrdersFromToProduceItems::make()->action($production, [$item->id]);
        expect($again['job_orders'])->toBe([]);

        $jobOrderItemId = $jobOrder->jobOrderItems()->first()->id;
        expect(\App\Models\Production\JobOrderItemTask::where('job_order_item_id', $jobOrderItemId)->count())->toBeGreaterThan(0);

        \App\Actions\Production\PartnerShippingList\UnassignToProduceItems::make()->action($production, [$item->id]);
        expect($item->fresh()->job_order_id)->toBeNull()
            ->and(JobOrder::withTrashed()->find($jobOrder->id)->jobOrderItems()->count())->toBe(0)
            ->and(\App\Models\Production\JobOrderItemTask::where('job_order_item_id', $jobOrderItemId)->count())->toBe(0);
    });

    test('production takes a line it cannot make off the board with a reason and the buyer sees why', function () {
        $seller     = $this->orgPartner->partner;
        $production = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $user = $this->adminGuest->getUser();

        $item     = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $inJob    = $item->replicate();
        $inJob->fill(['quantity' => 3, 'pre_picked_at' => now()])->save();

        actingAs($user);
        $this->post(route('grp.org.productions.show.to_produce.items.cant_be_done', [$seller->slug, $production->slug]), ['ids' => [$item->id]])
            ->assertSessionHasErrors('reason');

        $dismissed = \App\Actions\Production\PartnerShippingList\DismissToProduceItems::make()->action($production, [$item->id, $inJob->id], 'No honey in stock until November', $user);

        expect($dismissed->pluck('id')->all())->toBe([$item->id])
            ->and($item->refresh()->state)->toBe(ShoppingListItemStateEnum::DISMISSED)
            ->and($item->dismiss_reason)->toBe('No honey in stock until November')
            ->and($item->dismissed_by_user_id)->toBe($user->id)
            ->and($inJob->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN);

        $row = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]).'?'.http_build_query(['filter' => ['state' => 'dismissed']]))
            ->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('id', $item->id);

        expect($row['progress']['label'])->toBe('Cannot be made')
            ->and(collect($row['progress']['details'])->pluck('label')->all())->toContain('Cannot be made: No honey in stock until November');
    });

    test('store partner shopping list item denormalises', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 40,
        ]);

        expect($item)->toBeInstanceOf(PartnerShoppingListItem::class)
            ->and($item->group_id)->toBe($this->orgPartner->group_id)
            ->and($item->organisation_id)->toBe($this->orgPartner->organisation_id)
            ->and($item->org_partner_id)->toBe($this->orgPartner->id)
            ->and($item->partner_organisation_id)->toBe($this->orgPartner->partner_id)
            ->and($item->org_stock_id)->toBe($this->buyerOrgStock->id)
            ->and($item->stock_id)->toBe($this->buyerOrgStock->stock_id)
            ->and((float) $item->quantity)->toBe(40.0)
            ->and($item->state)->toBe(ShoppingListItemStateEnum::OPEN);
    });

    test('storing a stock already open on the partner shopping list starts a separate draft, storing it again updates that draft', function () {
        $open  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 20]);
        $again = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 30]);

        expect($draft->id)->not->toBe($open->id)
            ->and($draft->state)->toBe(ShoppingListItemStateEnum::DRAFT)
            ->and($again->id)->toBe($draft->id)
            ->and((float) $again->quantity)->toBe(30.0)
            ->and((float) $open->refresh()->quantity)->toBe(5.0)
            ->and(PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('org_stock_id', $this->buyerOrgStock->id)->count())->toBe(2);
    });

    test('update and delete partner shopping list item while open', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 10,
        ]);

        $item = UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => 15, 'notes' => 'for production']);
        expect((float) $item->quantity)->toBe(15.0)
            ->and($item->notes)->toBe('for production');

        DeletePartnerShoppingListItem::make()->action($item);
        expect(PartnerShoppingListItem::find($item->id))->toBeNull();
    });

    test('cherry pick creates hidden intercompany order and marks item ordered', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 25,
        ]);

        $result = CherryPickPartnerShoppingListItems::make()->action(
            $this->orgPartner->partner,
            [['id' => $item->id]]
        );

        expect($result['picked'])->toBe(1)
            ->and($result['skipped'])->toBeEmpty()
            ->and($result['orders'])->toHaveCount(1);

        $order = $result['orders'][0];
        $item->refresh();

        expect($order->state)->toBe(OrderStateEnum::CREATING)
            ->and($order->at_gate_at)->not->toBeNull()
            ->and($order->shop_id)->toBe($this->sellerShop->id)
            ->and($order->salesChannel->code)->toBe('intercompany')
            ->and($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and($item->transaction_id)->not->toBeNull()
            ->and((float) $item->transaction->quantity_ordered)->toBe(25.0)
            ->and($item->transaction->order_id)->toBe($order->id);

        $customerId = data_get($this->orgPartner->refresh()->data, "intercompany_customers.{$this->sellerShop->id}");
        expect($customerId)->toBe($order->customer_id);
    });

    test('hub partners buy at the hub partner discount from settings, not from shop offers', function () {
        $hub              = $this->orgPartner->partner;
        $originalSettings = $hub->settings;
        $hub->update([
            'is_manufacturing_hub' => true,
            'settings'             => array_replace_recursive($hub->settings ?? [], ['procurement' => ['partner_discount' => 0.3]]),
        ]);

        $item        = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $result      = CherryPickPartnerShoppingListItems::make()->action($hub->refresh(), [['id' => $item->id]]);
        $transaction = $item->refresh()->transaction;
        $factor      = GetPartnerBuyingPriceFactor::run($this->orgPartner->refresh());

        $hub->update(['is_manufacturing_hub' => false, 'settings' => $originalSettings]);

        expect($factor)->toBe(0.7)
            ->and((float) $transaction->discretionary_offer)->toBe(0.3)
            ->and($result['orders'][0]->refresh()->discretionary_offers_data)->toHaveKey((string) $transaction->id);
    });

    test('cherry pick partial quantity splits remainder to open child', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 30,
        ]);

        $result = CherryPickPartnerShoppingListItems::make()->action(
            $this->orgPartner->partner,
            [['id' => $item->id, 'quantity' => 12]]
        );

        expect($result['picked'])->toBe(1);

        $item->refresh();
        $child = $item->children()->first();

        expect((float) $item->quantity)->toBe(12.0)
            ->and($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and($child)->not->toBeNull()
            ->and((float) $child->quantity)->toBe(18.0)
            ->and($child->state)->toBe(ShoppingListItemStateEnum::OPEN);
    });

    test('cherry pick remainder joins the open line already waiting for the same stock', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 30,
        ]);
        $waiting = $item->replicate()->fill(['quantity' => 5]);
        $waiting->save();

        CherryPickPartnerShoppingListItems::make()->action(
            $this->orgPartner->partner,
            [['id' => $item->id, 'quantity' => 12]]
        );

        expect($item->refresh()->children()->count())->toBe(0)
            ->and((float) $waiting->refresh()->quantity)->toBe(23.0)
            ->and(PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->count())->toBe(1);
    });

    test('a line the seller deletes from the partner order goes back on the buyer list', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 25,
        ]);
        CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id]]);

        DeleteTransaction::make()->action($item->refresh()->transaction);

        expect($item->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($item->transaction_id)->toBeNull()
            ->and((float) $item->quantity)->toBe(25.0);
    });

    test('what a received job order did not make is freed at cut-off, while a job order still running keeps it', function () {
        $seller     = $this->orgPartner->partner;
        $production = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);

        $leftoverOf = function (\App\Enums\Production\JobOrder\JobOrderStateEnum $state) use ($production) {
            PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->delete();
            $jobOrder = \App\Actions\Production\JobOrder\StoreJobOrder::make()->action($production, []);
            $jobOrder->update(['state' => $state]);
            $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 10]);
            $item->update(['job_order_id' => $jobOrder->id]);

            CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id, 'quantity' => 8]]);

            $leftover = PartnerShoppingListItem::where('parent_id', $item->id)->first();
            $leftover->forceDelete();

            return [$leftover, $jobOrder];
        };

        [$fromReceived]           = $leftoverOf(\App\Enums\Production\JobOrder\JobOrderStateEnum::RECEIVED);
        [$fromRunning, $running]  = $leftoverOf(\App\Enums\Production\JobOrder\JobOrderStateEnum::CONFIRMED);

        expect((float) $fromReceived->quantity)->toBe(2.0)
            ->and($fromReceived->job_order_id)->toBeNull()
            ->and($fromRunning->job_order_id)->toBe($running->id);
    });

    test('a deleted partner order line joins the open line left by a partial pick', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 30,
        ]);
        CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id, 'quantity' => 12]]);

        DeleteTransaction::make()->action($item->refresh()->transaction);

        $openLines = PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->get();

        expect(PartnerShoppingListItem::find($item->id))->toBeNull()
            ->and($openLines)->toHaveCount(1)
            ->and((float) $openLines->first()->quantity)->toBe(30.0);
    });

    test('lowering a partner order line gives the difference back to the buyer list', function () {
        PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->delete();
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 30]);
        CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id]]);
        $transaction = $item->refresh()->transaction;
        $fullQuantity = (float) $transaction->quantity_ordered;

        UpdateTransaction::make()->action($transaction, ['quantity_ordered' => $fullQuantity * 20 / 30]);

        $openLine = PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->sole();
        expect($item->refresh()->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) $item->quantity)->toBe(20.0)
            ->and((float) $openLine->quantity)->toBe(10.0)
            ->and($openLine->parent_id)->toBe($item->id)
            ->and($openLine->transaction_id)->toBeNull();

        $item->update(['pre_picked_at' => now()]);
        UpdateTransaction::make()->action($transaction->refresh(), ['quantity_ordered' => $fullQuantity * 12 / 30]);

        $prePickedBack = PartnerShoppingListItem::where('parent_id', $item->id)->whereNotNull('pre_picked_at')->sole();
        expect((float) $item->refresh()->quantity)->toBe(12.0)
            ->and((float) $prePickedBack->quantity)->toBe(8.0)
            ->and($prePickedBack->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and((float) $openLine->refresh()->quantity)->toBe(10.0);
    });

    test('zeroing a partner order line already in the warehouse puts it back on the buyer list', function () {
        PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->delete();
        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 13]);
        $result = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id]]);
        $transaction = $item->refresh()->transaction;
        $result['orders'][0]->update(['state' => OrderStateEnum::IN_WAREHOUSE]);

        UpdateTransaction::make()->action($transaction->refresh(), ['quantity_ordered' => 0]);

        expect($transaction->refresh()->trashed())->toBeFalse()
            ->and($item->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($item->transaction_id)->toBeNull()
            ->and((float) $item->quantity)->toBe(13.0);
    });

    test('cancelling a partner order puts its lines back on the buyer list', function () {
        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 8,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id]]);

        CancelOrder::make()->action($result['orders'][0]);

        expect($item->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($item->transaction_id)->toBeNull();
    });

    test('a partner request is on its way: what the partner holds is dated from picking, the rest from production', function () {
        $this->sellerProduct->orgStocks()->first()->update(['quantity_available' => 4]);
        submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 10]);

        $lines = collect(GetProductIncomingStock::make()->forOrgStocks([$this->buyerOrgStock->id]))->where('type', 'partner_request')->values();
        $inStock      = $lines->first(fn ($line) => str_starts_with($line['state_label'], 'In stock at'));
        $notScheduled = $lines->first(fn ($line) => str_starts_with($line['state_label'], 'Not scheduled yet'));

        expect($lines)->toHaveCount(2)
            ->and($inStock['quantity'])->toBe(4.0)
            ->and($notScheduled['quantity'])->toBe(6.0)
            ->and($lines->every(fn ($line) => $line['is_estimate'] && $line['eta'] >= now()->addDay()->toDateString()))->toBeTrue()
            ->and($notScheduled['eta'] >= $inStock['eta'])->toBeTrue();
    });

    test('a picked partner request is listed with its order until the order reaches the warehouse', function () {
        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);
        $result = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $item->id]]);

        $line = collect(GetProductIncomingStock::make()->forOrgStocks([$this->buyerOrgStock->id]))->firstWhere('type', 'partner_request');

        expect($line['reference'])->toBe($result['orders'][0]->reference)
            ->and($line['state_label'])->toStartWith('Picked by')
            ->and($line['quantity'])->toBe(3.0);
    });

    test('a website shows quantity and date only of a partner purchase order on its way', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
        $purchaseOrder = $this->orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest()->first()
            ?? StorePurchaseOrder::make()->action($this->orgPartner->refresh(), PurchaseOrder::factory()->definition());
        StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $this->buyerOrgStock, ['quantity_ordered' => 5]);
        $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'delivery_state' => PurchaseOrderDeliveryStateEnum::CONFIRMED]);

        $product = (new \App\Models\Catalogue\Product())->setRelation('orgStocks', collect([$this->buyerOrgStock]));
        $line    = collect(GetProductIncomingStock::run($product, true))->firstWhere('quantity', 5.0);

        expect($line)->toBe(['quantity' => 5.0, 'eta' => null, 'is_estimate' => false]);
    });

    test('a partner request in production shows its job order and who is making it', function () {
        $seller         = $this->orgPartner->partner;
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $sellerOrgStock->update(['quantity_available' => 0]);
        $production = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $artefact = Artefact::where('production_id', $production->id)->where('org_stock_id', $sellerOrgStock->id)->first()
            ?? StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'TPA-'.$sellerOrgStock->id, 'name' => 'Artefact', 'org_stock_id' => $sellerOrgStock->id]);

        $employeeData                    = Employee::factory()->make(['organisation_id' => $seller->id])->toArray();
        $employeeData['worker_number']   = 'W'.rand(1000, 9999);
        $employeeData['alias']           = 'Alias '.rand(1000, 9999);
        $employeeData['type']            = EmployeeTypeEnum::EMPLOYEE;
        $employeeData['employment_type'] = EmploymentTypeEnum::FULL_TIME;
        $employeeData['state']           = EmployeeStateEnum::WORKING;
        $artisan = StoreEmployee::make()->action($seller, $employeeData);
        AttachArtisan::make()->action($artefact, ['employee_id' => $artisan->id]);

        $item     = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $jobOrder = StoreJobOrdersFromToProduceItems::make()->action($production, [$item->id])['job_orders'][0];

        $line = collect(GetProductIncomingStock::make()->forOrgStocks([$this->buyerOrgStock->id]))->firstWhere('type', 'partner_request');

        expect($line['reference'])->toBe($jobOrder->reference)
            ->and($line['state_label'])->toContain($artisan->contact_name)
            ->and($line['quantity'])->toBe(5.0)
            ->and($line['is_estimate'])->toBeTrue();
    });

    test('cherry pick reuses in-process intercompany order across picks', function () {
        $itemA = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 5,
        ]);
        $resultA = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $itemA->id]]);

        $itemB = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 7,
        ]);
        $resultB = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $itemB->id]]);

        expect($resultA['orders'][0]->id)->toBe($resultB['orders'][0]->id);
    });

    test('cherry pick skips item with no seller product for the stock', function () {
        $orphanStock = collect($this->stocks)->first(fn ($stock) => $stock->id !== $this->buyerOrgStock->stock_id);
        $orphanOrgStock = createOrgStocks($this->orgPartner->organisation, [$orphanStock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $orphanOrgStock, [
            'quantity' => 3,
        ]);

        $result = CherryPickPartnerShoppingListItems::make()->action(
            $this->orgPartner->partner,
            [['id' => $item->id]]
        );

        expect($result['picked'])->toBe(0)
            ->and($result['skipped'])->toHaveCount(1)
            ->and($item->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN);
    });

    test('a purchase order to a partner that is not the hub becomes an order there and a stock delivery here', function () {
        $seller = $this->orgPartner->partner;
        $seller->update([
            'is_manufacturing_hub' => false,
            'settings'             => array_replace_recursive($seller->settings ?? [], ['procurement' => ['shop_id' => $this->sellerShop->id]]),
        ]);
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $this->orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->delete();

        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $this->buyerOrgStock->update(['packed_in' => $sellerOrgStock->packed_in]);
        $unitsPerProduct = (float) $sellerOrgStock->pivot->quantity * (float) ($sellerOrgStock->packed_in ?: 1);

        $landedHistoryDate        = '2099-01-01';
        $organisationStockHistoryId = DB::table('organisation_stock_histories')->insertGetId([
            'group_id'                       => $seller->group_id,
            'organisation_id'                => $seller->id,
            'date'                           => $landedHistoryDate,
            'number_org_stocks'              => 1,
            'number_out_of_stock_org_stocks' => 0,
            'number_location_org_stocks'     => 1,
        ]);
        DB::table('org_stock_histories')->insert([
            'organisation_stock_history_id' => $organisationStockHistoryId,
            'organisation_id'               => $seller->id,
            'org_stock_id'                  => $sellerOrgStock->id,
            'date'                          => $landedHistoryDate,
            'fifo_per_sku'                  => 0.4 * (float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity,
        ]);
        $sellerOrgStock->update(['current_supplier_sku_cost' => 0.4 * (float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity]);

        $purchaseOrder = StorePurchaseOrder::make()->action($this->orgPartner, []);
        $line          = StorePurchaseOrderTransaction::make()->addPartnerOrgStock($purchaseOrder, $this->buyerOrgStock->refresh(), ['quantity_ordered' => 3 * $unitsPerProduct]);

        expect($purchaseOrder->currency_id)->toBe($seller->currency_id)
            ->and((float) $line->net_amount)->toBe(round(1.2 * (float) $this->sellerProduct->price, 2));

        $line->update(['unit_cost' => 99, 'net_amount' => 99 * (float) $line->quantity_ordered]);
        expect(RepricePartnerPurchaseOrders::make()->handle($purchaseOrder))->toBe(['orders' => 1, 'lines' => 1])
            ->and((float) $line->refresh()->net_amount)->toBe(round(1.2 * (float) $this->sellerProduct->price, 2))
            ->and((float) $purchaseOrder->refresh()->cost_items)->toBe(round(1.2 * (float) $this->sellerProduct->price, 2));

        $row = collect($this->getJson(route('grp.json.org-partner.purchase-order-org-stocks', [$this->orgPartner->id, $purchaseOrder->slug]))->assertOk()->json('data'))
            ->firstWhere('id', $this->buyerOrgStock->id);
        expect($row['purchase_order_transaction_id'])->toBe($line->id)
            ->and($row['saveRoute']['name'])->toBe('grp.models.purchase-order.transaction.update');

        $purchaseOrder->update(['source_id' => '3:999999']);
        UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
        $purchaseOrder->refresh();
        $order         = \App\Models\Ordering\Order::find(data_get($purchaseOrder->data, 'seller_order_id'));
        $stockDelivery = $purchaseOrder->stockDeliveries()->first();

        expect($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
            ->and($order->shop_id)->toBe($this->sellerShop->id)
            ->and($order->customer_reference)->toBe($purchaseOrder->reference)
            ->and($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and((float) $order->transactions()->first()->quantity_ordered)->toBe(3.0)
            ->and((float) $order->transactions()->first()->gross_amount)->toBe(round(3 * (float) $this->sellerProduct->price, 2))
            ->and((float) $order->transactions()->first()->net_amount)->toBe(round(1.2 * (float) $this->sellerProduct->price, 2))
            ->and($order->transactions()->first()->historic_asset_id)->toBe($this->sellerProduct->current_historic_asset_id)
            ->and($stockDelivery)->not->toBeNull()
            ->and($stockDelivery->delivery_note_id)->toBe($order->deliveryNotes()->first()->id)
            ->and($stockDelivery->items()->first()->org_stock_id)->toBe($this->buyerOrgStock->id);

        expect((float) $line->refresh()->net_amount)->toBe((float) $order->transactions()->first()->net_amount)
            ->and((float) $purchaseOrder->cost_items)->toBe((float) $order->transactions()->first()->net_amount);

        $sellerOrdersBefore = \App\Models\Ordering\Order::where('customer_reference', $purchaseOrder->reference)->count();
        expect(SendPartnerPurchaseOrderToSeller::run($purchaseOrder))->toBeNull()
            ->and(\App\Models\Ordering\Order::where('customer_reference', $purchaseOrder->reference)->count())->toBe($sellerOrdersBefore)
            ->and($stockDelivery->purchaseOrders()->count())->toBe(1);

        $fifoPerSko = 0.4 * (float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity;
        $originalSupplierCost = $sellerOrgStock->current_supplier_sku_cost;
        $sellerOrgStock->update(['current_supplier_sku_cost' => round($fifoPerSko * 8, 2)]);
        expect(GetPartnerLandedCost::run([$sellerOrgStock->id])[$sellerOrgStock->id])->toBe(round($fifoPerSko * 8, 2));
        $sellerOrgStock->update(['current_supplier_sku_cost' => round($fifoPerSko / 8, 2)]);
        expect(GetPartnerLandedCost::run([$sellerOrgStock->id])[$sellerOrgStock->id])->toBe(round($fifoPerSko / 8, 2));
        $sellerOrgStock->update(['current_supplier_sku_cost' => round($fifoPerSko * 1.5, 2)]);
        expect(GetPartnerLandedCost::run([$sellerOrgStock->id])[$sellerOrgStock->id])->toEqualWithDelta($fifoPerSko, 0.0001);
        $sellerOrgStock->update(['current_supplier_sku_cost' => $originalSupplierCost]);

        $originalSkosPerProduct = $sellerOrgStock->pivot->quantity;
        $originalPackedIn       = $sellerOrgStock->packed_in;
        DB::table('product_has_org_stocks')->where('product_id', $this->sellerProduct->id)->where('org_stock_id', $sellerOrgStock->id)->update(['quantity' => 3]);
        $sellerOrgStock->update(['packed_in' => 1]);
        $this->buyerOrgStock->update(['packed_in' => 1]);

        $skosNotInWholePacks = StorePurchaseOrder::make()->action($this->orgPartner, []);
        StorePurchaseOrderTransaction::make()->addPartnerOrgStock($skosNotInWholePacks, $this->buyerOrgStock->refresh(), ['quantity_ordered' => 100]);
        UpdatePurchaseOrderStateToSubmitted::make()->action($skosNotInWholePacks->refresh());
        $sellerOrder = \App\Models\Ordering\Order::find(data_get($skosNotInWholePacks->refresh()->data, 'seller_order_id'));

        expect((float) $sellerOrder->deliveryNotes()->first()->deliveryNoteItems()->first()->quantity_required)->toBe(100.0);

        DB::table('product_has_org_stocks')->where('product_id', $this->sellerProduct->id)->where('org_stock_id', $sellerOrgStock->id)->update(['quantity' => $originalSkosPerProduct]);
        $sellerOrgStock->update(['packed_in' => $originalPackedIn]);
        $this->buyerOrgStock->update(['packed_in' => $originalPackedIn]);

        DB::table('org_stock_histories')->where('organisation_stock_history_id', $organisationStockHistoryId)->delete();
        DB::table('organisation_stock_histories')->where('id', $organisationStockHistoryId)->delete();

        $blockedPurchaseOrder = StorePurchaseOrder::make()->action($this->orgPartner, []);
        StorePurchaseOrderTransaction::make()->addPartnerOrgStock($blockedPurchaseOrder, $this->buyerOrgStock->refresh(), ['quantity_ordered' => $unitsPerProduct]);
        $blockedPurchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);
        $sellerSettings = $seller->settings;
        $seller->update(['settings' => array_replace_recursive($sellerSettings, ['procurement' => ['shop_id' => null]])]);

        expect(SendPartnerPurchaseOrderToSeller::run($blockedPurchaseOrder))->toBeNull()
            ->and($blockedPurchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS)
            ->and(data_get($blockedPurchaseOrder->data, 'seller_order_id'))->toBeNull();

        $seller->update(['settings' => $sellerSettings]);

        $repackedTwin            = $this->buyerOrgStock->replicate();
        $repackedTwin->code      = $this->buyerOrgStock->code.'-repacked';
        $repackedTwin->slug      = $this->buyerOrgStock->slug.'-repacked';
        $repackedTwin->packed_in = (float) ($this->buyerOrgStock->packed_in ?: 1) + 1;
        $repackedTwin->saveQuietly();

        $adminUser      = $this->adminGuest->getUser();
        $submitProblems = SendPartnerPurchaseOrderToSeller::make()->submitProblems($blockedPurchaseOrder->refresh(), $adminUser);
        $packingProblem = collect($submitProblems)->firstWhere('org_stock_id', $this->buyerOrgStock->id);
        $fixRoutes      = array_column($packingProblem['fixes'], 'route');

        expect(array_column($submitProblems, 'message'))->toBe(SendPartnerPurchaseOrderToSeller::make()->problems($blockedPurchaseOrder))
            ->and($packingProblem['message'])->toContain($repackedTwin->code)
            ->and($packingProblem['task']['description'])->toContain($blockedPurchaseOrder->reference)
            ->and(array_column($fixRoutes, 'name'))->each->toBe('grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.composition')
            ->and(array_column(array_column($fixRoutes, 'parameters'), 'orgStock'))->toBe([$this->buyerOrgStock->slug, $repackedTwin->slug]);

        actingAs($adminUser);
        $this->patch(route('grp.models.purchase-order.submit', $blockedPurchaseOrder->id))->assertSessionHasErrors('purchase_order');
        expect($blockedPurchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

        $pageHeadActions = $this->withoutVite()
            ->get(route('grp.org.procurement.org_partners.show.purchase-orders.show', [$blockedPurchaseOrder->organisation->slug, $this->orgPartner->id, $blockedPurchaseOrder->slug]))
            ->assertOk()
            ->viewData('page')['props']['pageHead']['actions'];
        expect(collect($pageHeadActions)->firstWhere('key', 'submit_purchase_order')['send_channels'])->toBe([]);

        $repackedTwin->forceDelete();
        $blockedPurchaseOrder->delete();

        $seller->update(['is_manufacturing_hub' => true]);
        expect(fn () => StorePurchaseOrder::make()->action($this->orgPartner, []))->toThrow(ValidationException::class);
    });

    function replicateSellerProductInNewShop(Organisation $seller, \App\Models\Catalogue\Product $product, float $price): \App\Models\Catalogue\Product
    {
        $otherShop = StoreShop::run($seller, Shop::factory()->definition());

        $replica        = $product->replicate();
        $replica->code  = 'REPL-'.$product->id.'-'.$otherShop->id;
        $replica->slug  = 'repl-'.$product->id.'-'.$otherShop->id;
        $replica->shop_id = $otherShop->id;
        $replica->price = $price;
        $replica->save();

        $orgStock = $product->orgStocks()->first();
        DB::table('product_has_org_stocks')->insert([
            'product_id' => $replica->id, 'org_stock_id' => $orgStock->id, 'quantity' => $orgStock->pivot->quantity,
        ]);

        return $replica;
    }

    test('a partner sells a stock from the first listed shop that sells it, not the cheapest', function () {
        $seller           = $this->orgPartner->partner;
        $originalSettings = $seller->settings;

        try {
            $pricier = replicateSellerProductInNewShop($seller, $this->sellerProduct, (float) $this->sellerProduct->price + 5);
            $stockId = $this->sellerProduct->orgStocks()->first()->stock_id;

            $seller->update(['settings' => array_replace_recursive($seller->settings ?? [], ['procurement' => ['shop_ids' => [$pricier->shop_id, $this->sellerShop->id]]])]);
            expect(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $stockId)->id)->toBe($pricier->id);

            $seller->update(['settings' => array_replace_recursive($seller->settings, ['procurement' => ['shop_ids' => [$this->sellerShop->id, $pricier->shop_id]]])]);
            expect(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $stockId)->id)->toBe($this->sellerProduct->id)
                ->and(GetPartnerSellingProduct::run($this->orgPartner, $stockId, [$pricier->shop_id])->id)->toBe($pricier->id)
                ->and(GetPartnerSellingProduct::run($this->orgPartner, $stockId, [0]))->toBeNull();
        } finally {
            $seller->update(['settings' => $originalSettings]);
        }
    });

    test('a discontinued -error SKO of the seller does not hide the live one it sells', function () {
        $sellerOrgStock    = $this->sellerProduct->orgStocks()->first();
        $errorOrgStock     = $sellerOrgStock->replicate();
        $errorOrgStock->code  = $sellerOrgStock->code.'-error';
        $errorOrgStock->slug  = $sellerOrgStock->slug.'-error';
        $errorOrgStock->state = OrgStockStateEnum::DISCONTINUED;
        $errorOrgStock->save();

        try {
            expect(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $sellerOrgStock->stock_id)?->id)->toBe($this->sellerProduct->id);
        } finally {
            $errorOrgStock->forceDelete();
        }
    });

    test('another customer\'s exclusive products are off limits to a partner', function () {
        $sellerOrgStock      = $this->sellerProduct->orgStocks()->first();
        $outsideCustomer     = StoreCustomer::make()->action($this->sellerShop, Customer::factory()->definition());
        $originalPartnerData = $this->orgPartner->refresh()->data;
        $makeExclusive       = fn (\App\Models\Catalogue\Product $product, int $customerId) => DB::table('product_has_exclusive_customers')->insert([
            'product_id' => $product->id, 'customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $specialLabel        = $this->sellerProduct->replicate();
        $specialLabel->code  = 'SC-'.$this->sellerProduct->id;
        $specialLabel->slug  = 'sc-'.$this->sellerProduct->id;
        $specialLabel->price = round((float) $this->sellerProduct->price / 2, 2);
        $specialLabel->save();
        DB::table('product_has_org_stocks')->insert([
            'product_id' => $specialLabel->id, 'org_stock_id' => $sellerOrgStock->id, 'quantity' => $sellerOrgStock->pivot->quantity,
        ]);
        $makeExclusive($specialLabel, $outsideCustomer->id);

        try {
            $pricePerSko = fn () => DB::query()->selectRaw(PartnerSkoPrice::pricePerSkoSql((string) $sellerOrgStock->id, [$this->sellerShop->id], (string) $this->orgPartner->id).' as price')->value('price');

            expect(GetPartnerSellingProduct::run($this->orgPartner, $sellerOrgStock->stock_id)->id)->toBe($this->sellerProduct->id)
                ->and(round((float) $pricePerSko(), 4))->toBe(round((float) $this->sellerProduct->price / (float) $sellerOrgStock->pivot->quantity, 4))
                ->and(PartnerSkoPrice::isOffLimitsToPartner($this->orgPartner, $sellerOrgStock->stock_id))->toBeFalse();

            $makeExclusive($this->sellerProduct, $outsideCustomer->id);

            expect(GetPartnerSellingProduct::run($this->orgPartner, $sellerOrgStock->stock_id))->toBeNull()
                ->and(PartnerSkoPrice::isOffLimitsToPartner($this->orgPartner, $sellerOrgStock->stock_id))->toBeTrue()
                ->and(fn () => StorePartnerShoppingListItem::make()->action($this->orgPartner, $sellerOrgStock, ['quantity' => 1]))->toThrow(ValidationException::class, 'another customer');

            $this->orgPartner->update(['data' => array_replace_recursive($originalPartnerData ?? [], ['intercompany_customers' => ['test-'.$outsideCustomer->id => $outsideCustomer->id]])]);

            expect(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $sellerOrgStock->stock_id)->id)->toBe($specialLabel->id)
                ->and(PartnerSkoPrice::isOffLimitsToPartner($this->orgPartner, $sellerOrgStock->stock_id))->toBeFalse();
        } finally {
            $this->orgPartner->update(['data' => $originalPartnerData]);
            DB::table('product_has_exclusive_customers')->whereIn('product_id', [$specialLabel->id, $this->sellerProduct->id])->where('customer_id', $outsideCustomer->id)->delete();
            DB::table('product_has_org_stocks')->where('product_id', $specialLabel->id)->delete();
            $specialLabel->forceDelete();
        }
    });

    test('a partner sells from its procurement shop when no list of shops is set', function () {
        $seller           = $this->orgPartner->partner;
        $originalSettings = $seller->settings;

        try {
            $pricier = replicateSellerProductInNewShop($seller, $this->sellerProduct, (float) $this->sellerProduct->price + 5);
            $stockId = $this->sellerProduct->orgStocks()->first()->stock_id;

            $seller->update(['settings' => Arr::except(array_replace_recursive($seller->settings ?? [], ['procurement' => ['shop_id' => $pricier->shop_id]]), 'procurement.shop_ids')]);

            expect(GetPartnerSellingShopIds::run($seller->refresh()))->toBe([$pricier->shop_id])
                ->and(GetPartnerSellingProduct::run($this->orgPartner->refresh(), $stockId)->id)->toBe($pricier->id);
        } finally {
            $seller->update(['settings' => $originalSettings]);
        }
    });

    test('cherry picking orders from the first listed shop even when a later one is cheaper', function () {
        $seller           = $this->orgPartner->partner;
        $originalSettings = $seller->settings;

        try {
            $cheaper = replicateSellerProductInNewShop($seller, $this->sellerProduct, 0.01);
            $seller->update(['settings' => array_replace_recursive($seller->settings ?? [], ['procurement' => ['shop_ids' => [$this->sellerShop->id, $cheaper->shop_id]]])]);
            if (!$seller->warehouses()->exists()) {
                StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
            }

            $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 20]);
            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $item->id]]);

            expect($result['picked'])->toBe(1)
                ->and($result['orders'][0]->shop_id)->toBe($this->sellerShop->id);
        } finally {
            $seller->update(['settings' => $originalSettings]);
        }
    });

    test('a partner can split cosmetics into their own orders and bay', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $warehouse = $seller->warehouses()->first();
        $mainBay   = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $cosmeticBay = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $mainBay->update(['is_goods_out' => true]);
        $cosmeticBay->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalSettings = $sellerPartner->only(['goods_out_location_id', 'split_cosmetics', 'cosmetic_goods_out_location_id']);

        $listedLine = function (bool $isCosmetic) use ($seller) {
            $stock = StoreStock::make()->action($seller->group, Stock::factory()->definition());
            $stock->update(['is_cosmetic' => $isCosmetic]);
            createOrgStocks($seller, [$stock]);
            \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->sellerShop, array_merge(
                \App\Models\Catalogue\Product::factory()->definition(),
                [
                    'state'       => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE,
                    'trade_units' => [['id' => $stock->tradeUnits()->firstOrFail()->id, 'quantity' => 1]],
                ]
            ));

            return submittedPartnerShoppingListItem($this->orgPartner, createOrgStocks($this->orgPartner->organisation, [$stock])[0], ['quantity' => 2]);
        };

        try {
            $sellerPartner->update(['goods_out_location_id' => $mainBay->id, 'split_cosmetics' => false, 'cosmetic_goods_out_location_id' => $cosmeticBay->id]);

            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $listedLine(true)->id], ['id' => $listedLine(false)->id]]);
            expect($result['orders'])->toHaveCount(1)
                ->and($sellerPartner->refresh()->bayIdFor(true))->toBe($mainBay->id)
                ->and($sellerPartner->bayIdFor(false))->toBe($mainBay->id);

            $sellerPartner->update(['split_cosmetics' => true]);
            expect($sellerPartner->refresh()->bayIdFor(true))->toBe($cosmeticBay->id)
                ->and($sellerPartner->bayIdFor(false))->toBe($mainBay->id)
                ->and($sellerPartner->bayFor(true)->id)->toBe($cosmeticBay->id)
                ->and($sellerPartner->bayIds())->toBe([$mainBay->id, $cosmeticBay->id]);

            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $listedLine(true)->id], ['id' => $listedLine(false)->id], ['id' => $listedLine(true)->id]]);
            $orders = collect($result['orders']);
            expect($result['picked'])->toBe(3)
                ->and($orders)->toHaveCount(2)
                ->and($orders->map(fn ($order) => (bool) data_get($order->refresh()->data, 'partner_cosmetic'))->sort()->values()->all())->toBe([false, true])
                ->and($orders->firstWhere(fn ($order) => data_get($order->data, 'partner_cosmetic'))->transactions()->count())->toBe(2);

            $sellerPartner->update(['cosmetic_goods_out_location_id' => null]);
            expect($sellerPartner->refresh()->bayIdFor(true))->toBe($mainBay->id);
        } finally {
            $sellerPartner->update($originalSettings);
        }
    });

    test('the cosmetic bay settings of a partner can be edited', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $bay     = \App\Actions\Inventory\Location\StoreLocation::make()->action($seller->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        $mainBay = \App\Actions\Inventory\Location\StoreLocation::make()->action($seller->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        $mainBay->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalSettings = $sellerPartner->only(['goods_out_location_id', 'split_cosmetics', 'cosmetic_goods_out_location_id']);

        try {
            $sellerPartner->update(['goods_out_location_id' => null, 'cosmetic_goods_out_location_id' => null]);
            $bay->update(['is_goods_out' => true]);
            expect(fn () => \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), ['cosmetic_goods_out_location_id' => $bay->id]))
                ->toThrow(ValidationException::class, 'Set the partner goods out bay first');

            $sellerPartner->update(['goods_out_location_id' => $mainBay->id]);
            $bay->update(['is_goods_out' => false]);
            expect(fn () => \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), ['cosmetic_goods_out_location_id' => $bay->id]))
                ->toThrow(ValidationException::class)
                ->and(fn () => \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), ['cosmetic_goods_out_location_id' => $mainBay->id]))
                ->toThrow(ValidationException::class);

            $bay->update(['is_goods_out' => true]);
            \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), ['split_cosmetics' => true, 'cosmetic_goods_out_location_id' => $bay->id]);
            \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), ['split_cosmetics' => true, 'cosmetic_goods_out_location_id' => $bay->id]);
            expect($sellerPartner->refresh()->split_cosmetics)->toBeTrue()
                ->and($sellerPartner->cosmetic_goods_out_location_id)->toBe($bay->id);
        } finally {
            $sellerPartner->update($originalSettings);
        }
    });

    test('a partner can split GB-origin goods onto their own pallet, family by family', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $warehouse = $seller->warehouses()->first();
        $mainBay   = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $gbBay     = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $mainBay->update(['is_goods_out' => true]);
        $gbBay->update(['is_goods_out' => true]);

        $stockFamily = \App\Actions\Goods\StockFamily\StoreStockFamily::make()->action($seller->group, \App\Models\Goods\StockFamily::factory()->definition());
        $family      = \App\Models\Inventory\OrgStockFamily::where('organisation_id', $seller->id)->where('stock_family_id', $stockFamily->id)->first()
            ?? \App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily::make()->action($seller, $stockFamily, []);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalSettings = $sellerPartner->only(['goods_out_location_id', 'split_cosmetics', 'cosmetic_goods_out_location_id', 'split_gb_origin', 'gb_goods_out_location_id']);

        $listedLine = function (bool $isGbOrigin) use ($seller, $family) {
            $stock = StoreStock::make()->action($seller->group, Stock::factory()->definition());
            $stock->update(['is_cosmetic' => false, 'is_gb_origin' => $isGbOrigin]);
            createOrgStocks($seller, [$stock])[0]->update(['org_stock_family_id' => $family->id]);
            \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->sellerShop, array_merge(
                \App\Models\Catalogue\Product::factory()->definition(),
                [
                    'state'       => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE,
                    'trade_units' => [['id' => $stock->tradeUnits()->firstOrFail()->id, 'quantity' => 1]],
                ]
            ));

            return submittedPartnerShoppingListItem($this->orgPartner, createOrgStocks($this->orgPartner->organisation, [$stock])[0], ['quantity' => 2]);
        };

        try {
            $sellerPartner->update(['goods_out_location_id' => $mainBay->id, 'split_cosmetics' => false, 'cosmetic_goods_out_location_id' => null, 'split_gb_origin' => false, 'gb_goods_out_location_id' => $gbBay->id]);
            $family->update(['gb_separate_pallet' => true]);

            $gbLine = $listedLine(true);
            expect($sellerPartner->refresh()->bayIdFor(false, true))->toBe($mainBay->id)
                ->and($sellerPartner->isGbPallet($gbLine->stock_id))->toBeTrue();

            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $gbLine->id], ['id' => $listedLine(false)->id]]);
            expect($result['orders'])->toHaveCount(1);

            $sellerPartner->update(['split_gb_origin' => true]);
            expect($sellerPartner->refresh()->bayIdFor(false, true))->toBe($gbBay->id)
                ->and($sellerPartner->bayIdFor(false, false))->toBe($mainBay->id)
                ->and($sellerPartner->bayFor(false, true)->id)->toBe($gbBay->id)
                ->and($sellerPartner->splitFor(true, true))->toBe('gb')
                ->and($sellerPartner->bayIds())->toBe([$mainBay->id, $gbBay->id]);

            $gbLine     = $listedLine(true);
            $sqlBay     = \Illuminate\Support\Facades\DB::selectOne('select '.\App\Models\Procurement\OrgPartner::bayIdSql('org_partners', (string) $gbLine->stock_id).' as bay from org_partners where id = ?', [$sellerPartner->id])->bay;
            expect((int) $sqlBay)->toBe($gbBay->id);

            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $gbLine->id], ['id' => $listedLine(false)->id], ['id' => $listedLine(true)->id]]);
            $orders = collect($result['orders']);
            expect($result['picked'])->toBe(3)
                ->and($orders)->toHaveCount(2)
                ->and($orders->map(fn ($order) => (bool) data_get($order->refresh()->data, 'partner_gb'))->sort()->values()->all())->toBe([false, true])
                ->and($orders->firstWhere(fn ($order) => data_get($order->data, 'partner_gb'))->transactions()->count())->toBe(2);

            $family->update(['gb_separate_pallet' => false]);
            $offLine = $listedLine(true);
            expect($sellerPartner->isGbPallet($offLine->stock_id))->toBeFalse();
            $result = CherryPickPartnerShoppingListItems::make()->action($seller->refresh(), [['id' => $offLine->id]]);
            expect((bool) data_get($result['orders'][0]->refresh()->data, 'partner_gb'))->toBeFalse()
                ->and($result['orders'][0]->id)->not->toBe($orders->firstWhere(fn ($order) => data_get($order->data, 'partner_gb'))->id);
        } finally {
            $family->update(['gb_separate_pallet' => true]);
            $sellerPartner->update($originalSettings);
        }
    });

    test('the GB bay of a partner must be a free goods out bay, apart from its cosmetic bay', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $bay         = \App\Actions\Inventory\Location\StoreLocation::make()->action($seller->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        $mainBay     = \App\Actions\Inventory\Location\StoreLocation::make()->action($seller->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        $cosmeticBay = \App\Actions\Inventory\Location\StoreLocation::make()->action($seller->warehouses()->first(), \App\Models\Inventory\Location::factory()->definition());
        $mainBay->update(['is_goods_out' => true]);
        $cosmeticBay->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalSettings = $sellerPartner->only(['goods_out_location_id', 'split_cosmetics', 'cosmetic_goods_out_location_id', 'split_gb_origin', 'gb_goods_out_location_id']);
        $update           = fn (array $data) => \App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings::make()->action($sellerPartner->refresh(), $data);

        try {
            $sellerPartner->update(['goods_out_location_id' => $mainBay->id, 'cosmetic_goods_out_location_id' => $cosmeticBay->id, 'gb_goods_out_location_id' => null]);
            expect(fn () => $update(['gb_goods_out_location_id' => $bay->id]))->toThrow(ValidationException::class, 'goods out gathering location')
                ->and(fn () => $update(['gb_goods_out_location_id' => $mainBay->id]))->toThrow(ValidationException::class, 'The GB bay must be different from the partner goods out bay')
                ->and(fn () => $update(['gb_goods_out_location_id' => $cosmeticBay->id]))->toThrow(ValidationException::class, 'The GB bay must be different from the cosmetic bay');

            $bay->update(['is_goods_out' => true]);
            $update(['split_gb_origin' => true, 'gb_goods_out_location_id' => $bay->id]);
            expect($sellerPartner->refresh()->split_gb_origin)->toBeTrue()
                ->and($sellerPartner->gb_goods_out_location_id)->toBe($bay->id)
                ->and(\App\Models\Procurement\OrgPartner::withBay($bay->id)->value('id'))->toBe($sellerPartner->id)
                ->and(fn () => \App\Actions\Procurement\OrgPartner\SetPartnerGoodsOutLocation::run($sellerPartner->refresh(), $bay))->toThrow(ValidationException::class);
        } finally {
            $sellerPartner->update($originalSettings);
        }
    });

    test('a hub lists its families with GB-origin SKOs and switches their GB pallet', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $warehouse   = $seller->warehouses()->first();
        $stockFamily = \App\Actions\Goods\StockFamily\StoreStockFamily::make()->action($seller->group, \App\Models\Goods\StockFamily::factory()->definition());
        $family      = \App\Models\Inventory\OrgStockFamily::where('organisation_id', $seller->id)->where('stock_family_id', $stockFamily->id)->first()
            ?? \App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily::make()->action($seller, $stockFamily, []);
        $stock = StoreStock::make()->action($seller->group, Stock::factory()->definition());
        $stock->update(['is_gb_origin' => true]);
        createOrgStocks($seller, [$stock])[0]->update(['org_stock_family_id' => $family->id, 'state' => \App\Enums\Inventory\OrgStock\OrgStockStateEnum::ACTIVE]);

        $listUrl = route('grp.org.warehouses.show.inventory.org_stock_families.gb_pallet.index', [$seller->slug, $warehouse->slug]);
        $props   = $this->get($listUrl.'?index_filter[global]='.$family->code)->assertOk()->viewData('page')['props'];
        $row     = collect($props['index']['data'])->firstWhere('slug', $family->slug);
        expect($row['gb_separate_pallet'])->toBeTrue()
            ->and($row['number_gb_org_stocks'])->toBe(1)
            ->and($props['gb_pallet_can_edit'])->toBeTrue();

        $this->patch(route('grp.org.warehouses.show.inventory.org_stock_families.gb_pallet.update', [$seller->slug, $warehouse->slug, $family->slug]), ['gb_separate_pallet' => false])
            ->assertRedirect();
        expect($family->refresh()->gb_separate_pallet)->toBeFalse();

        $offSlugs = collect($this->get($listUrl.'?index_elements[gb_pallet]=off')->viewData('page')['props']['index']['data'])->pluck('slug');
        $onSlugs  = collect($this->get($listUrl.'?index_elements[gb_pallet]=on')->viewData('page')['props']['index']['data'])->pluck('slug');
        expect($offSlugs)->toContain($family->slug)
            ->and($onSlugs)->not->toContain($family->slug);

        $this->get(route('grp.org.warehouses.show.inventory.org_stock_families.show', [$seller->slug, $warehouse->slug, $family->slug]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('gb_pallet.value', false)
                ->where('gb_pallet.gb_org_stocks', 1));

        $family->update(['gb_separate_pallet' => true]);
    });

    test('send partner order to warehouse creates DN and mirror stock delivery in buyer org', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 20,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        $stockDelivery = SendPartnerOrderToWarehouse::make()->action($order);
        $order->refresh();

        expect($order->state)->toBe(OrderStateEnum::IN_WAREHOUSE)
            ->and($order->deliveryNotes()->count())->toBe(1)
            ->and($stockDelivery)->not->toBeNull()
            ->and($stockDelivery->organisation_id)->toBe($this->orgPartner->organisation_id)
            ->and($stockDelivery->parent_id)->toBe($this->orgPartner->id)
            ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::CONFIRMED)
            ->and($stockDelivery->delivery_note_id)->toBe($order->deliveryNotes()->first()->id)
            ->and($stockDelivery->reference)->toBe($order->deliveryNotes()->first()->reference)
            ->and(StorePartnerStockDeliveryFromDeliveryNote::make()->reference($order->deliveryNotes()->first(), $stockDelivery->organisation))->not->toBe($stockDelivery->reference)
            ->and($stockDelivery->items()->count())->toBe(1)
            ->and($stockDelivery->items()->first()->org_stock_id)->toBe($this->buyerOrgStock->id)
            ->and((float) $stockDelivery->items()->first()->net_amount)->toBe((float) $order->deliveryNotes()->first()->deliveryNoteItems()->first()->transaction->net_amount);
    });

    test('mirror stock delivery counts units, not the SKOs on the delivery note', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $packedIn       = $sellerOrgStock->packed_in;
        $sellerOrgStock->update(['packed_in' => 4]);

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 12,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        $stockDelivery    = SendPartnerOrderToWarehouse::make()->action($order);
        $deliveryNote     = $order->deliveryNotes()->first();
        $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->first();

        expect((float) $stockDelivery->items()->first()->unit_quantity)->toBe((float) $deliveryNoteItem->quantity_required * 4);

        $deliveryNoteItem->update(['quantity_dispatched' => 2]);
        SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());

        expect((float) $stockDelivery->items()->first()->refresh()->unit_quantity)->toBe(8.0);

        $stockDelivery->items()->first()->update(['unit_quantity_checked' => 4, 'checked_at' => now()]);
        request()->setRouteResolver(fn () => (new Illuminate\Routing\Route('GET', 'under-over', []))->name('under-over'));
        $underOverDelivered = IndexStockDeliveryUnderOverDeliveredItems::make()->handle($stockDelivery)->first();

        expect((float) $underOverDelivered->difference_skos)->toBe(-1.0);

        DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
        $sellerOrgStock->update(['packed_in' => $packedIn]);
    });

    test('mirror stock delivery has one item per stock when the delivery note carries the stock on two lines', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 12]);
        $order = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]])['orders'][0];

        SendPartnerOrderToWarehouse::make()->action($order);
        $deliveryNote     = $order->deliveryNotes()->first();
        $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->first();
        $unitsRequired    = (float) $deliveryNoteItem->quantity_required * (float) ($deliveryNoteItem->orgStock->packed_in ?: 1);
        $netAmount        = (float) $deliveryNoteItem->transaction->net_amount;

        $zeroedLine = $deliveryNoteItem->replicate(['has_waiting_warehouse', 'has_waiting_crm']);
        $zeroedLine->quantity_required = 0;
        $zeroedLine->save();

        $stockDelivery = StorePartnerStockDeliveryFromDeliveryNote::run($deliveryNote->refresh());

        $orgExchange = GetHistoricCurrencyExchange::run($seller->currency, $this->orgPartner->organisation->currency, now()->startOfDay());

        expect($stockDelivery->items()->count())->toBe(1)
            ->and((float) $stockDelivery->items()->first()->unit_quantity)->toBe($unitsRequired)
            ->and((float) $stockDelivery->items()->first()->net_amount)->toBe($netAmount)
            ->and($orgExchange)->not->toBeNull()
            ->and((float) $stockDelivery->org_exchange)->toBe((float) $orgExchange)
            ->and((float) $stockDelivery->items()->first()->org_net_amount)->toBe(round($netAmount * $orgExchange, 2));

        $zeroedLine->delete();
    });

    test('an order line made of several stocks is shared on the mirror delivery by the seller SKO value, not by SKOs', function () {
        $deliveryNoteItem = fn (int $id, int $transactionId, float $skos, ?float $skoValue) => (new DeliveryNoteItem(['transaction_id' => $transactionId, 'quantity_required' => $skos]))
            ->forceFill(['id' => $id])
            ->setRelation('orgStock', new OrgStock(['sku_value' => $skoValue]));

        $shares = StorePartnerStockDeliveryFromDeliveryNote::make()->transactionShares(collect([
            $deliveryNoteItem(1, 10, 30, 7.69),
            $deliveryNoteItem(2, 10, 1200, 0.07),
            $deliveryNoteItem(3, 20, 4, null),
            $deliveryNoteItem(4, 20, 12, 0.5),
        ]));

        expect(round($shares[1] * 691.74, 2))->toBe(507.10)
            ->and(round($shares[2] * 691.74, 2))->toBe(184.64)
            ->and($shares[3])->toBe(0.25)
            ->and($shares[4])->toBe(0.75);
    });

    test('the batches the seller picked arrive filled in on the buyer goods in, in the buyer SKOs', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $packedIn       = $sellerOrgStock->packed_in;
        $sellerOrgStock->update(['packed_in' => 4]);

        $item  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 12]);
        $order = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]])['orders'][0];

        $stockDelivery    = SendPartnerOrderToWarehouse::make()->action($order);
        $deliveryNote     = $order->deliveryNotes()->first();
        $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->first();

        $warehouse        = $seller->warehouses()->first();
        $location         = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $locationOrgStock = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $location, []);
        $sellerBatch      = \App\Actions\Dispatching\BatchCode\StoreBatchCode::make()->action($warehouse, [
            'code' => 'TEA-'.uniqid(), 'expiry_date' => '2027-05-31', 'org_stock_id' => $sellerOrgStock->id,
        ]);
        \App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement::make()->action($sellerOrgStock, $location, [
            'type' => \App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum::ADJUSTMENT, 'quantity' => 5, 'batches' => [['batch_code_id' => $sellerBatch->id, 'quantity' => 5]],
        ]);

        \App\Actions\Dispatching\Picking\StorePicking::make()->action($deliveryNoteItem, $this->adminGuest->getUser(), [
            'picker_user_id'        => $this->adminGuest->getUser()->id,
            'location_org_stock_id' => $locationOrgStock->id,
            'quantity'              => 2,
        ]);
        \App\Actions\Dispatching\Picking\StorePickingOrgStockMovement::run($deliveryNoteItem->pickings()->first()->id, $this->adminGuest->getUser()->id);
        $deliveryNoteItem->update(['quantity_dispatched' => 2]);
        SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());

        $buyerItem    = $stockDelivery->items()->first()->refresh();
        $buyerBatches = $buyerItem->batches()->with('batchCode')->get();
        expect($buyerBatches)->toHaveCount(1)
            ->and($buyerBatches->first()->batchCode->code)->toBe($sellerBatch->code)
            ->and($buyerBatches->first()->batchCode->expiry_date->toDateString())->toBe('2027-05-31')
            ->and($buyerBatches->first()->batchCode->organisation_id)->toBe($buyerItem->organisation_id)
            ->and((float) $buyerBatches->first()->quantity)->toEqualWithDelta(8 / $buyerItem->unitsPerSko(), 0.0001);

        DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
        $sellerOrgStock->update(['packed_in' => $packedIn]);
    });

    test('a manufacturing hub takes its partners orders only from their shopping list', function () {
        $seller = $this->orgPartner->partner;
        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 6]);
        $order  = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]])['orders'][0];
        $transaction = $item->refresh()->transaction;

        $middleware = new \App\Http\Middleware\EnsureHubPartnerOrderFromShoppingList();
        $through    = function (string $routeName, array $parameters, array $input = []) use ($middleware) {
            $request = \Illuminate\Http\Request::create('/hub-partner-order', 'PATCH', $input);
            $route   = (new \Illuminate\Routing\Route('PATCH', '/hub-partner-order', []))->name($routeName)->bind($request);
            foreach ($parameters as $name => $value) {
                $route->setParameter($name, $value);
            }
            $request->setRouteResolver(fn () => $route);

            return $middleware->handle($request, fn () => 'passed');
        };

        $quantity = (float) $transaction->quantity_ordered;

        expect(fn () => $through('grp.models.customer.submitted_order.store', ['customer' => $order->customer]))->toThrow(ValidationException::class)
            ->and(fn () => $through('grp.models.customer.order.store', ['customer' => $order->customer]))->toThrow(ValidationException::class)
            ->and(fn () => $through('grp.models.order.transaction.store', ['order' => $order]))->toThrow(ValidationException::class)
            ->and(fn () => $through('grp.models.order.follow_up.store', ['order' => $order]))->toThrow(ValidationException::class)
            ->and($through('grp.models.transaction.update_quantity_ordered', ['transaction' => $transaction], ['quantity_ordered' => $quantity]))->toBe('passed')
            ->and(fn () => $through('grp.models.transaction.update_quantity_ordered', ['transaction' => $transaction], ['quantity_ordered' => $quantity + 1]))->toThrow(ValidationException::class)
            ->and(fn () => $through('grp.models.order.modification.save', ['order' => $order], ['transactions' => [$transaction->id => ['newQty' => $quantity + 1]]]))->toThrow(ValidationException::class)
            ->and($through('grp.models.transaction.update_quantity_ordered', ['transaction' => $transaction], ['quantity_ordered' => $quantity - 1]))->toBe('passed')
            ->and($through('grp.models.order.modification.save', ['order' => $order], ['transactions' => [$transaction->id => ['newQty' => 0]]]))->toBe('passed')
            ->and($through('grp.models.transaction.delete', ['transaction' => $transaction]))->toBe('passed')
            ->and($through('grp.models.order.update', ['order' => $order], ['internal_notes' => 'fragile']))->toBe('passed');

        $seller->update(['is_manufacturing_hub' => false]);
        expect(fn () => $through('grp.models.order.transaction.store', ['order' => $order->refresh()]))->toThrow(ValidationException::class);
        $seller->update(['is_manufacturing_hub' => true]);
    });

    test('an order keyed by hand on the partner old customer is refused, and one already dispatched gets its stock delivery', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $seller->update(['is_manufacturing_hub' => false]);

        $item         = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 2]);
        $order        = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]])['orders'][0];
        $oldCustomer  = $order->customer->replicate(['ulid', 'slug', 'reference']);
        $oldCustomer->as_organisation_id = null;
        $oldCustomer->save();
        $orgPartnerDataBefore = $this->orgPartner->data;
        $this->orgPartner->update(['data' => array_merge((array) $orgPartnerDataBefore, ['intercompany_customers' => [$oldCustomer->id]])]);

        expect(StoreOrder::isPartnerBuyingFromHub($oldCustomer, $oldCustomer->shop))->toBeTrue();

        SubmitOrder::make()->action($order->refresh());
        $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), [], releaseFromGate: true);
        $order->update(['customer_id' => $oldCustomer->id]);

        expect(StorePartnerStockDeliveryFromDeliveryNote::make()->resolveOrgPartner($deliveryNote->refresh())?->id)->toBe($this->orgPartner->id);

        $this->orgPartner->update(['data' => $orgPartnerDataBefore]);
        $seller->update(['is_manufacturing_hub' => true]);
    });

    test('an order locked in Aurora at the move to aiku gets its stock delivery, dispatched and settling its purchase order, when Aurora dispatches it', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }
        $seller->update(['is_manufacturing_hub' => false]);

        $item  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 2]);
        $order = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]])['orders'][0];
        SubmitOrder::make()->action($order->refresh());
        $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), [], releaseFromGate: true);
        $order->update(['source_id' => $seller->id.':'.uniqid()]);
        $deliveryNote->update(['source_id' => $seller->id.':'.uniqid()]);

        expect(StorePartnerStockDeliveryFromDeliveryNote::make()->resolveOrgPartner($deliveryNote->refresh()))->toBeNull();

        $order->update(['handled_in_aurora' => true]);
        $notSentOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->organisation, StoreStock::make()->action($this->group, Stock::factory()->definition()));
        $purchaseOrder   = StorePurchaseOrder::make()->action($this->orgPartner->refresh(), []);
        $sentLine        = StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $this->buyerOrgStock, ['quantity_ordered' => 1000]);
        $notSentLine     = StorePurchaseOrderTransaction::make()->action($purchaseOrder, null, $notSentOrgStock, ['quantity_ordered' => 5]);
        $purchaseOrder->update(['reference' => 'AWA'.uniqid().'-'.$order->reference, 'state' => PurchaseOrderStateEnum::CONFIRMED]);
        $purchaseOrder->purchaseOrderTransactions()->update(['state' => PurchaseOrderTransactionStateEnum::CONFIRMED]);
        $deliveryNote->update(['state' => DeliveryNoteStateEnum::DISPATCHED, 'dispatched_at' => now()]);
        $deliveryNote->deliveryNoteItems()->update(['quantity_dispatched' => DB::raw('quantity_required')]);

        $stockDelivery = SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());

        expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
            ->and($stockDelivery->delivery_note_id)->toBe($deliveryNote->id)
            ->and($stockDelivery->purchaseOrders()->pluck('purchase_orders.id')->all())->toBe([$purchaseOrder->id])
            ->and((float) $stockDelivery->items()->sum('net_amount'))->toBeGreaterThan(0.0)
            ->and(StorePartnerStockDeliveryFromDeliveryNote::run($deliveryNote))->toBeNull();

        ReceiveStockDelivery::make()->action($stockDelivery->refresh());

        expect($sentLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::SETTLED)
            ->and($notSentLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CANCELLED)
            ->and($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SETTLED);

        $seller->update(['is_manufacturing_hub' => true]);
    });

    test('send partner order to warehouse rejects non-creating order', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 4,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        SendPartnerOrderToWarehouse::make()->action($order);

        expect(fn () => SendPartnerOrderToWarehouse::make()->action($order->refresh()))
            ->toThrow(ValidationException::class);
    });

    test('mirror stock delivery follows delivery note dispatch', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 8,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        $stockDelivery = SendPartnerOrderToWarehouse::make()->action($order);
        $deliveryNote  = $order->deliveryNotes()->first();

        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $deliveryNoteItem->update(['quantity_dispatched' => $deliveryNoteItem->quantity_required]);
        }

        SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());
        $stockDelivery->refresh();

        expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
            ->and($stockDelivery->dispatched_at)->not->toBeNull()
            ->and($stockDelivery->items()->first()->state)->toBe(StockDeliveryItemStateEnum::DISPATCHED);

        DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
    });

    test('mirror stock delivery follows a cancelled and resent delivery note', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 6,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        $staleStockDelivery = SendPartnerOrderToWarehouse::make()->action($order);
        CancelDeliveryNote::make()->action($order->deliveryNotes()->first(), null);

        expect(StockDelivery::find($staleStockDelivery->id))->toBeNull();

        $resentDeliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), [], releaseFromGate: true);
        foreach ($resentDeliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $deliveryNoteItem->update(['quantity_dispatched' => $deliveryNoteItem->quantity_required]);
        }

        $stockDelivery = SyncPartnerStockDeliveryOnDispatch::run($resentDeliveryNote->refresh());

        expect($stockDelivery)->not->toBeNull()
            ->and($stockDelivery->delivery_note_id)->toBe($resentDeliveryNote->id)
            ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
            ->and($stockDelivery->items()->first()->org_stock_id)->toBe($this->buyerOrgStock->id);

        DB::table('delivery_note_items')->where('delivery_note_id', $resentDeliveryNote->id)->update(['quantity_dispatched' => 0]);
    });

    test('an order the seller enters by hand for the partner customer gets a mirror stock delivery on dispatch', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 4,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];
        $order->update(['sales_channel_id' => null]);
        SubmitOrder::make()->action($order->refresh());

        $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), [], releaseFromGate: true);
        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $deliveryNoteItem->update(['quantity_dispatched' => $deliveryNoteItem->quantity_required]);
        }

        $stockDelivery = SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());

        expect($stockDelivery)->not->toBeNull()
            ->and($stockDelivery->organisation_id)->toBe($this->orgPartner->organisation_id)
            ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED);

        DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
    });

    test('an order fetched from aurora gets no mirror stock delivery on dispatch', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 2]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];
        SubmitOrder::make()->action($order->refresh());
        $deliveryNote = SendOrderToWarehouse::make()->action($order->refresh(), [], releaseFromGate: true);
        $order->update(['source_id' => '4:999999']);

        expect(SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh()))->toBeNull();
    });

    test('a partner purchase order raised after the partner moved to aiku can not create its own stock delivery', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
        $partnerData = $this->orgPartner->data;
        data_set($partnerData, 'intercompany_customers', [$this->sellerShop->id => 1]);
        $this->orgPartner->update(['data' => $partnerData]);
        $this->sellerShop->update(['migrated_to_aiku_on' => now()->subDays(10)]);
        $this->orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->delete();

        $purchaseOrder = StorePurchaseOrder::make()->action($this->orgPartner, PurchaseOrder::factory()->definition());
        $purchaseOrder->update(['state' => PurchaseOrderStateEnum::CONFIRMED]);

        expect(fn () => StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh()))
            ->toThrow(ValidationException::class);

        $purchaseOrder->update(['created_at' => now()->subDays(20)]);

        $refusal = '';
        try {
            StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh());
        } catch (ValidationException $exception) {
            $refusal = implode(' ', Arr::flatten($exception->errors()));
        }

        expect($refusal)->not->toContain('dispatches the order');
    });

    test('a SKO packed differently at the buyer and the seller can not be ordered between them', function () {
        $buyerOrganisation  = $this->buyerOrgStock->organisation;
        $sellerOrganisation = $this->agent->organisation;
        $orgPartner         = OrgPartner::where('organisation_id', $buyerOrganisation->id)->where('partner_id', $sellerOrganisation->id)->first()
            ?? StoreOrgPartner::make()->action($buyerOrganisation, $sellerOrganisation);
        $sellerOrgStock     = OrgStock::where('organisation_id', $sellerOrganisation->id)->where('stock_id', $this->buyerOrgStock->stock_id)->first()
            ?? \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($sellerOrganisation, $this->buyerOrgStock->stock);
        $sellerPackedIn     = $sellerOrgStock->packed_in;
        $guard              = EnsurePartnerOrderPackedInMatches::make();

        $sellerOrgStock->update(['packed_in' => null]);
        $this->buyerOrgStock->update(['packed_in' => 1]);
        expect($guard->mismatches($orgPartner, [$this->buyerOrgStock->stock_id]))->toBeEmpty();

        $sellerOrgStock->update(['packed_in' => 3]);

        expect($guard->mismatches($orgPartner, [$this->buyerOrgStock->stock_id])[0])->toContain($sellerOrgStock->code)
            ->and(fn () => submittedPartnerShoppingListItem($orgPartner, $sellerOrgStock, ['quantity' => 2]))->toThrow(ValidationException::class);

        $bulk = StorePartnerShoppingListItems::make()->action($orgPartner, [['org_stock_id' => $sellerOrgStock->id, 'quantity' => 2]]);

        expect($bulk['created'])->toBe(0)
            ->and($bulk['skipped'])->toHaveCount(1)
            ->and($bulk['skipped'][0]['reason'])->toContain('packed in');

        $sellerOrgStock->update(['packed_in' => $sellerPackedIn]);
    });

    test('buyer can not change a partner stock delivery before the seller dispatches it', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 3,
        ]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);

        $stockDelivery = SendPartnerOrderToWarehouse::make()->action($result['orders'][0]);

        expect($stockDelivery->isManagedByPartner())->toBeTrue();

        actingAs($this->adminGuest->getUser());

        $this->delete(route('grp.models.stock-delivery.delete', $stockDelivery->id))->assertSessionHasErrors('state');
        $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id))->assertSessionHasErrors('state');
        $this->patch(route('grp.models.stock-delivery.receive', $stockDelivery->id))->assertSessionHasErrors('state');
        $this->patch(route('grp.models.stock-delivery-item.ready-to-ship', $stockDelivery->items()->first()->id))->assertSessionHasErrors('state');

        expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::CONFIRMED)
            ->and($stockDelivery->trashed())->toBeFalse()
            ->and($stockDelivery->items()->first()->state)->toBe(StockDeliveryItemStateEnum::CONFIRMED);
    });

    test('a partner stock delivery set ready to ship by the buyer still follows the seller dispatch', function () {
        $seller = $this->orgPartner->partner;
        if (!$seller->warehouses()->exists()) {
            StoreWarehouse::make()->action($seller, Warehouse::factory()->definition());
        }

        $item   = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $result = CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        $order  = $result['orders'][0];

        $stockDelivery = SendPartnerOrderToWarehouse::make()->action($order);
        UpdateStateToReadyToShipStockDeliveryItem::make()->action($stockDelivery->items()->first());

        expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::READY_TO_SHIP);

        $deliveryNote = $order->deliveryNotes()->first();
        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $deliveryNoteItem->update(['quantity_dispatched' => $deliveryNoteItem->quantity_required]);
        }

        SyncPartnerStockDeliveryOnDispatch::run($deliveryNote->refresh());

        expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
            ->and($stockDelivery->items()->first()->state)->toBe(StockDeliveryItemStateEnum::DISPATCHED);

        DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
    });

    test('the reorder recommendation counts SKOs on order and on the partner list against the real lead time', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
        $orgStock = $this->buyerOrgStock;
        $orgStock->orgSupplierProducts()->detach();
        DB::table('partner_shopping_list_items')->where('org_stock_id', $orgStock->id)->update(['deleted_at' => now()]);
        DB::table('purchase_order_transactions')->where('org_stock_id', $orgStock->id)->update(['deleted_at' => now()]);
        DB::table('stock_delivery_items')->where('org_stock_id', $orgStock->id)->update(['deleted_at' => now()]);
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'packed_in' => 4, 'quantity_available' => 10, 'measured_lead_time_days' => null, 'estimated_lead_time_days' => 60]);
        $orgStock->stats->update(['demand_forecast' => ['from' => now()->toDateString(), 'weeks' => array_fill(0, 6, [7, 0])]]);

        $recommendation = function () use ($orgStock) {
            OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());

            return (float) $orgStock->stats->refresh()->recommended_order_quantity;
        };

        expect($recommendation())->toBe(80.0);

        $deliveryState = $this->stockDelivery->refresh()->state;
        DB::table('stock_deliveries')->where('id', $this->stockDelivery->id)->update(['state' => StockDeliveryStateEnum::DISPATCHED->value]);
        $onTheWay = DB::table('stock_delivery_items')->insertGetId([
            'group_id'          => $orgStock->group_id,
            'organisation_id'   => $orgStock->organisation_id,
            'stock_delivery_id' => $this->stockDelivery->id,
            'org_stock_id'      => $orgStock->id,
            'state'             => StockDeliveryItemStateEnum::DISPATCHED->value,
            'data'              => '{}',
            'unit_quantity'     => 80,
        ]);
        expect($recommendation())->toBe(60.0);
        DB::table('stock_delivery_items')->where('id', $onTheWay)->delete();
        DB::table('stock_deliveries')->where('id', $this->stockDelivery->id)->update(['state' => $deliveryState]);

        $purchaseOrder = $this->orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
            ?? StorePurchaseOrder::make()->action($this->orgPartner->refresh(), []);
        $onOrder = StorePurchaseOrderTransaction::make()->addPartnerOrgStock($purchaseOrder, $orgStock->fresh(), ['quantity_ordered' => 40]);
        expect($recommendation())->toBe(70.0);

        $orgStock->update(['estimated_lead_time_days' => null]);
        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        $this->orgPartner->update(['data' => ['shopping' => ['lead_time_days' => 60]]]);
        submittedPartnerShoppingListItem($this->orgPartner->refresh(), $orgStock->fresh(), ['quantity' => 5]);
        expect($recommendation())->toBe(65.0);

        $orgStock->update(['quantity_available' => 150]);
        expect($recommendation())->toBe(30.0);

        $orgStock->update(['quantity_available' => 200]);
        expect($recommendation())->toBe(0.0);

        $buckets  = App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets::make();
        $inDoomed = fn () => in_array($orgStock->stock_id, $buckets->stockIdsInBucket($this->orgPartner->refresh(), 'w1'));
        DB::table('stock_delivery_items')->where('org_stock_id', $orgStock->id)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        DB::table('purchase_order_transactions')->where('org_stock_id', $orgStock->id)->where('id', '!=', $onOrder->id)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $partnerLeadDays = App\Actions\Procurement\OrgPartner\GetPartnerLeadTime::run($this->orgPartner->refresh())['days'];
        $orgStock->stats->update(['days_of_cover' => max(1, intdiv($partnerLeadDays, 2)), 'predicted_daily_usage' => 0.01]);
        expect($inDoomed())->toBeFalse();

        DB::table('partner_shopping_list_items')->where('org_stock_id', $orgStock->id)->update(['deleted_at' => now()]);
        expect($inDoomed())->toBeTrue();

        $partnerOwnLead = DB::table('org_stocks')->where('organisation_id', $this->orgPartner->partner_id)->where('stock_id', $orgStock->stock_id)
            ->update(['measured_lead_time_days' => null, 'estimated_lead_time_days' => 5]);
        expect($partnerOwnLead)->toBeGreaterThanOrEqual(1)
            ->and($inDoomed())->toBeTrue();

        $onOrder->delete();
    });

    test('an agent-bought SKO with no lead time of its own is planned on the agent lead time', function () {
        $supplierProduct = $this->supplierProduct->refresh();
        $organisation    = $this->orgSupplierProduct->organisation;
        $orgAgent        = OrgAgent::where('organisation_id', $organisation->id)->where('agent_id', $supplierProduct->supplier->agent_id)->first()
            ?? StoreOrgAgent::make()->action($organisation, $supplierProduct->supplier->agent, []);
        $orgStock        = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($organisation, StoreStock::make()->action($this->group, Stock::factory()->definition()));
        \App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $this->orgSupplierProduct);

        $original = $supplierProduct->only(['measured_lead_time_days', 'estimated_lead_time_days', 'units_per_pack']);
        $supplierProduct->update(['measured_lead_time_days' => null, 'estimated_lead_time_days' => null, 'units_per_pack' => 1]);
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'packed_in' => 1, 'quantity_available' => 10, 'measured_lead_time_days' => null, 'estimated_lead_time_days' => null]);
        $orgStock->stats->update(['demand_forecast' => ['from' => now()->toDateString(), 'weeks' => array_fill(0, 6, [7, 0])]]);

        OrgStockHydrateOutOfStockForecast::run($orgStock->fresh());
        $agentDays = \App\Actions\Procurement\OrgAgent\GetAgentLeadTimes::run($orgAgent)['agent']['days'];
        $supplierProduct->update($original);

        expect((float) $orgStock->stats->refresh()->recommended_order_quantity)->toBe((float) ($agentDays + 30 - 10));
    });

    test('intercompany customer resolved by normalised name and mapping persisted', function () {
        StoreCustomer::make()->action($this->sellerShop, [
            'company_name'    => str_replace('.', '', $this->orgPartner->organisation->name).'.',
            'contact_name'    => 'Trade',
            'contact_address' => Address::factory()->definition(),
        ]);

        $this->orgPartner->update(['data' => []]);

        $resolved  = GetPartnerIntercompanyCustomer::run($this->orgPartner, $this->sellerShop->id);
        $normalise = fn (string $name) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));

        expect($resolved)->not->toBeNull()
            ->and($normalise($resolved->company_name))->toBe($normalise($this->orgPartner->organisation->name))
            ->and(data_get($this->orgPartner->refresh()->data, "intercompany_customers.{$this->sellerShop->id}"))->toBe($resolved->id);
    });

    test('intercompany customer with no address takes the buying organisation address', function () {
        $originalData = $this->orgPartner->data;
        $customer     = StoreCustomer::make()->action($this->sellerShop, [
            'company_name' => $this->orgPartner->organisation->name,
            'contact_name' => 'Trade',
        ], strict: false);
        $this->orgPartner->update(['data' => ['intercompany_customers' => [$this->sellerShop->id => $customer->id]]]);

        expect($customer->address_id)->toBeNull();

        $resolved = CherryPickPartnerShoppingListItems::make()->resolveIntercompanyCustomer($this->orgPartner->refresh(), $this->sellerShop);

        expect($resolved->id)->toBe($customer->id)
            ->and($resolved->address->country_id)->toBe($this->orgPartner->organisation->address->country_id)
            ->and($resolved->address->postal_code)->toBe($this->orgPartner->organisation->address->postal_code)
            ->and($resolved->delivery_address_id)->toBe($resolved->address_id)
            ->and($resolved->as_organisation_id)->toBe($this->orgPartner->organisation_id);

        $newSellerShop = StoreShop::run($this->sellerShop->organisation, Shop::factory()->definition());
        $created       = CherryPickPartnerShoppingListItems::make()->resolveIntercompanyCustomer($this->orgPartner->refresh(), $newSellerShop);

        expect($created->shop_id)->toBe($newSellerShop->id)
            ->and($created->as_organisation_id)->toBe($this->orgPartner->organisation_id);

        $this->orgPartner->update(['data' => $originalData]);
    });

    test('exclusive products for the intercompany customer appear in partner browse query', function () {
        $customer = GetPartnerIntercompanyCustomer::run($this->orgPartner, $this->sellerShop->id)
            ?? StoreCustomer::make()->action($this->sellerShop, [
                'company_name'    => $this->orgPartner->organisation->name,
                'contact_name'    => 'Trade',
                'contact_address' => Address::factory()->definition(),
            ]);
        $this->orgPartner->update([
            'data' => ['intercompany_customers' => [$this->sellerShop->id => $customer->id]],
        ]);

        $this->sellerProduct->update(['is_for_sale' => false]);
        DB::table('product_has_exclusive_customers')->insert([
            'product_id'  => $this->sellerProduct->id,
            'customer_id' => $customer->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $browse = ShowPartnerBrowse::make();
        (function () use ($customer) {
            $this->intercompanyCustomer = $customer;
        })->call($browse);

        $productIds = (fn () => $this->productsQuery($this->intercompanyCustomer->shop_id, null, null))
            ->call($browse)
            ->pluck('products.id');

        expect($productIds)->toContain($this->sellerProduct->id);

        (function () {
            $this->intercompanyCustomer = null;
        })->call($browse);

        $shopId          = $this->sellerShop->id;
        $withoutCustomer = (fn () => $this->productsQuery($shopId, null, null))
            ->call($browse)
            ->pluck('products.id');

        expect($withoutCustomer)->not->toContain($this->sellerProduct->id);
    });

    test('update and delete partner shopping list item via http', function () {
        $item = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, [
            'quantity' => 10,
        ]);

        actingAs($this->adminGuest->getUser());
        $this->patch(route('grp.org.procurement.org_partners.show.shopping_list.update', [$this->organisation->slug, $this->orgPartner->id, $item->id]), ['priority' => 'high'])
            ->assertRedirect();
        expect($item->refresh()->priority)->toBe(ShoppingListItemPriorityEnum::HIGH);

        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy', [$this->organisation->slug, $this->orgPartner->id, $item->id]))
            ->assertRedirect();

        expect(PartnerShoppingListItem::find($item->id))->toBeNull();
    });

    test('the buyer can withdraw a sent line the partner only set stock aside for, unless it is already in their bay', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalBay = $sellerPartner->goods_out_location_id;
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        try {
            $reserved = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 4]);
            $reserved->update(['pre_picked_at' => now()]);

            actingAs($this->adminGuest->getUser());
            $row = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
                ->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('id', $reserved->id);
            expect($row['is_withdrawable'])->toBeTrue()
                ->and($row['is_editable'])->toBeFalse();

            $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy', [$this->organisation->slug, $this->orgPartner->id, $reserved->id]))
                ->assertRedirect();
            expect(PartnerShoppingListItem::find($reserved->id))->toBeNull();

            $staged = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 4]);
            $staged->update(['pre_picked_at' => now()]);
            $bayStock = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $goodsOut, [
                'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
            ]);
            \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($bayStock, ['quantity' => 4]);

            $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy', [$this->organisation->slug, $this->orgPartner->id, $staged->id]))
                ->assertStatus(422);
            expect(PartnerShoppingListItem::find($staged->id))->not->toBeNull();

            $made = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 2]);
            $made->update(['preparing_at' => now()]);
            $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy', [$this->organisation->slug, $this->orgPartner->id, $made->id]))
                ->assertStatus(422);
        } finally {
            $sellerPartner->update(['goods_out_location_id' => $originalBay]);
        }
    });

    test('clearing the ongoing PO deletes its drafts and keeps the lines already sent', function () {
        $taken = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 7]);
        $taken->update(['state' => ShoppingListItemStateEnum::ORDERED]);
        $open  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);

        actingAs($this->adminGuest->getUser());
        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy_open', [$this->organisation->slug, $this->orgPartner->id]))
            ->assertRedirect();

        expect(PartnerShoppingListItem::find($draft->id))->toBeNull()
            ->and(PartnerShoppingListItem::find($open->id))->not->toBeNull()
            ->and(PartnerShoppingListItem::find($taken->id))->not->toBeNull();
    });

    test('pre-picked stock is walked to the partner goods out location and stops being available', function () {
        $seller = $this->orgPartner->partner;

        /* Its own shop and stock: the block's shared fixtures already carry pre-picked lines
           for the shared product, which would fold into the same staging row. */
        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);
        $sourceSlot->refresh();

        /* Reserving is what puts the line in front of the warehouse; it takes no money and
           can only promise stock we actually hold. */
        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
            ->action($seller, [['id' => $item->id]]);

        $task = collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))
            ->firstWhere('org_stock_id', $sellerOrgStock->id);
        expect($task)->not->toBeNull()
            ->and((float) $task['quantity_staged'])->toBe(0.0)
            ->and((float) $task['quantity_to_move'])->toBeGreaterThanOrEqual(5.0)
            ->and($task['from_locations'])->not->toBeEmpty();

        $toMove          = (float) $task['quantity_to_move'];
        $availableBefore = (float) $sellerOrgStock->fresh()->quantity_available;
        $inLocations     = (float) $sellerOrgStock->fresh()->quantity_in_locations;

        \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $sourceSlot, $sellerPartner, $toMove);

        $sellerOrgStock->refresh();
        expect((float) $sellerOrgStock->quantity_in_locations)->toBe($inLocations)
            ->and((float) $sellerOrgStock->quantity_available)->toBe($availableBefore - $toMove)
            ->and(collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))->firstWhere('org_stock_id', $sellerOrgStock->id))->toBeNull();
    });

    test('what a partner asked for that is in their bay or on the shelves becomes an order, the rest stays on the list', function () {
        $seller = $this->orgPartner->partner;

        $stock = StoreStock::make()->action($seller->group, Stock::factory()->definition());
        $sellerOrgStock = createOrgStocks($seller, [$stock])[0];
        $product = \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->sellerShop, array_merge(
            \App\Models\Catalogue\Product::factory()->definition(),
            [
                'state' => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE,
                'trade_units' => [['id' => $stock->tradeUnits()->firstOrFail()->id, 'quantity' => 1]],
            ]
        ));
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $baySlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $goodsOut, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($baySlot, ['quantity' => 3]);

        $inTheMaking = collect(\App\Actions\Production\PartnerShippingList\GetPartnerOrdersInTheMaking::run($seller))
            ->firstWhere('org_partner_id', $sellerPartner->id);
        $pricePerSko = (float) $product->price / (float) $product->orgStocks()->first()->pivot->quantity;

        expect($inTheMaking['location_code'])->toBe($goodsOut->code)
            ->and($inTheMaking['lines'])->toBe([['id' => $item->id, 'quantity' => 3.0]])
            ->and($inTheMaking['in_the_bay'])->toBe(['quantity' => 3.0, 'amount' => round(3 * $pricePerSko, 2)])
            ->and($inTheMaking['on_the_shelves']['quantity'])->toBe(0.0)
            ->and($inTheMaking['requested']['quantity'])->toBe(2.0);

        $incoming = collect(GetProductIncomingStock::make()->forOrgStocks([$buyerOrgStock->id]))->where('type', 'partner_request');
        expect($incoming->firstWhere('state_label', 'Ready to ship from '.$seller->name)['quantity'] ?? null)->toBe(3.0)
            ->and($incoming->contains(fn ($line) => str_starts_with($line['state_label'], 'In stock at')))->toBeFalse();

        $shelf     = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $shelfSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $shelf, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($shelfSlot, ['quantity' => 1]);

        $inTheMaking = collect(\App\Actions\Production\PartnerShippingList\GetPartnerOrdersInTheMaking::run($seller))
            ->firstWhere('org_partner_id', $sellerPartner->id);
        expect($inTheMaking['lines'])->toBe([['id' => $item->id, 'quantity' => 4.0]])
            ->and($inTheMaking['on_the_shelves']['quantity'])->toBe(1.0)
            ->and($inTheMaking['requested']['quantity'])->toBe(1.0);

        $orders = \App\Actions\Production\PartnerShippingList\StorePartnerOrderFromBay::make()->action($sellerPartner);

        $item->refresh();
        expect($orders)->toHaveCount(1)
            ->and($orders[0]->refresh()->state)->not->toBe(OrderStateEnum::CREATING)
            ->and((float) $orders[0]->net_amount)->toBe(round(4 * $pricePerSko * GetPartnerBuyingPriceFactor::run($this->orgPartner), 2))
            ->and($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) $item->quantity)->toBe(4.0)
            ->and((float) $item->children()->where('state', ShoppingListItemStateEnum::OPEN)->sum('quantity'))->toBe(1.0);

        $deliveryNoteItem = $orders[0]->deliveryNotes()->first()->deliveryNoteItems()->where('org_stock_id', $sellerOrgStock->id)->first();
        $firstLocationFor = fn (int $deliveryNoteItemId) => DB::table('location_org_stocks')
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->where('location_org_stocks.org_stock_id', $sellerOrgStock->id)
            ->orderByRaw(\App\Actions\Dispatching\PartnerStaging\PartnerBayPickingOrder::sql((string) $deliveryNoteItemId))
            ->orderBy('location_org_stocks.picking_priority')
            ->value('locations.id');

        expect($firstLocationFor($deliveryNoteItem->id))->toBe($goodsOut->id)
            ->and($firstLocationFor(0))->toBe($shelf->id);

        expect(fn () => \App\Actions\Production\PartnerShippingList\StorePartnerOrderFromBay::make()->action($sellerPartner))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    test('staging takes whatever was really moved: less leaves the rest to move, more clears the row', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);
        $sourceSlot->refresh();

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
            ->action($seller, [['id' => $item->id]]);

        $taskFor = fn () => collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))
            ->firstWhere('org_stock_id', $sellerOrgStock->id);
        $toMove  = (float) $taskFor()['quantity_to_move'];

        \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $sourceSlot->refresh(), $sellerPartner, 2);

        expect((float) $taskFor()['quantity_staged'])->toBe(2.0)
            ->and((float) $taskFor()['quantity_to_move'])->toBe($toMove - 2);

        $stage = \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make();
        $stage->action($warehouse, $sourceSlot->refresh(), $sellerPartner, $toMove + 5);

        expect($taskFor())->toBeNull()
            ->and($stage->notReserved)->toBe(7.0)
            ->and((float) $sourceSlot->refresh()->quantity)->toBe(500 - 2 - ($toMove + 5));
    });

    test('a pre-pick sent without a quantity takes what is free now, not what the page showed', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 50]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $shelf     = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $slot      = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $shelf, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($slot, ['quantity' => 5]);

        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($slot->refresh(), ['quantity' => 500]);

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);

        expect($item->refresh()->pre_picked_at)->not->toBeNull()
            ->and((float) $item->quantity)->toBe(50.0)
            ->and(PartnerShoppingListItem::where('parent_id', $item->id)->exists())->toBeFalse();
    });

    test('walking more than was pre-picked pre-picks the rest of the partner waiting lines', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
            ->action($seller, [['id' => $item->id, 'quantity' => 2]]);

        $remainder = PartnerShoppingListItem::where('parent_id', $item->id)->where('state', ShoppingListItemStateEnum::OPEN)->first();
        expect((float) $remainder->quantity)->toBe(3.0)
            ->and($remainder->pre_picked_at)->toBeNull();

        $stage = \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make();
        $stage->action($warehouse, $sourceSlot->refresh(), $sellerPartner, 5);

        expect($remainder->refresh()->pre_picked_at)->not->toBeNull()
            ->and($stage->notReserved)->toBe(0.0)
            ->and((float) $remainder->quantity)->toBe(3.0)
            ->and(collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))->firstWhere('org_stock_id', $sellerOrgStock->id))->toBeNull();
    });

    test('stock already staged in the main bay stays staged when the partner gets a cosmetic bay', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];
        $sellerOrgStock->stock->update(['is_cosmetic' => true]);

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse   = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source      = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $cosmeticBay = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);
        $cosmeticBay->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $originalSettings = $sellerPartner->only(['goods_out_location_id', 'split_cosmetics', 'cosmetic_goods_out_location_id']);

        try {
            $sellerPartner->update(['goods_out_location_id' => $goodsOut->id, 'split_cosmetics' => false, 'cosmetic_goods_out_location_id' => null]);

            $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
                'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
            ]);
            \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);

            \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
                ->action($seller, [['id' => $item->id]]);

            $taskFor = fn () => collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))
                ->firstWhere('org_stock_id', $sellerOrgStock->id);
            $toMove  = (float) $taskFor()['quantity_to_move'];

            \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $sourceSlot->refresh(), $sellerPartner, $toMove);
            expect($taskFor())->toBeNull();

            $sellerPartner->update(['split_cosmetics' => true, 'cosmetic_goods_out_location_id' => $cosmeticBay->id]);

            expect($taskFor())->toBeNull()
                ->and(\App\Actions\Dispatching\PartnerStaging\ReleasePartnerStagingTask::make()->action($warehouse, $sellerPartner->refresh(), $sellerOrgStock))->toBe(0.0);
        } finally {
            $sellerPartner->update($originalSettings);
            $sellerOrgStock->stock->update(['is_cosmetic' => false]);
        }
    });

    test('releasing a staging task keeps what was moved promised and sends the rest back to the lists', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
            ->action($seller, [['id' => $item->id]]);

        $taskFor = fn () => collect(\App\Actions\Dispatching\PartnerStaging\GetPartnerStagingTasks::run($warehouse))
            ->firstWhere('org_stock_id', $sellerOrgStock->id);
        $toMove  = (float) $taskFor()['quantity_to_move'];

        \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $sourceSlot->refresh(), $sellerPartner, 2);

        $released = \App\Actions\Dispatching\PartnerStaging\ReleasePartnerStagingTask::make()->action($warehouse, $sellerPartner, $sellerOrgStock);

        $lines = \App\Models\Procurement\PartnerShoppingListItem::where('partner_organisation_id', $seller->id)
            ->where('organisation_id', $this->orgPartner->organisation_id)
            ->where('stock_id', $sellerOrgStock->stock_id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->get();

        expect($released)->toBe($toMove - 2)
            ->and($taskFor())->toBeNull()
            ->and((float) $lines->whereNotNull('pre_picked_at')->sum('quantity'))->toBe(2.0)
            ->and((float) $lines->whereNull('pre_picked_at')->sum('quantity'))->toBe($toMove - 2);
    });

    test('released pre-picks go back into the line already waiting for the partner instead of splitting into new ones', function () {
        $seller = $this->orgPartner->partner;

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $prePicked = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()
            ->action($seller, [['id' => $prePicked->id]]);
        $prePicked->refresh();
        $prePickedQuantity = (float) $prePicked->quantity;

        $waiting = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 3]);

        $release = \App\Actions\Dispatching\PartnerStaging\ReleasePartnerStagingTask::make();
        $release->releaseUnstaged($sellerPartner, $sellerOrgStock, 1);
        $release->releaseUnstaged($sellerPartner, $sellerOrgStock, 1);

        $waitingLines = fn () => \App\Models\Procurement\PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $buyerOrgStock->id)->get();

        expect($waitingLines()->pluck('id')->all())->toBe([$waiting->id])
            ->and((float) $waiting->refresh()->quantity)->toBe(5.0)
            ->and((float) $prePicked->refresh()->quantity)->toBe($prePickedQuantity - 2);

        $release->releaseUnstaged($sellerPartner, $sellerOrgStock);

        expect($waitingLines()->pluck('id')->all())->toBe([$waiting->id])
            ->and((float) $waiting->refresh()->quantity)->toBe(3 + $prePickedQuantity)
            ->and(\App\Models\Procurement\PartnerShoppingListItem::find($prePicked->id))->toBeNull();
    });

    test('an audit that finds the hub shelf short sends the uncovered pre-pick to be produced', function () {
        $seller        = $this->orgPartner->partner;
        $wasHub        = $seller->is_manufacturing_hub;
        $seller->update(['is_manufacturing_hub' => true]);

        [, $product]    = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $sellerOrgStock = $product->orgStocks()->first();
        $sellerOrgStock->update([
            'quantity_in_submitted_orders'        => 0,
            'quantity_to_be_picked'               => 0,
            'quantity_reserved_for_pre_orders'    => 0,
            'source_quantity_in_submitted_orders' => 0,
            'source_quantity_to_be_picked'        => 0,
        ]);
        $buyerOrgStock  = createOrgStocks($this->orgPartner->organisation, [$sellerOrgStock->stock])[0];

        $item = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 5]);
        PartnerShoppingListItem::where('partner_organisation_id', $seller->id)->where('stock_id', $sellerOrgStock->stock_id)->where('id', '!=', $item->id)->forceDelete();

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $sourceSlot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        DB::table('location_org_stocks')->where('org_stock_id', $sellerOrgStock->id)->where('id', '!=', $sourceSlot->id)->update(['quantity' => 0]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot, ['quantity' => 500]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($sellerOrgStock->id);

        \App\Actions\Production\PartnerShippingList\PrePickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);
        \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $sourceSlot->refresh(), $sellerPartner, 2);

        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($sourceSlot->refresh(), ['quantity' => 1]);
        \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations::run($sellerOrgStock->id);

        $lines = \App\Models\Procurement\PartnerShoppingListItem::where('partner_organisation_id', $seller->id)
            ->where('stock_id', $sellerOrgStock->stock_id)
            ->where('id', '>=', $item->id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->select('*')
            ->selectRaw(\App\Models\Procurement\PartnerShoppingListItem::shortfallSql().' as shortfall')
            ->get();

        $released = $lines->whereNull('pre_picked_at');

        $seller->update(['is_manufacturing_hub' => $wasHub]);

        expect((float) $lines->whereNotNull('pre_picked_at')->sum('quantity'))->toBe(3.0)
            ->and((float) $released->sum('quantity'))->toBe(2.0)
            ->and((float) $released->sum('shortfall'))->toBe(2.0);
    });

    test('the cut-off runs the day before a partner shipment and moves the next one on', function () {
        $seller        = $this->orgPartner->partner;
        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $today = \Illuminate\Support\Carbon::parse('2026-10-08');
        $raise = \App\Actions\Production\PartnerShippingList\RaiseScheduledPartnerOrders::make();

        $sellerPartner->update(['next_shipment_on' => '2026-10-12', 'shipment_every_days' => 3]);
        expect($raise->handle($sellerPartner, $today))->toBeNull()
            ->and($sellerPartner->refresh()->next_shipment_on->toDateString())->toBe('2026-10-12');

        $sellerPartner->update(['next_shipment_on' => '2026-10-02']);
        $result = $raise->handle($sellerPartner, $today);
        expect($result['orders'])->toBe(0)
            ->and($result['skipped'])->not->toBeEmpty()
            ->and($sellerPartner->refresh()->next_shipment_on->toDateString())->toBe('2026-10-11');

        $sellerPartner->update(['next_shipment_on' => '2026-10-09', 'shipment_every_days' => null]);
        $raise->handle($sellerPartner, $today);
        expect($sellerPartner->refresh()->next_shipment_on)->toBeNull();
    });

    test('staging refuses a partner with no goods out location and more stock than the shelf holds', function () {
        $seller         = $this->orgPartner->partner;
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $source    = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $slot      = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $source, [
            'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
        ]);
        \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($slot, ['quantity' => 3]);
        $slot->refresh();

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => null]);

        expect(fn () => \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $slot, $sellerPartner, 1))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        $goodsOut = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        expect(fn () => \App\Actions\Dispatching\PartnerStaging\StagePartnerStock::make()->action($warehouse, $slot, $sellerPartner, 99))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    test('the goods out location is set by command and refuses a location that is not one', function () {
        $seller    = $this->orgPartner->partner;
        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $plain     = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $buyer = $this->orgPartner->organisation;

        $this->artisan('org:set_partner_goods_out_location', [
            'organisation' => $seller->slug,
            'partner'      => $buyer->slug,
            'location'     => $plain->code,
        ])->assertExitCode(1);
        expect($sellerPartner->fresh()->goods_out_location_id)->not->toBe($plain->id);

        $this->artisan('org:set_partner_goods_out_location', [
            'organisation' => $seller->slug,
            'partner'      => $buyer->slug,
            'location'     => $goodsOut->code,
        ])->assertExitCode(0);
        expect($sellerPartner->fresh()->goods_out_location_id)->toBe($goodsOut->id);

        $this->artisan('org:set_partner_goods_out_location', [
            'organisation' => $seller->slug,
            'partner'      => $buyer->slug,
        ])->assertExitCode(0);
        expect($sellerPartner->fresh()->goods_out_location_id)->toBeNull();
    });

    test('the pre-pick tab only shows once the organisation has a partner goods out location', function () {
        $seller    = $this->orgPartner->partner;
        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());

        /* Every shop organisation is partnered with every other one, so the tab must key off
           the goods out location, not off having partners. */
        \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)->update(['goods_out_location_id' => null]);

        actingAs($this->adminGuest->getUser());
        $tabsOf = fn () => get(route('grp.org.warehouses.show.dispatching.backlog', [$seller->slug, $warehouse->slug]))
            ->assertOk()->viewData('page')['props']['tabs']['navigation'];

        expect($tabsOf())->not->toHaveKey('partner_staging');

        $goodsOut = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);
        \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->update(['goods_out_location_id' => $goodsOut->id]);

        expect($tabsOf())->toHaveKey('partner_staging');
    });

    test('the buyer sees how far its line has got', function () {
        $seller = $this->orgPartner->partner;

        [, $product]   = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$product->orgStocks()->first()->stock])[0];
        $item          = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 4]);

        actingAs($this->adminGuest->getUser());
        $progressOf = function () use ($item) {
            $rows = get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
                ->assertOk()->viewData('page')['props']['data']['data'];

            return collect($rows)->firstWhere('id', $item->id)['progress']['label'] ?? null;
        };

        expect($progressOf())->toBe('Waiting for the partner');

        $item->update(['preparing_at' => now()]);
        expect($progressOf())->toBe('Queued to be made');

        $item->update(['preparing_at' => null, 'pre_picked_at' => now()]);
        expect($progressOf())->toBe('Picked from stock');

        $item->update(['pre_picked_at' => null]);

        CherryPickPartnerShoppingListItems::make()->action($seller, [['id' => $item->id]]);

        /* The block's shared intercompany basket may already carry a delivery note from an
           earlier test, which is a later stage than the one under test here. */
        \Illuminate\Support\Facades\DB::table('delivery_note_order')
            ->where('order_id', $item->fresh()->transaction->order_id)
            ->delete();

        expect($progressOf())->toBe('Pre-picked');
    });

    test('a line split into picked and to-make parts shows to the buyer as the one line they sent', function () {
        $seller = $this->orgPartner->partner;

        [, $product]   = createProduct(StoreShop::run($seller, Shop::factory()->definition()));
        $buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$product->orgStocks()->first()->stock])[0];
        $item          = submittedPartnerShoppingListItem($this->orgPartner, $buyerOrgStock, ['quantity' => 15]);
        $item->update(['pre_picked_at' => now()]);
        $rest = $item->replicate();
        $rest->fill(['quantity' => 75, 'parent_id' => $item->id, 'pre_picked_at' => null, 'preparing_at' => now()])->save();

        actingAs($this->adminGuest->getUser());
        $rows = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
            ->assertOk()->viewData('page')['props']['data']['data'])
            ->where('org_stock_id', $buyerOrgStock->id);

        expect($rows)->toHaveCount(1)
            ->and((float) $rows->first()['quantity'])->toBe(90.0)
            ->and(collect($rows->first()['progress_parts'])->map(fn ($part) => [$part['quantity'], $part['label']])->all())
            ->toBe([[15.0, 'Picked from stock'], [75.0, 'Queued to be made']]);
        expect(collect($rows->first()['progress_parts'][1]['details'])->pluck('label')->all())->toBe(['Requested', 'Sent to production']);

        $rest->update(['state' => ShoppingListItemStateEnum::ORDERED]);
        $orderedIds = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]).'?'.http_build_query(['filter' => ['state' => 'ordered']]))
            ->assertOk()->viewData('page')['props']['data']['data'])->pluck('id');
        expect($orderedIds)->toContain($item->id);

        $sentCount = fn () => collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
            ->viewData('page')['props']['pageHead']['subNavigation'])->firstWhere('label', 'Orders')['number'];
        $withPiece = $sentCount();
        $rest->forceDelete();

        expect($sentCount())->toBe($withPiece);
    });

    test('SKOs picked from a partner bay become one order through the partner list, and can not be ordered twice (HELP-3500)', function () {
        $seller = $this->orgPartner->partner;

        $warehouse = \App\Actions\Inventory\Warehouse\StoreWarehouse::make()->action($seller, \App\Models\Inventory\Warehouse::factory()->definition());
        $goodsOut  = \App\Actions\Inventory\Location\StoreLocation::make()->action($warehouse, \App\Models\Inventory\Location::factory()->definition());
        $goodsOut->update(['is_goods_out' => true]);

        $sellerPartner = \App\Models\Procurement\OrgPartner::where('organisation_id', $seller->id)
            ->where('partner_id', $this->orgPartner->organisation_id)
            ->first()
            ?? StoreOrgPartner::make()->action($seller, $this->orgPartner->organisation);
        $sellerPartner->update(['goods_out_location_id' => $goodsOut->id]);

        $inTheBay = function (int $quantity) use ($seller, $goodsOut) {
            $stock          = StoreStock::make()->action($seller->group, Stock::factory()->definition());
            $sellerOrgStock = createOrgStocks($seller, [$stock])[0];
            \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->sellerShop, array_merge(
                \App\Models\Catalogue\Product::factory()->definition(),
                [
                    'state'       => \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE,
                    'trade_units' => [['id' => $stock->tradeUnits()->firstOrFail()->id, 'quantity' => 1]],
                ]
            ));
            $slot = \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($sellerOrgStock, $goodsOut, [
                'type' => \App\Enums\Inventory\LocationStock\LocationStockTypeEnum::PICKING,
            ]);
            \App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock::make()->action($slot, ['quantity' => $quantity]);

            return $sellerOrgStock;
        };

        $asked    = $inTheBay(3);
        $notAsked = $inTheBay(1);
        $item     = submittedPartnerShoppingListItem($this->orgPartner, createOrgStocks($this->orgPartner->organisation, [$asked->stock])[0], ['quantity' => 2]);

        actingAs($this->adminGuest->getUser());

        $this->get(route('grp.org.warehouses.show.infrastructure.locations.show', [$seller->slug, $warehouse->slug, $goodsOut->slug, 'tab' => 'org_stocks']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('create_order_route.name', 'grp.models.location.create_order')->etc());

        $response = $this->post(route('grp.models.location.create_order', $goodsOut->id), ['org_stock_ids' => [$asked->id, $notAsked->id]]);

        $item->refresh();
        $order        = $item->transaction->order;
        $notAskedLine = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('stock_id', $notAsked->stock_id)->first();

        $response->assertRedirect(route('grp.org.shops.show.ordering.orders.show', [$seller->slug, $order->shop->slug, $order->slug]));
        expect($order->shop_id)->toBe($this->sellerShop->id)
            ->and($order->state)->toBe(\App\Enums\Ordering\Order\OrderStateEnum::IN_WAREHOUSE)
            ->and($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) $item->quantity)->toBe(3.0)
            ->and($notAskedLine->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) $notAskedLine->quantity)->toBe(1.0)
            ->and($notAskedLine->transaction->order_id)->toBe($order->id)
            ->and(PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::OPEN)->whereIn('stock_id', [$asked->stock_id, $notAsked->stock_id])->exists())->toBeFalse();

        $this->post(route('grp.models.location.create_order', $goodsOut->id), ['org_stock_ids' => [$asked->id]])
            ->assertSessionHasErrors('org_stock_ids');
        expect($order->transactions()->count())->toBe(2);

        $order->update(['state' => \App\Enums\Ordering\Order\OrderStateEnum::HANDLING]);
        $nextOrderStock = $inTheBay(2);
        $this->post(route('grp.models.location.create_order', $goodsOut->id), ['org_stock_ids' => [$nextOrderStock->id]])
            ->assertSessionHasErrors(['org_stock_ids' => __('Finish :references before creating a new order, so its stock is not mixed with the new one', ['references' => $order->reference])]);

        $order->update(['state' => \App\Enums\Ordering\Order\OrderStateEnum::FINALISED]);
        $this->post(route('grp.models.location.create_order', $goodsOut->id), ['org_stock_ids' => [$nextOrderStock->id]])
            ->assertSessionHasNoErrors();
    });

    test('the ai assistant fills the hub shopping list only when enrolled, logged and revertible', function () {
        $user = $this->adminGuest->getUser();
        $user->update(['can_use_mcp' => true, 'can_use_mcp_procurement' => false]);
        $arguments = [
            'organisation' => $this->orgPartner->organisation->slug,
            'lines'        => [['sko' => strtolower($this->buyerOrgStock->code), 'quantity' => 4]],
            'request_text' => 'ok add it to the shopping list',
        ];
        $draftLine = fn () => PartnerShoppingListItem::draftPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->first();

        App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\HubShoppingListTool::class, $arguments)
            ->assertHasErrors(['not enabled for this user']);
        expect($draftLine())->toBeNull();

        $user->update(['can_use_mcp_procurement' => true]);
        App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\HubShoppingListTool::class, $arguments)
            ->assertOk()->assertSee('change_log_id');

        $mcpChange = App\Models\SysAdmin\McpChange::latest('id')->first();
        expect((float) $draftLine()->quantity)->toBe(4.0)
            ->and($mcpChange->type)->toBe(App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PARTNER_SHOPPING_LIST)
            ->and($mcpChange->data['after_text'])->toBe($this->buyerOrgStock->stock->code.': 4');

        App\Actions\SysAdmin\McpChange\RevertMcpChange::run($mcpChange, $user);
        expect($draftLine())->toBeNull();

        App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\HubShoppingListTool::class, [...$arguments, 'lines' => [['sko' => 'NOPE-999', 'quantity' => 1]]])
            ->assertHasErrors(['NOPE-999']);
    });

    test('only users enrolled to place orders let the ai assistant submit the hub basket and change sent lines', function () {
        $user         = $this->adminGuest->getUser();
        $organisation = $this->orgPartner->organisation;
        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        $user->update(['can_use_mcp' => true, 'can_use_mcp_procurement' => true, 'can_use_mcp_place_orders' => false]);
        $tool      = fn (array $arguments) => App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\HubShoppingListTool::class, ['organisation' => $organisation->slug, 'request_text' => 'add it and submit', ...$arguments]);
        $line      = fn (ShoppingListItemStateEnum $state) => PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('org_stock_id', $this->buyerOrgStock->id)->where('state', $state)->first();
        $addAndSubmit = ['lines' => [['sko' => $this->buyerOrgStock->code, 'quantity' => 4]], 'submit' => true];

        $tool($addAndSubmit)->assertHasErrors(['Placing orders is not enabled']);
        expect($line(ShoppingListItemStateEnum::DRAFT))->toBeNull();

        $user->update(['can_use_mcp_place_orders' => true]);
        $tool($addAndSubmit)->assertOk()->assertSee(['"submitted":1', 'order_log_id']);

        $placed = App\Models\SysAdmin\McpChange::latest('id')->first();
        expect($line(ShoppingListItemStateEnum::DRAFT))->toBeNull()
            ->and((float) $line(ShoppingListItemStateEnum::OPEN)->quantity)->toBe(4.0)
            ->and($placed->type)->toBe(App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PLACED_ORDER)
            ->and($placed->canBeRevertedBy($user))->toBeFalse()
            ->and(fn () => App\Actions\SysAdmin\McpChange\RevertMcpChange::run($placed, $user))->toThrow(ValidationException::class);

        $changeSent = ['sent_lines' => [['sko' => $this->buyerOrgStock->code, 'quantity' => 6]]];
        $tool($changeSent)->assertHasErrors(['Dangerous']);
        expect((float) $line(ShoppingListItemStateEnum::OPEN)->quantity)->toBe(4.0);

        $tool([...$changeSent, 'accept' => ['change_submitted']])->assertOk()->assertSee('"sent_lines_changed":1');
        expect((float) $line(ShoppingListItemStateEnum::OPEN)->refresh()->quantity)->toBe(6.0);

        $line(ShoppingListItemStateEnum::OPEN)->update(['preparing_at' => now()]);
        $tool([...$changeSent, 'accept' => ['change_submitted']])->assertOk()->assertSee('No sent line the hub has not started yet');


        $this->orgPartner->partner->update(['is_manufacturing_hub' => false]);
        $user->update(['can_use_mcp_place_orders' => false]);
        App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\PartnerRescueOrderTool::class, ['organisation' => $organisation->slug])
            ->assertOk()->assertSee('"partner":"'.$this->orgPartner->partner->code);
        App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\PartnerRescueOrderTool::class, ['organisation' => $organisation->slug, 'partner' => $this->orgPartner->partner->code, 'place' => true, 'request_text' => 'place the rescue order'])
            ->assertHasErrors(['Placing orders is not enabled']);
    });

    test('an ai assistant previews and places a supplier purchase order in whole cartons', function () {
        $user        = $this->adminGuest->getUser();
        $orgSupplier = $this->orgSupplier;
        $orgSupplier->update(['status' => true]);
        $this->orgSupplierProduct->update(['state' => 'active']);
        $this->supplierProduct->update(['units_per_carton' => 12, 'minimum_carton_order' => 2, 'cost' => 5, 'is_available' => true]);
        $this->orgSupplierProduct->update(['is_available' => true]);
        $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->update(['state' => PurchaseOrderStateEnum::CANCELLED]);
        if (OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $this->orgSupplierProduct->id)->doesntExist()) {
            AttachOrgSupplierProductToOrgStock::make()->action(OrgStock::where('organisation_id', $orgSupplier->organisation_id)->where('state', OrgStockStateEnum::ACTIVE)->orderBy('id')->firstOrFail(), $this->orgSupplierProduct);
        }
        $user->update(['can_use_mcp' => true, 'can_use_mcp_place_orders' => false]);
        $arguments = ['organisation' => $orgSupplier->organisation->slug, 'supplier' => $orgSupplier->supplier->code, 'lines' => [['code' => $this->supplierProduct->code, 'quantity' => 30]]];
        $tool      = fn (array $extra = []) => App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\SupplierPurchaseOrderTool::class, [...$arguments, ...$extra]);

        $tool()->assertOk()->assertSee(['"units":36', '"cartons":3', '"new_lines_total":180', '"supplier":"'.$orgSupplier->supplier->code.' (']);

        $otherSupplier = Supplier::where('id', '!=', $orgSupplier->supplier_id)->orderBy('id')->first() ?? StoreSupplier::make()->action($this->agent, Supplier::factory()->definition());
        $tool(['supplier' => $otherSupplier->slug])->assertDontSee('"supplier":"'.$orgSupplier->supplier->code.' (');

        $currencyId = $this->supplierProduct->currency_id;
        $this->supplierProduct->update(['currency_id' => Currency::where('id', '!=', $orgSupplier->supplier->currency_id)->orderBy('id')->value('id')]);
        $tool()->assertHasErrors(['priced in another currency']);
        $this->supplierProduct->update(['currency_id' => $currencyId]);
        $tool(['lines' => [['code' => $this->supplierProduct->code, 'quantity' => 5]]])->assertOk()->assertSee('"units":24');
        $tool(['lines' => [['code' => $this->supplierProduct->code, 'quantity' => 20], ['code' => $this->supplierProduct->code, 'quantity' => 20]]])->assertOk()->assertSee(['"units_asked":40', '"units":48']);
        $tool(['lines' => [['code' => 'NOPE-999', 'quantity' => 1]]])->assertHasErrors(['NOPE-999']);
        $this->orgSupplierProduct->update(['is_available' => false]);
        $tool()->assertHasErrors(['not an available product']);
        $this->orgSupplierProduct->update(['is_available' => true]);

        $place = ['place' => true, 'request_text' => 'order 30 from the supplier'];
        $tool($place)->assertHasErrors(['Placing orders is not enabled']);
        expect($orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->exists())->toBeFalse();

        $user->update(['can_use_mcp_place_orders' => true]);
        $tool($place)->assertOk()->assertSee('"state":"submitted"');

        $purchaseOrder = $orgSupplier->purchaseOrders()->latest('id')->first();
        $placed        = App\Models\SysAdmin\McpChange::latest('id')->first();
        expect($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
            ->and($purchaseOrder->buyer_id)->toBe($user->id)
            ->and((float) $purchaseOrder->purchaseOrderTransactions()->sole()->quantity_ordered)->toBe(36.0)
            ->and($placed->type)->toBe(App\Enums\SysAdmin\McpChange\McpChangeTypeEnum::PLACED_ORDER)
            ->and($placed->data['after_text'])->toContain($purchaseOrder->reference.': submitted 1 lines');

        $labels = fn (array $extra = []) => App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\PurchaseOrderLabelsTool::class, ['organisation' => $orgSupplier->organisation->code, 'purchase_order' => strtolower($purchaseOrder->reference), ...$extra]);
        $purchaseOrder->purchaseOrderTransactions()->sole()->historicSupplierProduct->update(['units_per_carton' => 12]);
        $labels()->assertOk()->assertSee(['"level":"carton"', '"units":36', '"labels":3', '"pages":3', 'purchase-order-labels']);
        $labels(['codes' => ['NOPE-999']])->assertHasErrors(['No line of']);
        $labels(['purchase_order' => 'NOPE-999'])->assertHasErrors(['has no purchase order']);

        $button = fn (array $levels) => $this->actingAs($user)->get(route('grp.org.procurement.purchase_orders.labels', ['organisation' => $orgSupplier->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug, ...$levels]))->assertRedirect()->headers->get('Location');
        expect($button(['sko' => 1, 'unit' => 1]))->toContain('purchase-order-labels/'.$purchaseOrder->id)->toContain('levels=sko%2Cunit')
            ->and($button([]))->toContain('levels=carton');

        $url = App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels::make()->url($purchaseOrder, ['carton']);
        auth()->logout();
        $pdf = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        expect(preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf))->toBe(3);
        $this->get(str_replace('levels=carton', 'levels=unit', $url))->assertForbidden();

        $purchaseOrder->purchaseOrderTransactions()->sole()->historicSupplierProduct->update(['units_per_carton' => 1]);
        $purchaseOrder->purchaseOrderTransactions()->sole()->update(['quantity_ordered' => 2001]);
        $labels()->assertHasErrors(['That is 2001 labels']);
        $this->get(App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels::make()->url($purchaseOrder, ['carton']))->assertStatus(422);

        $purchaseOrder->purchaseOrderTransactions()->sole()->historicSupplierProduct->update(['units_per_carton' => 0]);
        $labels()->assertHasErrors(['no units per carton']);
    });

    test('the ai planning rows cap the order at what sells before it expires, one year when shelf life is not recorded', function () {
        $user = $this->adminGuest->getUser();
        $this->buyerOrgStock->update(['quantity_available' => 10]);
        $this->buyerOrgStock->stats()->update(['predicted_daily_usage' => 2, 'days_of_cover' => 5, 'recommended_order_quantity' => 30]);
        $planning = fn () => App\Mcp\Servers\AikuServer::actingAs($user)->tool(App\Mcp\Tools\HubOrderPlanningTool::class, [
            'organisation' => $this->orgPartner->organisation->slug,
            'codes'        => [$this->buyerOrgStock->code],
        ])->assertOk();

        $planning()->assertSee(['"days_until_out_of_stock":5', '"shelf_life_days":365,"shelf_life_recorded":false,"max_order_before_expiry":720,"suggested_quantity":30']);

        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $production     = Production::where('organisation_id', $sellerOrgStock->organisation_id)->first()
            ?? StoreProduction::make()->action($sellerOrgStock->organisation, ['code' => 'PLNP', 'name' => 'Planning factory']);
        StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'PLN-'.$sellerOrgStock->id, 'name' => 'Artefact', 'org_stock_id' => $sellerOrgStock->id, 'shelf_life_days' => 10]);

        $planning()->assertSee('"shelf_life_days":10,"shelf_life_recorded":true,"max_order_before_expiry":10,"suggested_quantity":10');
    });

    test('partner delivery costing mirrors the refunds the partner makes for goods that did not arrive', function () {
        $customer = createCustomer($this->sellerShop);
        $invoiceData = [
            'group_id'        => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
            'shop_id'         => $customer->shop_id,
            'customer_id'     => $customer->id,
            'tax_category_id' => \App\Models\Helpers\TaxCategory::first()->id,
            'in_process'      => false,
        ];

        [$receivedStock, $missingStock] = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)->limit(2)->get();
        $stockDelivery = StoreStockDelivery::make()->action($this->orgPartner, [
            'reference' => 'PARTNER-REFUND-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
            'state'     => StockDeliveryStateEnum::PLACED,
        ], strict: false);
        $invoiceData['currency_id'] = $stockDelivery->currency_id;
        $invoice = \App\Models\Accounting\Invoice::factory()->create([...$invoiceData, 'net_amount' => 300]);
        $stockDelivery->update(['invoice_id' => $invoice->id]);
        StoreStockDeliveryItem::make()->action($stockDelivery, null, $receivedStock, [
            'unit_quantity' => 10, 'unit_quantity_checked' => 10, 'net_amount' => 100, 'state' => StockDeliveryItemStateEnum::PLACED,
        ], strict: false);
        StoreStockDeliveryItem::make()->action($stockDelivery, null, $missingStock, [
            'unit_quantity' => 50, 'unit_quantity_checked' => 0, 'net_amount' => 200, 'state' => StockDeliveryItemStateEnum::NOT_RECEIVED,
        ], strict: false);

        $partnerInvoice = fn () => $this->get(route('grp.org.procurement.stock_deliveries.show', [$stockDelivery->organisation->slug, $stockDelivery->slug]))
            ->assertOk()
            ->inertiaProps('costing.partner_invoice');

        expect($partnerInvoice())->toMatchArray(['reference' => $invoice->reference, 'refunded' => 0, 'missing_amount' => 200, 'to_refund' => 200]);

        \App\Models\Accounting\Invoice::factory()->create([...$invoiceData, 'type' => \App\Enums\Accounting\Invoice\InvoiceTypeEnum::REFUND, 'original_invoice_id' => $invoice->id, 'net_amount' => -150]);
        \App\Models\Accounting\Invoice::factory()->create([...$invoiceData, 'type' => \App\Enums\Accounting\Invoice\InvoiceTypeEnum::REFUND, 'original_invoice_id' => $invoice->id, 'net_amount' => -999, 'in_process' => true]);

        expect($partnerInvoice())->toMatchArray(['refunded' => 150, 'to_refund' => 50])
            ->and($partnerInvoice()['refunds'])->toHaveCount(1);
    });

    test('a stored line is a draft the seller can not see', function () {
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 4]);

        $result = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $draft->id]]);

        expect($draft->state)->toBe(ShoppingListItemStateEnum::DRAFT)
            ->and(PartnerShoppingListItem::openPartnerLineFor($this->orgPartner->id, $this->buyerOrgStock->id)->exists())->toBeFalse()
            ->and($result['picked'])->toBe(0)
            ->and(collect($result['skipped'])->pluck('id')->all())->toBe([$draft->id]);
    });

    test('submitting promotes the drafts to open and dates them from the submission', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 4]);

        $submittedAt = now()->addHours(2)->startOfSecond();
        $this->travelTo($submittedAt);
        $submitted = SubmitPartnerShoppingList::make()->action($this->orgPartner);
        $draft->refresh();
        $this->travelBack();

        expect($submitted)->toBe(1)
            ->and($draft->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($draft->created_at->toDateTimeString())->toBe($submittedAt->toDateTimeString());
    });

    test('one draft line can be submitted on its own, the rest stay on the ongoing PO', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();
        $otherOrgStock = createOrgStocks($this->orgPartner->organisation, [StoreStock::make()->action($this->orgPartner->organisation->group, Stock::factory()->definition())])[0];
        $sent          = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 4]);
        $kept          = StorePartnerShoppingListItem::make()->action($this->orgPartner, $otherOrgStock, ['quantity' => 2]);

        actingAs($this->adminGuest->getUser());
        post(route('grp.org.procurement.org_partners.show.shopping_list.submit_item', [$this->organisation->slug, $this->orgPartner->id, $sent->id]))
            ->assertRedirect();

        expect(PartnerShoppingListItem::whereKey($sent->id)->where('state', ShoppingListItemStateEnum::DRAFT)->exists())->toBeFalse()
            ->and($kept->refresh()->state)->toBe(ShoppingListItemStateEnum::DRAFT);
    });

    test('submitting adds a draft to the untouched open line of the same SKO', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();
        $open  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);

        expect(SubmitPartnerShoppingList::make()->action($this->orgPartner))->toBe(1)
            ->and((float) $open->refresh()->quantity)->toBe(8.0)
            ->and($open->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and(PartnerShoppingListItem::find($draft->id))->toBeNull();
    });

    test('submitting never adds a draft to an open line already pre-picked or in a job order', function () {
        $seller         = $this->orgPartner->partner;
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $production     = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $artefact = Artefact::where('production_id', $production->id)->where('org_stock_id', $sellerOrgStock->id)->first()
            ?? StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'TPA-'.$sellerOrgStock->id, 'name' => 'Artefact', 'org_stock_id' => $sellerOrgStock->id]);
        $employeeData = Employee::factory()->make(['organisation_id' => $seller->id])->toArray();
        $employeeData['worker_number']   = 'W'.rand(1000, 9999);
        $employeeData['alias']           = 'Alias '.rand(1000, 9999);
        $employeeData['type']            = EmployeeTypeEnum::EMPLOYEE;
        $employeeData['employment_type'] = EmploymentTypeEnum::FULL_TIME;
        $employeeData['state']           = EmployeeStateEnum::WORKING;
        AttachArtisan::make()->action($artefact, ['employee_id' => StoreEmployee::make()->action($seller, $employeeData)->id]);
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();

        $inJobOrder = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        StoreJobOrdersFromToProduceItems::make()->action($production, [$inJobOrder->id]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);
        SubmitPartnerShoppingList::make()->action($this->orgPartner);

        expect($inJobOrder->refresh()->job_order_id)->not->toBeNull()
            ->and((float) $inJobOrder->quantity)->toBe(5.0)
            ->and($draft->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN);

        $draft->update(['pre_picked_at' => now()]);
        $nextDraft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 2]);
        SubmitPartnerShoppingList::make()->action($this->orgPartner);

        expect((float) $draft->refresh()->quantity)->toBe(3.0)
            ->and($nextDraft->refresh()->state)->toBe(ShoppingListItemStateEnum::OPEN);
    });

    test('submitting with no drafts is refused', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();

        expect(fn () => SubmitPartnerShoppingList::make()->action($this->orgPartner))->toThrow(ValidationException::class);
    });

    test('submitting the list of a partner of another organisation is not found', function () {
        $anotherOrganisation = $this->agent->organisation;
        $foreignOrgPartner   = OrgPartner::where('organisation_id', $anotherOrganisation->id)->where('partner_id', $this->orgPartner->partner_id)->first()
            ?? StoreOrgPartner::make()->action($anotherOrganisation, $this->orgPartner->partner);
        $foreignDraft        = PartnerShoppingListItem::create([
            ...Arr::only(StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 1])->getAttributes(), ['group_id', 'org_stock_id', 'stock_id', 'quantity', 'priority']),
            'organisation_id'         => $anotherOrganisation->id,
            'org_partner_id'          => $foreignOrgPartner->id,
            'partner_organisation_id' => $foreignOrgPartner->partner_id,
            'state'                   => ShoppingListItemStateEnum::DRAFT,
        ]);

        actingAs($this->adminGuest->getUser());
        $this->post(route('grp.org.procurement.org_partners.show.shopping_list.submit', [$this->organisation->slug, $foreignOrgPartner->id]))
            ->assertNotFound();

        expect($foreignDraft->refresh()->state)->toBe(ShoppingListItemStateEnum::DRAFT);
    });

    test('preparing an order with a budget never goes over it once lines are raised to whole batches', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        $sellerOrgStock = $this->sellerProduct->orgStocks()->first();
        $sellerOrgStock->updateQuietly(['packed_in' => 1]);
        $this->buyerOrgStock->updateQuietly(['packed_in' => 1]);
        $production = Production::first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'BATCHSL', 'name' => 'Batch factory']);
        StoreArtefact::make()->action($production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'BATCH-BUDGET', 'name' => 'Batched budget artefact', 'recommended_batch_size' => 45])
            ->update(['org_stock_id' => $sellerOrgStock->id]);

        $cheapStock    = collect($this->stocks)->first(fn ($stock) => $stock->id !== $this->buyerOrgStock->stock_id);
        $cheapOrgStock = createOrgStocks($this->orgPartner->organisation, [$cheapStock])[0];
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->delete();

        $this->partialMock(GetPartnerStockCoverBuckets::class, fn ($mock) => $mock->shouldReceive('rescueLines')->andReturn([
            ['org_stock_id' => $this->buyerOrgStock->id, 'skos' => 10, 'quantity' => 10, 'cost' => 10.0],
            ['org_stock_id' => $cheapOrgStock->id, 'skos' => 5, 'quantity' => 5, 'cost' => 20.0],
        ]));

        $added  = PreparePartnerShoppingListOrder::make()->handle($this->orgPartner->refresh(), 50);
        $drafts = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->get()->keyBy('org_stock_id');

        expect($added)->toBe(1)
            ->and((float) $drafts->get($this->buyerOrgStock->id)?->quantity)->toBe(45.0)
            ->and($drafts->has($cheapOrgStock->id))->toBeFalse();
    });

    test('the hub production manager orders on behalf of a partner from Intervention', function () {
        Notification::fake();
        $seller = $this->orgPartner->partner;
        $seller->update(['is_manufacturing_hub' => true]);
        $production = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $buyer        = Organisation::where('code', 'prc2')->first()
            ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
        $buyerPartner = OrgPartner::where('organisation_id', $buyer->id)->where('partner_id', $seller->id)->first()
            ?? StoreOrgPartner::make()->action($buyer, $seller);
        $buyerOrgStock = createOrgStocks($buyer, [$this->buyerOrgStock->stock])[0];

        $this->partialMock(GetPartnerStockCoverBuckets::class, fn ($mock) => $mock->shouldReceive('rescueLines')->andReturn([
            ['org_stock_id' => $buyerOrgStock->id, 'skos' => 10, 'quantity' => 10, 'cost' => 10.0],
        ]));

        $user          = $this->adminGuest->getUser();
        $originalRoles = $user->roles->pluck('name')->toArray();
        actingAsUserWithRoles($user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName('production-admin', $production)]);
        $order = fn (OrgPartner $orgPartner) => $this->post(route('grp.org.productions.show.intervention.order', [$seller->slug, $production->slug, $orgPartner->id]));

        try {
            $this->withoutVite()->get(route('grp.org.productions.show.intervention.index', [$seller->slug, $production->slug]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Org/Production/Interventions')
                    ->where('can_order', true)
                    ->has('buyers', 1)
                    ->has('buyers.0', fn (AssertableInertia $card) => $card->where('id', $buyerPartner->id)->has('rescuable.buckets')->etc()));

            $order($buyerPartner)->assertSessionHasNoErrors()->assertRedirect();

            $draft = PartnerShoppingListItem::where('org_partner_id', $buyerPartner->id)->where('state', ShoppingListItemStateEnum::DRAFT)->first();
            expect($draft->added_by_user_id)->toBe($user->id)
                ->and($draft->suggested_by_hub)->toBeTrue()
                ->and($production->audits()->where('event', 'order_on_behalf')->exists())->toBeTrue()
                ->and($buyer->audits()->where('event', 'order_on_behalf')->exists())->toBeTrue();

            $draft->update(['quantity' => 3]);
            $order($buyerPartner)->assertSessionHasErrors('rescue');
            expect((float) $draft->refresh()->quantity)->toBe(3.0);

            $order($this->orgPartner)->assertNotFound();

            actingAsUserWithRoles($user, [\App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName('production-orchestrator', $production)]);
            $order($buyerPartner)->assertSessionHasErrors('rescue');

            actingAsUserWithRoles($user, []);
            $order($buyerPartner)->assertForbidden();
        } finally {
            actingAsUserWithRoles($user, $originalRoles);
        }

        $ours = StorePartnerShoppingListItem::make()->action($buyerPartner, createOrgStocks($buyer, [collect($this->stocks)->first(fn ($stock) => $stock->id !== $buyerOrgStock->stock_id)])[0], ['quantity' => 2]);
        $user->givePermissionTo("procurement.$buyer->id.edit");
        $listUrl = route('grp.org.procurement.org_partners.show.shopping_list.index', [$buyer->slug, $buyerPartner->id]);
        $this->get($listUrl.'?'.http_build_query(['filter' => ['origin' => 'hub']]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hubSuggestionsCount', 1)
                ->has('data.data', 1)
                ->where('data.data.0.id', $draft->id)
                ->where('data.data.0.suggested_by_hub', true)
                ->etc());

        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy_hub_suggestions', [$this->organisation->slug, $buyerPartner->id]))->assertNotFound();
        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy_hub_suggestions', [$buyer->slug, $buyerPartner->id]))->assertRedirect();
        expect(PartnerShoppingListItem::find($draft->id))->toBeNull()
            ->and(PartnerShoppingListItem::find($ours->id))->not->toBeNull();
    });

    test('over http drafts and sent lines the partner has not started can be changed or removed, started lines are the partner\'s', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
        $started = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 4]);
        $started->update(['preparing_at' => now()]);
        $waiting = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);
        $route = fn (string $name, PartnerShoppingListItem $item) => route("grp.org.procurement.org_partners.show.shopping_list.$name", [$this->organisation->slug, $this->orgPartner->id, $item->id]);

        actingAs($this->adminGuest->getUser());

        $sentRows = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
            ->assertOk()->viewData('page')['props']['data']['data'])->keyBy('id');
        expect($sentRows[$waiting->id]['is_editable'])->toBeTrue()
            ->and($sentRows[$started->id]['is_editable'])->toBeFalse();

        $otherPartnerOrganisation = Organisation::where('id', '!=', $this->organisation->id)->where('group_id', $this->organisation->group_id)->firstOrFail();
        $otherOrgPartner          = OrgPartner::where('organisation_id', $this->organisation->id)->where('partner_id', $otherPartnerOrganisation->id)->first()
            ?? StoreOrgPartner::make()->action($this->organisation, $otherPartnerOrganisation);
        $foreignUrl = fn (string $name) => route("grp.org.procurement.org_partners.show.shopping_list.$name", [$this->organisation->slug, $otherOrgPartner->id, $waiting->id]);
        $this->patch($foreignUrl('update'), ['quantity' => 1, 'break_batch' => true])->assertNotFound();
        $this->delete($foreignUrl('destroy'))->assertNotFound();

        $this->patch($route('update', $started), ['priority' => 'high'])->assertStatus(422);
        $this->delete($route('destroy', $started))->assertStatus(422);
        $this->patch($route('update', $waiting), ['priority' => 'high', 'quantity' => 2, 'break_batch' => true])->assertRedirect();
        $this->patch($route('update', $draft), ['priority' => 'high'])->assertRedirect();
        $this->delete($route('destroy', $draft))->assertRedirect();

        expect($started->refresh()->priority)->not->toBe(ShoppingListItemPriorityEnum::HIGH)
            ->and($waiting->refresh()->priority)->toBe(ShoppingListItemPriorityEnum::HIGH)
            ->and((float) $waiting->quantity)->toBe(2.0)
            ->and(PartnerShoppingListItem::find($draft->id))->toBeNull();

        $this->delete($route('destroy', $waiting))->assertRedirect();
        expect(PartnerShoppingListItem::find($waiting->id))->toBeNull();
    });

    test('the buyer can poke the partner production about a sent line, once an hour, until it is delivered', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
        Notification::fake();
        $seller     = $this->orgPartner->partner;
        $production = Production::where('organisation_id', $seller->id)->first()
            ?? StoreProduction::make()->action($seller, ['code' => 'TPRD', 'name' => 'To produce factory']);
        $poker     = $this->adminGuest->getUser();
        $recipient = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
        setPermissionsTeamId($this->organisation->group_id);
        $recipient->givePermissionTo("productions_operations.$production->id.orchestrate");

        $delivered = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 2]);
        $delivered->update(['state' => ShoppingListItemStateEnum::ORDERED]);
        $waiting = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        OrgStock::where('organisation_id', $seller->id)->where('stock_id', $this->buyerOrgStock->stock_id)->update(['quantity_available' => 0]);
        $poke = fn (PartnerShoppingListItem $item, ?OrgPartner $orgPartner = null) => $this->postJson(route('grp.org.procurement.org_partners.show.shopping_list.poke', [$this->organisation->slug, ($orgPartner ?? $this->orgPartner)->id, $item->id]));

        try {
            actingAs($poker);
            $sentRows = collect(get(route('grp.org.procurement.org_partners.show.shopping_list.sent', [$this->organisation->slug, $this->orgPartner->id]))
                ->assertOk()->viewData('page')['props']['data']['data'])->keyBy('id');
            expect($sentRows[$waiting->id]['can_be_poked'])->toBeTrue();

            $poke($delivered)->assertStatus(422);
            expect((int) $poke($waiting)->assertOk()->getContent())->toBeGreaterThanOrEqual(1);
            $poke($waiting)->assertStatus(429);
            expect($waiting->refresh()->poked_at)->not->toBeNull()
                ->and($waiting->poked_by_user_id)->toBe($poker->id);

            $board = collect(get(route('grp.org.productions.show.to_produce.list', [$seller->slug, $production->slug, 'perPage' => 1000]))->viewData('page')['props']['data']['data'])->keyBy('id');
            expect($board[$waiting->id]['poked_at'])->not->toBeNull();

            $waiting->update(['poked_at' => now()->subHours(2)]);
            $poke($waiting)->assertOk();

            Notification::assertSentToTimes($recipient, \App\Notifications\PartnerLinePokedNotification::class, 2);
            Notification::assertNotSentTo($poker, \App\Notifications\PartnerLinePokedNotification::class);
        } finally {
            $recipient->revokePermissionTo("productions_operations.$production->id.orchestrate");
        }
    });

    test('the ongoing PO lists the drafts and sent lists what was submitted', function () {
        $ordered = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 7]);
        $ordered->update(['state' => ShoppingListItemStateEnum::ORDERED]);
        $open  = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 5]);
        $draft = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);
        $ids   = fn (string $name) => collect(
            get(route("grp.org.procurement.org_partners.show.shopping_list.$name", [$this->organisation->slug, $this->orgPartner->id]))
                ->assertOk()->viewData('page')['props']['data']['data']
        )->pluck('id');

        actingAs($this->adminGuest->getUser());
        $ongoing = $ids('index');
        $sent    = $ids('sent');

        expect($ongoing)->toContain($draft->id)
            ->and($ongoing)->not->toContain($open->id)
            ->and($ongoing)->not->toContain($ordered->id)
            ->and($sent)->toContain($open->id)
            ->and($sent)->toContain($ordered->id)
            ->and($sent)->not->toContain($draft->id);
    });

    test('an ongoing PO line shows what is already sent to the partner at its stage, until it is dispatched', function () {
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
        $picked = submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 6]);
        $order  = CherryPickPartnerShoppingListItems::make()->action($this->orgPartner->partner, [['id' => $picked->id]])['orders'][0];
        submittedPartnerShoppingListItem($this->orgPartner, $this->buyerOrgStock, ['quantity' => 4]);
        $draft    = StorePartnerShoppingListItem::make()->action($this->orgPartner, $this->buyerOrgStock, ['quantity' => 3]);
        $packedIn = (float) ($this->buyerOrgStock->packed_in ?: 1);

        actingAs($this->adminGuest->getUser());
        $incoming = fn () => collect(collect(
            get(route('grp.org.procurement.org_partners.show.shopping_list.index', [$this->organisation->slug, $this->orgPartner->id]))
                ->assertOk()->viewData('page')['props']['data']['data']
        )->firstWhere('id', $draft->id)['other_open_purchase_orders'])->where('state', 'sent')->mapWithKeys(fn ($sent) => [$sent['stage'] => [$sent['reference'], (float) $sent['quantity_ordered']]])->all();

        expect($incoming())->toEqual([
            'Waiting for the partner' => [__('from :partner', ['partner' => $this->orgPartner->partner->name]), 4 * $packedIn],
            'Pre-picked'              => [$order->reference, 6 * $packedIn],
        ]);

        $order->update(['state' => OrderStateEnum::DISPATCHED]);

        expect($incoming())->toEqual([
            'Waiting for the partner' => [__('from :partner', ['partner' => $this->orgPartner->partner->name]), 4 * $packedIn],
        ]);
    });

    test('the old shopping list address goes to the ongoing PO', function () {
        actingAs($this->adminGuest->getUser());

        $this->get(route('grp.org.procurement.org_partners.show.shopping_list.legacy', [$this->organisation->slug, $this->orgPartner->id]))
            ->assertRedirect(route('grp.org.procurement.org_partners.show.shopping_list.index', [$this->organisation->slug, $this->orgPartner->id]));
    });

    test('removing a line with do not suggest again keeps the SKO out of prepared orders until unblocked', function () {
        $this->orgPartner->partner->update(['is_manufacturing_hub' => true]);
        PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)->forceDelete();
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        createOrgStocks($this->orgPartner->partner, [$stock])[0]->update(['state' => OrgStockStateEnum::ACTIVE]);
        $this->buyerOrgStock = createOrgStocks($this->orgPartner->organisation, [$stock])[0];
        $this->buyerOrgStock->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_available' => 0, 'health_rank' => HealthRankEnum::A, 'is_on_demand' => false, 'is_excluded_from_auto_ordering' => false]);
        $this->buyerOrgStock->stats()->updateOrCreate([], ['predicted_daily_usage' => 2, 'days_of_cover' => 0]);
        $orgPartner = $this->orgPartner->refresh();
        $rescued    = fn () => GetPartnerStockCoverBuckets::make()->rescueItems($orgPartner)->pluck('org_stock_id')->map(fn ($id) => (int) $id)->all();

        expect($rescued())->toContain($this->buyerOrgStock->id);

        $draft = StorePartnerShoppingListItem::make()->action($orgPartner, $this->buyerOrgStock, ['quantity' => 1]);
        actingAs($this->adminGuest->getUser());
        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.destroy', [$this->organisation->slug, $orgPartner->id, $draft->id]), ['stop_suggesting' => true])
            ->assertRedirect();

        expect(PartnerShoppingListItem::find($draft->id))->toBeNull()
            ->and($this->buyerOrgStock->refresh()->is_excluded_from_auto_ordering)->toBeTrue()
            ->and($rescued())->not->toContain($this->buyerOrgStock->id);

        $buckets   = GetPartnerStockCoverBuckets::make();
        $leftOut   = $buckets->leftOut($orgPartner, GetPartnerLeadTime::run($orgPartner)['days']);
        $cards     = collect($buckets->handle($orgPartner)['buckets'])->pluck('count', 'bucket');
        $rescuable = collect($buckets->rescuable($orgPartner, 0)['buckets'])->pluck('count', 'bucket');
        expect($leftOut['out']['excluded'] ?? 0)->toBeGreaterThanOrEqual(1)
            ->and($rescuable->map(fn ($count, $bucket) => $count + array_sum($leftOut[$bucket] ?? []))->all())
            ->toBe($cards->only($rescuable->keys())->all());

        $this->withoutVite()->get(route('grp.org.procurement.org_partners.show.shopping_list.blocked', [$this->organisation->slug, $orgPartner->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Procurement/PartnerBlockedOrgStocks')
                ->where('data.data', fn ($rows) => collect($rows)->pluck('id')->contains($this->buyerOrgStock->id)));

        $this->delete(route('grp.org.procurement.org_partners.show.shopping_list.unblock', [$this->organisation->slug, $orgPartner->id, $this->buyerOrgStock->id]))
            ->assertRedirect();

        expect($this->buyerOrgStock->refresh()->is_excluded_from_auto_ordering)->toBeFalse()
            ->and($rescued())->toContain($this->buyerOrgStock->id);
    });
});
