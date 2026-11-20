<?php

use App\Actions\Goods\Stock\StoreStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit;
use App\Models\SysAdmin\User;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\StartStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsPlaced;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\HumanResources\Employee\StoreEmployee;
use Illuminate\Support\Str;
use App\Models\HumanResources\JobPosition;
use App\Actions\SysAdmin\User\StoreUser;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\Catalogue\Shop;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Goods\Stock;
use App\Models\SysAdmin\Organisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * A fresh supplier behind the agent, as the org supplier the organisation buys it through, with one product it can order.
 *
 * @return array{0: OrgSupplier, 1: OrgSupplierProduct}
 */
function createAgentOrgSupplierWithProduct(object $test, ?OrgAgent $orgAgent = null): array
{
    $orgAgent ??= $test->orgAgent;

    $supplier    = StoreSupplier::make()->action($orgAgent->agent, Supplier::factory()->definition());
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $orgAgent->id)->firstOrFail();

    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'AGT-'.Str::upper(Str::random(8)),
        'name'             => 'Agent supplier product',
        'cost'             => 100,
        'stock_id'         => $test->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    return [$orgSupplier->refresh(), $orgSupplierProduct];
}

/**
 * @param  list<array{0: string, 1: int, 2: int, 3: bool}>  $lines  code, units per carton, cartons, code filled green (in China's warehouse)
 */
function writeChinaContainerList(string $name, array $lines): string
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['Barcode', 'Code', 'Description', 'RMB', 'Packing', 'Carton Qty', '', 'ORDER']);
    foreach ($lines as $index => [$code, $unitsPerCarton, $cartons, $inWarehouse]) {
        $row = $index + 2;
        $sheet->fromArray(['', $code, '', '', '', $unitsPerCarton, '', $cartons], null, "A$row");
        if ($inWarehouse) {
            $sheet->getStyle("B$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
        }
    }
    $file = sys_get_temp_dir().'/'.$name;
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($file);

    return $file;
}

function createFreshOrgAgent(object $test): OrgAgent
{
    $agent = StoreAgent::make()->action($test->group, Agent::factory()->definition());

    return StoreOrgAgent::make()->action($test->orgAgent->organisation, $agent, []);
}


function createConfirmedAgentOrder(object $test, OrgSupplier $orgSupplier, OrgSupplierProduct $orgSupplierProduct, int $orgStockIndex, int $quantity): PurchaseOrder
{
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $test->orgStocks[$orgStockIndex],
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => $quantity])
    );

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());

    return UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());
}

function placeDeliveryUnderAgentOrder(StockDelivery $stockDelivery, PurchaseOrder $purchaseOrder): void
{
    $stockDelivery->update(['agent_id' => $purchaseOrder->agent_id, 'currency_id' => $purchaseOrder->currency_id]);
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);
}

function createPaidAspoDeposit(PurchaseOrder $purchaseOrder, array $modelData = []): \App\Models\SupplyChain\AspoDeposit
{
    $deposit = StoreAspoDeposit::make()->action($purchaseOrder, array_merge(['amount' => 100], $modelData));

    return \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);
}

function offeredDepositIds(StockDelivery $stockDelivery): array
{
    $method = new ReflectionMethod(\App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery::class, 'getDepositSettlement');
    $method->setAccessible(true);

    return collect($method->invoke(\App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery::make(), $stockDelivery, collect(), 0.0, 0.0)['available'])->pluck('id')->all();
}

function allowGoodsInOn(\App\Models\Inventory\Warehouse $warehouse, User $user): void
{
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_CLERK->value, $warehouse));
}

