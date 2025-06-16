<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:52:35 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers;

use App\Models\Accounting\Invoice;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Models\Transfers\Fetch;
use App\Transfers\Aurora\FetchAuroraCustomer;
use App\Transfers\Aurora\FetchAuroraProspect;
use App\Transfers\Aurora\FetchAuroraShop;
use App\Transfers\Aurora\FetchAuroraWebpage;
use App\Transfers\Aurora\FetchAuroraWebsite;
use App\Transfers\Aurora\FetchAuroraWebUser;
use App\Transfers\Wowsbar\FetchWowsbarEmployee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WowsbarOrganisationService implements SourceOrganisationService
{
    public Organisation $organisation;
    public ?Fetch $fetch = null;

    public function initialisation(Organisation $organisation, string $databaseSuffix = ''): void
    {
        $database_settings = data_get(config('database.connections'), 'wowsbar');
        data_set($database_settings, 'database', Arr::get($organisation->source, 'db_name').$databaseSuffix);
        config(['database.connections.wowsbar' => $database_settings]);
        DB::connection('wowsbar');
        DB::purge('wowsbar');

        $this->organisation = $organisation;
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function fetchOrganisation(Organisation $organisation): ?array
    {
        return null;
    }

    public function fetchEmployee($id): ?array
    {
        return (new FetchWowsbarEmployee($this))->fetch($id);
    }

    public function fetchShop($id): ?array
    {
        return (new FetchAuroraShop($this))->fetch($id);
    }

    public function fetchWebsite($id): ?array
    {
        return (new FetchAuroraWebsite($this))->fetch($id);
    }

    public function fetchWebpage($id): ?array
    {
        return (new FetchAuroraWebpage($this))->fetch($id);
    }

    public function fetchShipper($id): ?array
    {
        return null;
    }

    public function fetchCustomer($id): ?array
    {
        return (new FetchAuroraCustomer($this))->fetch($id);
    }

    public function fetchDeletedCustomer($id): ?array
    {
        return null;
    }

    public function fetchDeletedStock($id): ?array
    {
        return null;
    }

    public function fetchDeletedLocation($id): ?array
    {
        return null;
    }

    public function fetchDeletedInvoice($id): ?array
    {
        return null;
    }

    public function fetchWebUser($id): ?array
    {
        return (new FetchAuroraWebUser($this))->fetch($id);
    }

    public function fetchOrder($id): ?array
    {
        return null;
    }

    public function fetchDeliveryNote($id): ?array
    {
        return null;
    }

    public function fetchInvoice($id): ?array
    {
        return null;
    }

    public function fetchCustomerClient($id): ?array
    {
        return null;
    }

    public function fetchWarehouse($id): ?array
    {
        return null;
    }

    public function fetchWarehouseArea($id): ?array
    {
        return null;
    }

    public function fetchLocation($id): ?array
    {
        return null;
    }

    public function fetchTransaction($id): ?array
    {
        return null;
    }

    public function fetchNoProductTransaction($id, Order $order): ?array
    {
        return null;
    }

    public function fetchDeliveryNoteItem($id, DeliveryNote $deliveryNote): ?array
    {
        return null;
    }

    public function fetchInvoiceTransaction($id, Invoice $invoice, bool $isFulfilment): ?array
    {
        return null;
    }

    public function fetchHistoricAsset($id): ?array
    {
        return null;
    }

    public function fetchHistoricService($id): ?array
    {
        return null;
    }

    public function fetchProductHasOrgStock($id): ?array
    {
        return null;
    }

    public function fetchMasterAssetHasStock($id): ?array
    {
        return null;
    }

    public function fetchDepartment($id): ?array
    {
        return null;
    }

    public function fetchFamily($id): ?array
    {
        return null;
    }

    public function fetchProduct($id): ?array
    {
        return null;
    }

    public function fetchService($id): ?array
    {
        return null;
    }

    public function fetchStock($id): ?array
    {
        return null;
    }

    public function fetchStockFamily($id): ?array
    {
        return null;
    }

    public function fetchTradeUnit($id): ?array
    {
        return null;
    }

    public function fetchTradeUnitImages($id): ?array
    {
        return null;
    }

    public function fetchAgent($id): ?array
    {
        return null;
    }

    public function fetchSupplier($id): ?array
    {
        return null;
    }

    public function fetchSupplierProduct($id): ?array
    {
        return null;
    }

    public function fetchDeletedSupplier($id): ?array
    {
        return null;
    }

    public function fetchDeletedEmployee($id): ?array
    {
        return null;
    }

    public function fetchDeletedSupplierProduct($id): ?array
    {
        return null;
    }

    public function fetchOrgPaymentServiceProvider($id): ?array
    {
        return null;
    }

    public function fetchPaymentAccount($id): ?array
    {
        return null;
    }

    public function fetchPayment($id): ?array
    {
        return null;
    }

    public function fetchMailshot($id): ?array
    {
        return null;
    }

    public function fetchDispatchedEmail($id): ?array
    {
        return null;
    }

    public function fetchProspect($id): ?array
    {
        return (new FetchAuroraProspect($this))->fetch($id);
    }

    public function fetchEmailTrackingEvent($id): ?array
    {
        return null;
    }

    public function fetchPurchaseOrder($id): ?array
    {
        return null;
    }

    public function fetchStockDelivery($id): ?array
    {
        return null;
    }

    public function fetchTimesheet($id): ?array
    {
        return null;
    }

    public function fetchClockingMachine($id): ?array
    {
        return null;
    }

    public function fetchArtefact($id): ?array
    {
        return null;
    }

    public function fetchBarcode($id): ?array
    {
        return null;
    }

    public function fetchPortfolio($id): ?array
    {
        return null;
    }

    public function fetchCharge($id): ?array
    {
        return null;
    }

    public function fetchCredit($id): ?array
    {
        return null;
    }

    public function fetchOrgStockMovement($id): ?array
    {
        return null;
    }

    public function fetchShippingZoneSchema($id): ?array
    {
        return null;
    }

    public function fetchShippingZone($id): ?array
    {
        return null;
    }

    public function fetchAdjustment($id): ?array
    {
        return null;
    }

    public function fetchNoProductInvoiceTransaction($id, Invoice $invoice): ?array
    {
        return null;
    }

    public function fetchPurchaseOrderTransaction($id, PurchaseOrder $purchaseOrder): ?array
    {
        return null;
    }

    public function fetchStockDeliveryItem($id, StockDelivery $stockDelivery): ?array
    {
        return null;
    }

    public function fetchHistoricSupplierProduct($id): ?array
    {
        return null;
    }

    public function fetchOfferCampaign($id): ?array
    {
        return null;
    }

    public function fetchOffer($id): ?array
    {
        return null;
    }

    public function fetchOfferComponent($id): ?array
    {
        return null;
    }

    public function fetchCustomerNote($id): ?array
    {
        return null;
    }

    public function fetchDeletedUser($id): ?array
    {
        return null;
    }

    public function fetchUser($id): ?array
    {
        return null;
    }

    public function fetchHistory($id): ?array
    {
        return null;
    }

    public function fetchUpload($id): ?array
    {
        return null;
    }

    public function fetchFavourite($id): ?array
    {
        return null;
    }

    public function fetchBackInStockReminder($id): ?array
    {
        return null;
    }

    public function fetchTopUp($id): ?array
    {
        return null;
    }

    public function fetchEmailCopy($id): ?array
    {
        return null;
    }

    public function fetchQuery($id): ?array
    {
        return null;
    }

    public function fetchOrderDispatchedEmail($id): ?array
    {
        return null;
    }

    public function fetchIngredient($id): ?array
    {
        return null;
    }

    public function fetchFeedback($id): ?array
    {
        return null;
    }

    public function fetchPoll($id): ?array
    {
        return null;
    }

    public function fetchPollOption($id): ?array
    {
        return null;
    }

    public function fetchPollReply($id): ?array
    {
        return null;
    }

    public function fetchPurge($id): ?array
    {
        return null;
    }

    public function fetchSalesChannel($id): ?array
    {
        return null;
    }

    public function fetchTransactionHasOfferComponent($id, Order $order): ?array
    {
        return null;
    }

    public function fetchNoProductTransactionHasOfferComponent($id, Order $order): ?array
    {
        return null;
    }

    public function fetchEmail($id): ?array
    {
        return null;
    }

    public function fetchEmailBulkRun($id): ?array
    {
        return null;
    }

    public function fetchEmailOngoingRun($id): ?array
    {
        return null;
    }

    public function fetchSubscriptionEvent($id): ?array
    {
        return null;
    }

    public function fetchMasterDepartment($id): ?array
    {
        return null;
    }

    public function fetchMasterFamily($id): ?array
    {
        return null;
    }

    public function fetchMasterAsset($id): ?array
    {
        return null;
    }

    public function fetchInvoiceCategory($id): ?array
    {
        return null;
    }

    public function fetchCollection($id): ?array
    {
        return null;
    }


}
