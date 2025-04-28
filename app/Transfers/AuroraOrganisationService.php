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
use App\Transfers\Aurora\FetchAuroraAdjustment;
use App\Transfers\Aurora\FetchAuroraAgent;
use App\Transfers\Aurora\FetchAuroraArtefact;
use App\Transfers\Aurora\FetchAuroraBackInStockReminder;
use App\Transfers\Aurora\FetchAuroraBarcode;
use App\Transfers\Aurora\FetchAuroraCharge;
use App\Transfers\Aurora\FetchAuroraClockingMachine;
use App\Transfers\Aurora\FetchAuroraCredit;
use App\Transfers\Aurora\FetchAuroraCustomer;
use App\Transfers\Aurora\FetchAuroraCustomerClient;
use App\Transfers\Aurora\FetchAuroraCustomerNote;
use App\Transfers\Aurora\FetchAuroraDeletedCustomer;
use App\Transfers\Aurora\FetchAuroraDeletedEmployee;
use App\Transfers\Aurora\FetchAuroraDeletedInvoice;
use App\Transfers\Aurora\FetchAuroraDeletedLocation;
use App\Transfers\Aurora\FetchAuroraDeletedStock;
use App\Transfers\Aurora\FetchAuroraDeletedSupplier;
use App\Transfers\Aurora\FetchAuroraDeletedSupplierProduct;
use App\Transfers\Aurora\FetchAuroraDeletedUser;
use App\Transfers\Aurora\FetchAuroraDeliveryNote;
use App\Transfers\Aurora\FetchAuroraDeliveryNoteItem;
use App\Transfers\Aurora\FetchAuroraDepartment;
use App\Transfers\Aurora\FetchAuroraDispatchedEmail;
use App\Transfers\Aurora\FetchAuroraEmail;
use App\Transfers\Aurora\FetchAuroraEmailBulkRun;
use App\Transfers\Aurora\FetchAuroraEmailCopy;
use App\Transfers\Aurora\FetchAuroraEmailOngoingRun;
use App\Transfers\Aurora\FetchAuroraEmailTrackingEvent;
use App\Transfers\Aurora\FetchAuroraEmployee;
use App\Transfers\Aurora\FetchAuroraFamily;
use App\Transfers\Aurora\FetchAuroraFavourite;
use App\Transfers\Aurora\FetchAuroraFeedback;
use App\Transfers\Aurora\FetchAuroraHistoricAsset;
use App\Transfers\Aurora\FetchAuroraHistoricService;
use App\Transfers\Aurora\FetchAuroraHistoricSupplierProduct;
use App\Transfers\Aurora\FetchAuroraHistory;
use App\Transfers\Aurora\FetchAuroraIngredient;
use App\Transfers\Aurora\FetchAuroraInvoice;
use App\Transfers\Aurora\FetchAuroraInvoiceCategory;
use App\Transfers\Aurora\FetchAuroraInvoiceTransaction;
use App\Transfers\Aurora\FetchAuroraLocation;
use App\Transfers\Aurora\FetchAuroraMailshot;
use App\Transfers\Aurora\FetchAuroraMasterAsset;
use App\Transfers\Aurora\FetchAuroraMasterAssetHasStock;
use App\Transfers\Aurora\FetchAuroraMasterDepartment;
use App\Transfers\Aurora\FetchAuroraMasterFamily;
use App\Transfers\Aurora\FetchAuroraNoProductInvoiceTransaction;
use App\Transfers\Aurora\FetchAuroraNoProductTransaction;
use App\Transfers\Aurora\FetchAuroraNoProductTransactionHasOfferComponent;
use App\Transfers\Aurora\FetchAuroraOffer;
use App\Transfers\Aurora\FetchAuroraOfferCampaign;
use App\Transfers\Aurora\FetchAuroraOfferComponent;
use App\Transfers\Aurora\FetchAuroraOrder;
use App\Transfers\Aurora\FetchAuroraOrderDispatchedEmail;
use App\Transfers\Aurora\FetchAuroraOrganisation;
use App\Transfers\Aurora\FetchAuroraOrgPaymentServiceProvider;
use App\Transfers\Aurora\FetchAuroraOrgStockMovement;
use App\Transfers\Aurora\FetchAuroraPayment;
use App\Transfers\Aurora\FetchAuroraPaymentAccount;
use App\Transfers\Aurora\FetchAuroraPoll;
use App\Transfers\Aurora\FetchAuroraPollOption;
use App\Transfers\Aurora\FetchAuroraPollReply;
use App\Transfers\Aurora\FetchAuroraPortfolio;
use App\Transfers\Aurora\FetchAuroraProduct;
use App\Transfers\Aurora\FetchAuroraProductHasOrgStock;
use App\Transfers\Aurora\FetchAuroraProspect;
use App\Transfers\Aurora\FetchAuroraPurchaseOrder;
use App\Transfers\Aurora\FetchAuroraPurchaseOrderTransaction;
use App\Transfers\Aurora\FetchAuroraPurge;
use App\Transfers\Aurora\FetchAuroraQuery;
use App\Transfers\Aurora\FetchAuroraSalesChannel;
use App\Transfers\Aurora\FetchAuroraService;
use App\Transfers\Aurora\FetchAuroraShipper;
use App\Transfers\Aurora\FetchAuroraShippingZone;
use App\Transfers\Aurora\FetchAuroraShippingZoneSchema;
use App\Transfers\Aurora\FetchAuroraShop;
use App\Transfers\Aurora\FetchAuroraStock;
use App\Transfers\Aurora\FetchAuroraStockDelivery;
use App\Transfers\Aurora\FetchAuroraStockDeliveryItem;
use App\Transfers\Aurora\FetchAuroraStockFamily;
use App\Transfers\Aurora\FetchAuroraSubscriptionEvent;
use App\Transfers\Aurora\FetchAuroraSupplier;
use App\Transfers\Aurora\FetchAuroraSupplierProduct;
use App\Transfers\Aurora\FetchAuroraTimesheet;
use App\Transfers\Aurora\FetchAuroraTopUp;
use App\Transfers\Aurora\FetchAuroraTradeUnit;
use App\Transfers\Aurora\FetchAuroraTradeUnitImages;
use App\Transfers\Aurora\FetchAuroraTransaction;
use App\Transfers\Aurora\FetchAuroraTransactionHasOfferComponent;
use App\Transfers\Aurora\FetchAuroraUpload;
use App\Transfers\Aurora\FetchAuroraUser;
use App\Transfers\Aurora\FetchAuroraWarehouse;
use App\Transfers\Aurora\FetchAuroraWarehouseArea;
use App\Transfers\Aurora\FetchAuroraWebpage;
use App\Transfers\Aurora\FetchAuroraWebsite;
use App\Transfers\Aurora\FetchAuroraWebUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AuroraOrganisationService implements SourceOrganisationService
{
    public Organisation $organisation;
    public ?Fetch $fetch = null;

    public function initialisation(Organisation $organisation, string $databaseSuffix = ''): void
    {
        $database_settings = data_get(config('database.connections'), 'aurora');
        data_set($database_settings, 'database', Arr::get($organisation->source, 'db_name').$databaseSuffix);
        config(['database.connections.aurora' => $database_settings]);
        DB::connection('aurora');
        DB::purge('aurora');

        $this->organisation = $organisation;
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function fetchOrganisation(Organisation $organisation): ?array
    {
        return (new FetchAuroraOrganisation($this))->fetch();
    }

    public function fetchEmployee($id): ?array
    {
        return (new FetchAuroraEmployee($this))->fetch($id);
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
        return (new FetchAuroraShipper($this))->fetch($id);
    }

    public function fetchCustomer($id): ?array
    {
        return (new FetchAuroraCustomer($this))->fetch($id);
    }

    public function fetchDeletedCustomer($id): ?array
    {
        return (new FetchAuroraDeletedCustomer($this))->fetch($id);
    }

    public function fetchDeletedStock($id): ?array
    {
        return (new FetchAuroraDeletedStock($this))->fetch($id);
    }

    public function fetchDeletedLocation($id): ?array
    {
        return (new FetchAuroraDeletedLocation($this))->fetch($id);
    }

    public function fetchDeletedInvoice($id): ?array
    {
        return (new FetchAuroraDeletedInvoice($this))->fetch($id);
    }

    public function fetchWebUser($id): ?array
    {
        return (new FetchAuroraWebUser($this))->fetch($id);
    }

    public function fetchOrder($id): ?array
    {
        return (new FetchAuroraOrder($this))->fetch($id);
    }

    public function fetchDeliveryNote($id): ?array
    {
        return (new FetchAuroraDeliveryNote($this))->fetch($id);
    }

    public function fetchInvoice($id): ?array
    {
        return (new FetchAuroraInvoice($this))->fetchInvoice($id);
    }

    public function fetchCustomerClient($id): ?array
    {
        return (new FetchAuroraCustomerClient($this))->fetch($id);
    }

    public function fetchWarehouse($id): ?array
    {
        return (new FetchAuroraWarehouse($this))->fetch($id);
    }

    public function fetchWarehouseArea($id): ?array
    {
        return (new FetchAuroraWarehouseArea($this))->fetch($id);
    }

    public function fetchLocation($id): ?array
    {
        return (new FetchAuroraLocation($this))->fetch($id);
    }

    public function fetchTransaction($id): ?array
    {
        return (new FetchAuroraTransaction($this))->fetch($id);
    }

    public function fetchNoProductTransaction($id, Order $order): ?array
    {
        return (new FetchAuroraNoProductTransaction($this))->fetchNoProductTransaction($id, $order);
    }

    public function fetchDeliveryNoteItem($id, DeliveryNote $deliveryNote): ?array
    {
        return (new FetchAuroraDeliveryNoteItem($this))->fetchDeliveryNoteTransaction($id, $deliveryNote);
    }

    public function fetchInvoiceTransaction($id, Invoice $invoice, bool $isFulfilment): ?array
    {
        return (new FetchAuroraInvoiceTransaction($this))->fetchInvoiceTransaction($id, $invoice, $isFulfilment);
    }

    public function fetchNoProductInvoiceTransaction($id, Invoice $invoice): ?array
    {
        return (new FetchAuroraNoProductInvoiceTransaction($this))->fetchNoProductInvoiceTransaction($id, $invoice);
    }

    public function fetchHistoricAsset($id): ?array
    {
        return (new FetchAuroraHistoricAsset($this))->fetch($id);
    }

    public function fetchHistoricSupplierProduct($id): ?array
    {
        return (new FetchAuroraHistoricSupplierProduct($this))->fetch($id);
    }

    public function fetchHistoricService($id): ?array
    {
        return (new FetchAuroraHistoricService($this))->fetch($id);
    }

    public function fetchProductHasOrgStock($id): ?array
    {
        return (new FetchAuroraProductHasOrgStock($this))->fetch($id);
    }

    public function fetchMasterAssetHasStock($id): ?array
    {
        return (new FetchAuroraMasterAssetHasStock($this))->fetch($id);
    }

    public function fetchDepartment($id): ?array
    {
        return (new FetchAuroraDepartment($this))->fetch($id);
    }

    public function fetchFamily($id): ?array
    {
        return (new FetchAuroraFamily($this))->fetch($id);
    }

    public function fetchProduct($id): ?array
    {
        return (new FetchAuroraProduct($this))->fetch($id);
    }

    public function fetchService($id): ?array
    {
        return (new FetchAuroraService($this))->fetch($id);
    }

    public function fetchStock($id): ?array
    {
        return (new FetchAuroraStock($this))->fetch($id);
    }

    public function fetchStockFamily($id): ?array
    {
        return (new FetchAuroraStockFamily($this))->fetch($id);
    }

    public function fetchTradeUnit($id): ?array
    {
        return (new FetchAuroraTradeUnit($this))->fetch($id);
    }

    public function fetchTradeUnitImages($id): ?array
    {
        return (new FetchAuroraTradeUnitImages($this))->fetch($id);
    }

    public function fetchAgent($id): ?array
    {
        return (new FetchAuroraAgent($this))->fetch($id);
    }

    public function fetchSupplier($id): ?array
    {
        return (new FetchAuroraSupplier($this))->fetch($id);
    }

    public function fetchSupplierProduct($id): ?array
    {
        return (new FetchAuroraSupplierProduct($this))->fetch($id);
    }

    public function fetchDeletedSupplier($id): ?array
    {
        return (new FetchAuroraDeletedSupplier($this))->fetch($id);
    }

    public function fetchDeletedEmployee($id): ?array
    {
        return (new FetchAuroraDeletedEmployee($this))->fetch($id);
    }

    public function fetchDeletedSupplierProduct($id): ?array
    {
        return (new FetchAuroraDeletedSupplierProduct($this))->fetch($id);
    }

    public function fetchOrgPaymentServiceProvider($id): ?array
    {
        return (new FetchAuroraOrgPaymentServiceProvider($this))->fetch($id);
    }

    public function fetchPaymentAccount($id): ?array
    {
        return (new FetchAuroraPaymentAccount($this))->fetch($id);
    }

    public function fetchPayment($id): ?array
    {
        return (new FetchAuroraPayment($this))->fetch($id);
    }


    public function fetchMailshot($id): ?array
    {
        return (new FetchAuroraMailshot($this))->fetch($id);
    }

    public function fetchDispatchedEmail($id): ?array
    {
        return (new FetchAuroraDispatchedEmail($this))->fetch($id);
    }

    public function fetchProspect($id): ?array
    {
        return (new FetchAuroraProspect($this))->fetch($id);
    }

    public function fetchEmailTrackingEvent($id): ?array
    {
        return (new FetchAuroraEmailTrackingEvent($this))->fetch($id);
    }

    public function fetchEmailCopy($id): ?array
    {
        return (new FetchAuroraEmailCopy($this))->fetch($id);
    }

    public function fetchPurchaseOrder($id): ?array
    {
        return (new FetchAuroraPurchaseOrder($this))->fetch($id);
    }

    public function fetchStockDelivery($id): ?array
    {
        return (new FetchAuroraStockDelivery($this))->fetch($id);
    }

    public function fetchTimesheet($id): ?array
    {
        return (new FetchAuroraTimesheet($this))->fetch($id);
    }

    public function fetchClockingMachine($id): ?array
    {
        return (new FetchAuroraClockingMachine($this))->fetch($id);
    }

    public function fetchArtefact($id): array
    {
        return (new FetchAuroraArtefact($this))->fetch($id);
    }

    public function fetchBarcode($id): ?array
    {
        return (new FetchAuroraBarcode($this))->fetch($id);
    }

    public function fetchPortfolio($id): ?array
    {
        return (new FetchAuroraPortfolio($this))->fetch($id);
    }

    public function fetchCharge($id): ?array
    {
        return (new FetchAuroraCharge($this))->fetch($id);
    }

    public function fetchCredit($id): ?array
    {
        return (new FetchAuroraCredit($this))->fetch($id);
    }

    public function fetchOrgStockMovement($id): ?array
    {
        return (new FetchAuroraOrgStockMovement($this))->fetch($id);
    }

    public function fetchShippingZoneSchema($id): ?array
    {
        return (new FetchAuroraShippingZoneSchema($this))->fetch($id);
    }

    public function fetchShippingZone($id): ?array
    {
        return (new FetchAuroraShippingZone($this))->fetch($id);
    }

    public function fetchAdjustment($id): ?array
    {
        return (new FetchAuroraAdjustment($this))->fetch($id);
    }

    public function fetchPurchaseOrderTransaction($id, PurchaseOrder $purchaseOrder): ?array
    {
        return (new FetchAuroraPurchaseOrderTransaction($this))->fetchAuroraPurchaseOrderTransaction($id, $purchaseOrder);
    }

    public function fetchStockDeliveryItem($id, StockDelivery $stockDelivery): ?array
    {
        return (new FetchAuroraStockDeliveryItem($this))->fetchAuroraStockDeliveryItem($id, $stockDelivery);
    }

    public function fetchOfferCampaign($id): ?array
    {
        return (new FetchAuroraOfferCampaign($this))->fetch($id);
    }

    public function fetchOffer($id): ?array
    {
        return (new FetchAuroraOffer($this))->fetch($id);
    }

    public function fetchOfferComponent($id): ?array
    {
        return (new FetchAuroraOfferComponent($this))->fetch($id);
    }

    public function fetchCustomerNote($id): ?array
    {
        return (new FetchAuroraCustomerNote($this))->fetch($id);
    }

    public function fetchDeletedUser($id): ?array
    {
        return (new FetchAuroraDeletedUser($this))->fetch($id);
    }

    public function fetchUser($id): ?array
    {
        return (new FetchAuroraUser($this))->fetch($id);
    }

    public function fetchHistory($id): ?array
    {
        return (new FetchAuroraHistory($this))->fetch($id);
    }

    public function fetchUpload($id): ?array
    {
        return (new FetchAuroraUpload($this))->fetch($id);
    }

    public function fetchFavourite($id): ?array
    {
        return (new FetchAuroraFavourite($this))->fetch($id);
    }

    public function fetchBackInStockReminder($id): ?array
    {
        return (new FetchAuroraBackInStockReminder($this))->fetch($id);
    }

    public function fetchTopUp($id): ?array
    {
        return (new FetchAuroraTopUp($this))->fetch($id);
    }

    public function fetchQuery($id): ?array
    {
        return (new FetchAuroraQuery($this))->fetch($id);
    }

    public function fetchOrderDispatchedEmail($id): ?array
    {
        return (new FetchAuroraOrderDispatchedEmail($this))->fetch($id);
    }

    public function fetchIngredient($id): ?array
    {
        return (new FetchAuroraIngredient($this))->fetch($id);
    }

    public function fetchFeedback($id): ?array
    {
        return (new FetchAuroraFeedback($this))->fetch($id);
    }

    public function fetchPoll($id): ?array
    {
        return (new FetchAuroraPoll($this))->fetch($id);
    }

    public function fetchPollOption($id): ?array
    {
        return (new FetchAuroraPollOption($this))->fetch($id);
    }

    public function fetchPollReply($id): ?array
    {
        return (new FetchAuroraPollReply($this))->fetch($id);
    }

    public function fetchPurge($id): ?array
    {
        return (new FetchAuroraPurge($this))->fetch($id);
    }

    public function fetchSalesChannel($id): ?array
    {
        return (new FetchAuroraSalesChannel($this))->fetch($id);
    }

    public function fetchTransactionHasOfferComponent($id, Order $order): ?array
    {
        return (new FetchAuroraTransactionHasOfferComponent($this))->fetchTransactionHasOfferComponent($id, $order);
    }

    public function fetchNoProductTransactionHasOfferComponent($id, Order $order): ?array
    {
        return (new FetchAuroraNoProductTransactionHasOfferComponent($this))->fetchNoProductTransactionHasOfferComponent($id, $order);
    }

    public function fetchEmail($id): ?array
    {
        return (new FetchAuroraEmail($this))->fetch($id);
    }

    public function fetchEmailBulkRun($id): ?array
    {
        return (new FetchAuroraEmailBulkRun($this))->fetch($id);
    }

    public function fetchEmailOngoingRun($id): ?array
    {
        return (new FetchAuroraEmailOngoingRun($this))->fetch($id);
    }

    public function fetchSubscriptionEvent($id): ?array
    {
        return (new FetchAuroraSubscriptionEvent($this))->fetch($id);
    }

    public function fetchMasterDepartment($id): ?array
    {
        return (new FetchAuroraMasterDepartment($this))->fetch($id);
    }

    public function fetchMasterFamily($id): ?array
    {
        return (new FetchAuroraMasterFamily($this))->fetch($id);
    }

    public function fetchMasterAsset($id): ?array
    {
        return (new FetchAuroraMasterAsset($this))->fetch($id);
    }

    public function fetchInvoiceCategory($id): ?array
    {
        return (new FetchAuroraInvoiceCategory($this))->fetch($id);
    }

}