function createUnlinkedOrgStock($test): OrgStock
{
    $stock = StoreStock::make()->action($test->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

    return \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($test->organisation, $stock);
}

function createStockDeliveryWithItems($test, string $code, array $unitQuantities): StockDelivery
{
    $supplier    = StoreSupplier::make()->action(parent: $test->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $test->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => $code,
        'name'             => $code,
        'cost'             => 100,
        'stock_id'         => $test->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);

    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
        'reference' => $code,
        'date'      => date('Y-m-d'),
    ]);

    foreach ($unitQuantities as $index => $unitQuantity) {
        StoreStockDeliveryItem::make()->action(
            $stockDelivery,
            $supplierProduct->historicSupplierProduct,
            $test->orgStocks[$index],
            ['unit_quantity' => $unitQuantity]
        );
    }

    return $stockDelivery->refresh();
}

function agentPaymentsClerk(Organisation $agentOrganisation): User
{
    $existing = User::where('username', 'agent-pay-clerk')->first();
    if ($existing) {
        return $existing;
    }

    $orgAdmin = JobPosition::where('organisation_id', $agentOrganisation->id)->where('code', 'org-admin')->firstOrFail();
    $employee = StoreEmployee::make()->action($agentOrganisation, [
        'worker_number'   => 'agent-pay-clerk',
        'alias'           => 'agent-pay-clerk',
        'contact_name'    => 'Agent Payments Clerk',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
    ]);

    return StoreUser::make()->action($employee, [
        'username'       => 'agent-pay-clerk',
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
}

function createLocationOrgStockFor($test, StockDeliveryItem $stockDeliveryItem): LocationOrgStock
{
    $warehouse = Warehouse::where('organisation_id', $test->organisation->id)->first();

    if (!$warehouse) {
        $warehouse = Warehouse::factory()->create([
            'group_id'        => $test->group->id,
            'organisation_id' => $test->organisation->id,
            'address_id'      => Address::factory()->create(['group_id' => $test->group->id])->id,
        ]);
    }

    $location = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    return StoreLocationOrgStock::make()->action(OrgStock::find($stockDeliveryItem->org_stock_id), $location, [
        'type' => LocationStockTypeEnum::PICKING,
    ]);
}

function procurementAccountingClerk(\App\Models\SysAdmin\Organisation $organisation): \App\Models\SysAdmin\User
{
    $existing = \App\Models\SysAdmin\User::where('username', 'acc-clerk')->first();
    if ($existing) {
        return $existing;
    }

    $accCode  = JobPosition::where('organisation_id', $organisation->id)->where('code', 'acc-c')->firstOrFail();
    $employee = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'acc-clerk',
        'alias'           => 'acc-clerk',
        'contact_name'    => 'Accounting Clerk',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $accCode->slug, 'scopes' => []]],
    ]);

    return StoreUser::make()->action($employee, [
        'username'       => 'acc-clerk',
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
}

function orgStockOnSaleAndReceived(OrgStock $orgStock): OrgStock
{
    $shop = StoreShop::run($orgStock->organisation, Shop::factory()->definition());
    $shop->update(['state' => ShopStateEnum::OPEN]);
    [, $product] = createProduct($shop);
    $product->update(['is_for_sale' => true]);
    $product->orgStocks()->syncWithoutDetaching([$orgStock->id => ['quantity' => 1]]);
    $orgStock->stock->update(['state' => StockStateEnum::ACTIVE]);

    DB::table('org_stock_movements')->insert([
        'group_id'        => $orgStock->group_id,
        'organisation_id' => $orgStock->organisation_id,
        'warehouse_id'    => ($orgStock->organisation->warehouses()->oldest('id')->first() ?? createWarehouse())->id,
        'org_stock_id'    => $orgStock->id,
        'date'            => now()->subYear(),
        'class'           => OrgStockMovementClassEnum::MOVEMENT->value,
        'type'            => OrgStockMovementTypeEnum::PURCHASE->value,
        'flow'            => OrgStockMovementFlowEnum::IN->value,
        'quantity'        => 1,
        'org_amount'      => 0,
        'grp_amount'      => 0,
        'data'            => '{}',
    ]);

    return $orgStock;
}

function independentOrgSupplierFixture($test): array
{
    $supplier = StoreSupplier::make()->action(
        parent: $test->group,
        modelData: Supplier::factory()->definition()
    );

    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $test->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'                     => 'IND-'.$supplier->id,
        'name'                     => 'Independent supplier product',
        'cost'                     => 5,
        'stock_id'                 => $test->stock->id,
        'units_per_pack'           => 10,
        'units_per_carton'         => 100,
    ]);

    $supplierProduct->update(['estimated_lead_time_days' => 9]);

    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    return [$orgSupplier, $supplierProduct, $orgSupplierProduct];
}

