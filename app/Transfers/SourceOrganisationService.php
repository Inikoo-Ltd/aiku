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

interface SourceOrganisationService
{
    public function fetchOrganisation(Organisation $organisation);

    public function fetchEmployee($id);

    public function fetchShop($id);

    public function fetchWebsite($id);

    public function fetchWebpage($id);

    public function fetchShipper($id);

    public function fetchTransaction($id);

    public function fetchNoProductTransaction($id, Order $order);

    public function fetchDeliveryNoteItem($id, DeliveryNote $deliveryNote);

    public function fetchInvoiceTransaction($id, Invoice $invoice, bool $isFulfilment);

    public function fetchNoProductInvoiceTransaction($id, Invoice $invoice);

    public function fetchCustomer($id);

    public function fetchDeletedCustomer($id);

    public function fetchDeletedStock($id);

    public function fetchDeletedLocation($id);

    public function fetchDeletedInvoice($id);

    public function fetchDeletedSupplier($id);

    public function fetchDeletedEmployee($id);

    public function fetchDeletedSupplierProduct($id);

    public function fetchCustomerClient($id);

    public function fetchWebUser($id);

    public function fetchOrder($id);

    public function fetchDeliveryNote($id);

    public function fetchInvoice($id);

    public function fetchWarehouse($id);

    public function fetchWarehouseArea($id);

    public function fetchLocation($id);

    public function fetchHistoricAsset($id);

    public function fetchHistoricSupplierProduct($id);

    public function fetchHistoricService($id);

    public function fetchDepartment($id);

    public function fetchFamily($id);

    public function fetchProduct($id);

    public function fetchService($id);

    public function fetchProductHasOrgStock($id);

    public function fetchMasterAssetHasStock($id);

    public function fetchStock($id);

    public function fetchStockFamily($id);

    public function fetchTradeUnit($id);

    public function fetchTradeUnitImages($id);

    public function fetchAgent($id);

    public function fetchSupplier($id);

    public function fetchSupplierProduct($id);

    public function fetchArtefact($id);

    public function fetchOrgPaymentServiceProvider($id);

    public function fetchPaymentAccount($id);

    public function fetchPayment($id);

    public function fetchMailshot($id);

    public function fetchDispatchedEmail($id);

    public function fetchProspect($id);

    public function fetchEmailTrackingEvent($id);

    public function fetchEmailCopy($id);

    public function initialisation(Organisation $organisation);

    public function fetchPurchaseOrder($id);

    public function fetchStockDelivery($id);

    public function fetchTimesheet($id);

    public function fetchClockingMachine($id);

    public function fetchBarcode($id);

    public function fetchPortfolio($id);

    public function fetchCharge($id);

    public function fetchCredit($id);

    public function fetchShippingZoneSchema($id);

    public function fetchShippingZone($id);

    public function fetchAdjustment($id);

    public function fetchPurchaseOrderTransaction($id, PurchaseOrder $purchaseOrder);

    public function fetchStockDeliveryItem($id, StockDelivery $stockDelivery);

    public function fetchOfferCampaign($id);

    public function fetchOffer($id);

    public function fetchOfferComponent($id);

    public function fetchDeletedUser($id);

    public function fetchUser($id);

    public function fetchHistory($id);

    public function fetchUpload($id);

    public function fetchFavourite($id);

    public function fetchBackInStockReminder($id);

    public function fetchTopUp($id);

    public function fetchQuery($id);

    public function fetchOrderDispatchedEmail($id);

    public function fetchIngredient($id);

    public function fetchFeedback($id);

    public function fetchPoll($id);

    public function fetchPollOption($id);

    public function fetchPollReply($id);

    public function fetchPurge($id);

    public function fetchSalesChannel($id);

    public function fetchTransactionHasOfferComponent($id, Order $order);

    public function fetchNoProductTransactionHasOfferComponent($id, Order $order);

    public function fetchEmail($id);

    public function fetchEmailBulkRun($id);

    public function fetchSubscriptionEvent($id);

    public function fetchMasterDepartment($id);

    public function fetchMasterFamily($id);

    public function fetchMasterAsset($id);

    public function fetchInvoiceCategory($id);

    public function fetchCollection($id);


}