function placedStockDeliveryWithTwoLines($test, string $code): StockDelivery
{
    $stockDelivery = createStockDeliveryWithItems($test, $code, [10, 20]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    foreach ($stockDelivery->items()->orderBy('id')->get() as $index => $item) {
        $item = UpdateStockDeliveryItem::make()->action($item, ['net_amount' => $index === 0 ? 100 : 300], strict: false);
        $item = SetStockDeliveryItemCheckedQuantity::make()->action($item->fresh(), ['unit_quantity_checked' => $item->unit_quantity]);
        SetStockDeliveryItemAsPlaced::make()->action($item, ['location_org_stock_id' => createLocationOrgStockFor($test, $item)->id]);
    }

    $stockDelivery = StartStockDeliveryCosting::make()->action($stockDelivery->fresh());
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'amount' => 400, 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'label' => 'Polymer surcharge', 'amount' => 80, 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, 'is_na' => true]);

    return $stockDelivery->fresh();
}

function agentOrganisationAdmin(Organisation $agentOrganisation, string $username, string $jobPositionCode = 'org-admin'): User
{
    $orgAdmin = JobPosition::where('organisation_id', $agentOrganisation->id)->where('code', $jobPositionCode)->firstOrFail();
    $employee = StoreEmployee::make()->action($agentOrganisation, [
        'worker_number'   => $username,
        'alias'           => $username,
        'contact_name'    => $username,
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
    ]);

    return StoreUser::make()->action($employee, [
        'username'       => $username,
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
}

function userWithJobPosition($test, string $jobPositionCode, string $username): User
{
    setPermissionsTeamId($test->group->id);

    $jobPosition = JobPosition::where('organisation_id', $test->organisation->id)->where('code', $jobPositionCode)->firstOrFail();
    $employee    = StoreEmployee::make()->action($test->organisation, [
        'worker_number'   => $username,
        'alias'           => $username,
        'contact_name'    => $username,
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $jobPosition->slug, 'scopes' => []]],
    ]);

    return StoreUser::make()->action($employee, [
        'username'       => $username,
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
}

function weighedPlacedStockDelivery($test, string $code, array $grams, array $packedIn = [1, 2]): StockDelivery
{
    $orgStocks = [];
    foreach ($grams as $index => $gross) {
        $stock       = StoreStock::make()->action($test->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
        $stock->update(['gross_weight' => $gross]);
        $orgStock    = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($test->organisation, $stock);
        $orgStock->update(['packed_in' => $packedIn[$index]]);
        $orgStocks[] = $orgStock;
    }

    $fixture = (object) [
        'group'        => $test->group,
        'organisation' => $test->organisation,
        'stocks'       => $test->stocks,
        'orgStocks'    => $orgStocks,
    ];

    return placedStockDeliveryWithTwoLines($fixture, $code);
}

function setShippingAndDuty(StockDelivery $stockDelivery, float $shipping, ?float $duty = null): void
{
    $shippingRow = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->first();
    UpdateStockDeliveryCost::make()->action($shippingRow, ['is_na' => false, 'amount' => $shipping, 'received_at' => now()]);

    if ($duty !== null) {
        StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'amount' => $duty, 'received_at' => now()]);
    }
}

function procurementGmailMessage(string $id, string $threadId, string $from, string $to, string $subject, array $labels = ['INBOX']): array
{
    return [
        'id'           => $id,
        'threadId'     => $threadId,
        'labelIds'     => $labels,
        'snippet'      => 'Snippet of '.$subject,
        'internalDate' => (string) now()->getTimestampMs(),
        'payload'      => [
            'mimeType' => 'multipart/mixed',
            'headers'  => [
                ['name' => 'From', 'value' => $from],
                ['name' => 'To', 'value' => $to],
                ['name' => 'Subject', 'value' => $subject],
            ],
            'parts'    => [
                ['mimeType' => 'text/plain', 'body' => ['data' => rtrim(strtr(base64_encode('Body of '.$subject), '+/', '-_'), '=')]],
                ['mimeType' => 'application/pdf', 'filename' => 'proforma.pdf', 'headers' => [['name' => 'Content-Disposition', 'value' => 'attachment; filename="proforma.pdf"']], 'body' => ['attachmentId' => 'att-1', 'size' => 2048]],
            ],
        ],
    ];
}

function stockDeliveryWithInvoiceInAnotherCurrency($test, string $token): array
{
    $organisationCurrency = $test->organisation->currency;
    $deliveryCurrency     = \App\Models\Helpers\Currency::where('id', '!=', $organisationCurrency->id)->where('id', '!=', $test->group->currency_id)->orderBy('id')->firstOrFail();

    $stockDelivery = createStockDeliveryWithItems($test, "INVCUR-$token", [10]);
    $stockDelivery->update(['state' => StockDeliveryStateEnum::BOOKED_IN]);
    $stockDelivery = StartStockDeliveryCosting::make()->action($stockDelivery->fresh());
    $stockDelivery->update(['currency_id' => $deliveryCurrency->id, 'org_exchange' => 0.01, 'grp_exchange' => 0.02]);
    $stockDelivery->items()->update([
        'net_amount'     => 9000,
        'cost_items'     => 9000,
        'cost_total'     => 9000,
        'org_exchange'   => 0.01,
        'grp_exchange'   => 0.02,
        'org_net_amount' => 90,
        'grp_net_amount' => 180,
        'unit_quantity_checked' => 10,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'invoice');
    file_put_contents($path, "%PDF-1.4\n% invoice $token\n%%EOF");
    $media = \App\Actions\Helpers\Media\SaveModelAttachment::make()->action($stockDelivery, [
        'path'         => $path,
        'originalName' => "invoice-$token.pdf",
        'extension'    => 'pdf',
        'scope'        => 'Invoice',
    ]);
    @unlink($path);

    \Illuminate\Support\Facades\Cache::put('current-currency-exchange:'.$organisationCurrency->code.'-'.$deliveryCurrency->code, 100, now()->addHour());

    \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::storeReading($stockDelivery, $media, [
        'state'          => 'read',
        'kind'           => \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::KIND,
        'read_at'        => now()->toIso8601String(),
        'is_invoice'     => true,
        'issuer'         => 'Supplier Test',
        'invoice_number' => "INV-$token",
        'invoice_date'   => '2026-09-20',
        'currency'       => $organisationCurrency->code,
        'references'     => [],
        'lines'          => [
            ['code' => "invcur $token", 'description' => 'Goods', 'quantity' => 10, 'unit_price' => 9.5, 'amount' => 95],
            ['code' => 'SAMPLE-1', 'description' => 'Sample', 'quantity' => 1, 'unit_price' => 5, 'amount' => 5],
        ],
        'charges'        => [],
        'total'          => 100,
        'checks'         => ['disagreements' => [], 'text_available' => true, 'amounts_missing' => [], 'mentions_delivery' => true, 'currency_printed' => true, 'number_printed' => true, 'other_deliveries' => []],
    ]);

    return [$stockDelivery->fresh(), $media, $stockDelivery->items()->first(), $deliveryCurrency, $organisationCurrency];
}

function readSupplierInvoiceDocument($test, StockDelivery $stockDelivery, string $document, array ...$readings): array
{
    $reply = fn (array $reading) => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => json_encode([
        'is_invoice'   => true,
        'issuer'       => 'Supplier Test',
        'invoice_date' => '2026-09-20',
        'currency'     => $stockDelivery->currency->code,
        'references'   => [],
        'charges'      => [],
        ...$reading,
    ])]]]]);
    $replies = array_map($reply, count($readings) === 1 ? [$readings[0], $readings[0]] : $readings);

    \Illuminate\Support\Facades\Http::fake(function () use (&$replies) {
        return array_shift($replies);
    });

    $path = tempnam(sys_get_temp_dir(), 'invoice').'.csv';
    file_put_contents($path, $document);
    $media = \App\Actions\Helpers\Media\SaveModelAttachment::make()->action($stockDelivery, [
        'path'         => $path,
        'originalName' => 'invoice-'.Str::random(8).'.csv',
        'extension'    => 'csv',
        'scope'        => 'Invoice',
    ]);
    @unlink($path);

    $test->post(route('grp.models.stock-delivery.invoice.read', [$stockDelivery->id, $media->id]))->assertRedirect();

    return [$media, \App\Actions\GoodsIn\StockDelivery\GetStockDeliveryInvoiceCosting::make()->review($stockDelivery->fresh(), \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media), $media)];
}

function readServiceInvoiceDocument($test, StockDelivery $stockDelivery, string $document, array ...$readings): array
{
    $reply = fn (array $reading) => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => json_encode([
        'is_invoice'     => true,
        'issuer'         => 'Sea Forwarder Test',
        'invoice_date'   => '2026-09-18',
        'currency'       => $stockDelivery->organisation->currency->code,
        'references'     => [],
        'vat_total'      => 0,
        ...$reading,
    ])]]]]);
    $replies = array_map($reply, count($readings) === 1 ? [$readings[0], $readings[0]] : $readings);

    \Illuminate\Support\Facades\Http::fake(function () use (&$replies) {
        return array_shift($replies);
    });

    $test->post(route('grp.models.stock-delivery.service-invoice.read-upload', [$stockDelivery->id]), [
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('bill-'.Str::random(8).'.csv', $document)],
    ])->assertSessionHasNoErrors();

    $media = $stockDelivery->attachments()->wherePivot('scope', 'Service invoice')->orderByPivot('id', 'desc')->first();

    return [$media, \App\Actions\GoodsIn\StockDelivery\GetStockDeliveryServiceInvoiceReadings::make()->review($stockDelivery, \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media))];
}

/**
 * @return array<int, PurchaseOrder>
 */
function createAgentPurchaseOrders(object $test, OrgAgent $orgAgent, int $numberOfOrders): array
{
    $orders = [];

    foreach (range(1, $numberOfOrders) as $index) {
        [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($test, $orgAgent);
        $order                              = StorePurchaseOrder::make()->action($orgSupplier, []);

        StorePurchaseOrderTransaction::make()->action(
            $order,
            $orgSupplierProduct->supplierProduct->historicSupplierProduct,
            $test->orgStocks[0],
            array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10 * $index])
        );

        $orders[] = $order->refresh();
    }

    return $orders;
}

function madeBySeller(\App\Models\Inventory\OrgStock $sellerOrgStock): \App\Models\Production\Artefact
{
    $seller     = $sellerOrgStock->organisation;
    $production = \App\Models\Production\Production::where('organisation_id', $seller->id)->first()
        ?? \App\Actions\Production\Production\StoreProduction::make()->action($seller, ['code' => 'MBS'.rand(100, 999), 'name' => 'Seller factory']);

    return \App\Models\Production\Artefact::where('org_stock_id', $sellerOrgStock->id)->whereNull('deleted_at')->first()
        ?? \App\Actions\Production\Artefact\StoreArtefact::make()->action($production, [
            'state'        => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
            'code'         => 'MBS-'.$sellerOrgStock->id,
            'name'         => 'Made by seller',
            'org_stock_id' => $sellerOrgStock->id,
        ]);
}
