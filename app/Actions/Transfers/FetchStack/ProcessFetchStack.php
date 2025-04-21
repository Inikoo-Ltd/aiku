<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\FetchStack;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraAgent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraBarcode;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCharge;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCredit;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCustomer;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCustomerClient;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraCustomerNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteCustomerClient;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteDeliveryNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeleteInvoice;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDeliveryNote;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDepartment;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraDispatchedEmail;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraEmailTrackingEvent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraEmployee;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFamily;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFavourites;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraFeedback;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraInvoice;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraLocation;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraMailshot;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOffer;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOfferCampaign;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOfferComponent;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOrder;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOrgStockMovement;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPayment;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPortfolio;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraProduct;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraProspect;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPurchaseOrder;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraPurge;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraShop;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraStockDelivery;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraStockFamily;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraSupplier;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraSupplierProduct;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraTimesheet;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraTopUp;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWarehouse;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWarehouseArea;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraWebUser;
use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Models\Transfers\FetchStack;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessFetchStack
{
    use AsAction;
    use WithOrganisationSource;


    /**
     * @throws \Throwable
     */
    public function handle(FetchStack $fetchStack, $bg = false): void
    {
        $organisation = $fetchStack->organisation;
        $modelData    = [
            'fetch_stack_id' => $fetchStack->id,
            'id'             => $fetchStack->operation_id,
            'bg'             => $bg
        ];

        if ($fetchStack->operation == 'DeleteFavourite') {
            $modelData['unfavourited_at'] = $fetchStack->submitted_at;
        }

        $fetchStack->update([
            'start_fetch_at' => now()
        ]);


        $res = match ($fetchStack->operation) {
            'Agent' => ProcessAuroraAgent::make()->action($organisation, $modelData),
            'Barcode' => ProcessAuroraBarcode::make()->action($organisation, $modelData),
            'Change' => ProcessAuroraCharge::make()->action($organisation, $modelData),
            'Credit' => ProcessAuroraCredit::make()->action($organisation, $modelData),
            'CustomerClient' => ProcessAuroraCustomerClient::make()->action($organisation, $modelData),
            'DeleteCustomerClient' => ProcessAuroraDeleteCustomerClient::make()->action($organisation, $modelData),
            'CustomerNote' => ProcessAuroraCustomerNote::make()->action($organisation, $modelData),
            'DeleteDeliveryNote' => ProcessAuroraDeleteDeliveryNote::make()->action($organisation, $modelData),
            'Department' => ProcessAuroraDepartment::make()->action($organisation, $modelData),
            'EmailTrackingEvent' => ProcessAuroraEmailTrackingEvent::make()->action($organisation, $modelData),
            'Staff', 'Employee' => ProcessAuroraEmployee::make()->action($organisation, $modelData),
            'Family' => ProcessAuroraFamily::make()->action($organisation, $modelData),
            'Feedback' => ProcessAuroraFeedback::make()->action($organisation, $modelData),
            'DeleteInvoice' => ProcessAuroraDeleteInvoice::make()->action($organisation, $modelData),
            'Location' => ProcessAuroraLocation::make()->action($organisation, $modelData),
            'Mailshot' => ProcessAuroraMailshot::make()->action($organisation, $modelData),
            'OfferCampaign' => ProcessAuroraOfferCampaign::make()->action($organisation, $modelData),
            'OfferComponent' => ProcessAuroraOfferComponent::make()->action($organisation, $modelData),
            'Offer' => ProcessAuroraOffer::make()->action($organisation, $modelData),
            'OrgStockMovement' => ProcessAuroraOrgStockMovement::make()->action($organisation, $modelData),
            'Payment' => ProcessAuroraPayment::make()->action($organisation, $modelData),
            'Product' => ProcessAuroraProduct::make()->action($organisation, $modelData),
            'Prospect' => ProcessAuroraProspect::make()->action($organisation, $modelData),
            'Purge' => ProcessAuroraPurge::make()->action($organisation, $modelData),
            'Shop' => ProcessAuroraShop::make()->action($organisation, $modelData),
            'StockFamily' => ProcessAuroraStockFamily::make()->action($organisation, $modelData),
            'SupplierPart' => ProcessAuroraSupplierProduct::make()->action($organisation, $modelData),
            'Supplier' => ProcessAuroraSupplier::make()->action($organisation, $modelData),
            'Timesheet' => ProcessAuroraTimesheet::make()->action($organisation, $modelData),
            'TopUp' => ProcessAuroraTopUp::make()->action($organisation, $modelData),
            'WarehouseArea' => ProcessAuroraWarehouseArea::make()->action($organisation, $modelData),
            'Warehouse' => ProcessAuroraWarehouse::make()->action($organisation, $modelData),
            'Favourite' => ProcessAuroraFavourites::make()->action($organisation, $modelData),
            'WebsiteUser' => ProcessAuroraWebUser::make()->action($organisation, $modelData),
            'Portfolio' => ProcessAuroraPortfolio::make()->action($organisation, $modelData),
            'Order' => ProcessAuroraOrder::make()->action($organisation, array_merge($modelData, ['with' => 'transactions,payments'])),
            'Invoice' => ProcessAuroraInvoice::make()->action($organisation, array_merge($modelData, ['with' => 'transactions,payments'])),
            'DispatchedEmailWithFull' => ProcessAuroraDispatchedEmail::make()->action($organisation, array_merge($modelData, ['with' => 'full'])),
            'SupplierDelivery' => ProcessAuroraStockDelivery::make()->action($organisation, array_merge($modelData, ['with' => 'transactions'])),
            'DeliveryNote' => ProcessAuroraDeliveryNote::make()->action($organisation, array_merge($modelData, ['with' => 'transactions'])),
            'PurchaseOrder' => ProcessAuroraPurchaseOrder::make()->action($organisation, array_merge($modelData, ['with' => 'transactions'])),
            'Customer' => ProcessAuroraCustomer::make()->action($organisation, $modelData),
            default => null,
        };


        if ($res !== null) {
            $fetchStack->update([
                'state'  => $bg ? FetchStackStateEnum::PROCESSING : FetchStackStateEnum::SUCCESS,
                'result' => $res,
            ]);

            if (!$bg) {
                $fetchStack->update(
                    [
                        'finish_fetch_at' => now()
                    ]
                );
            }
        } else {
            $fetchStack->update([
                'send_to_queue_at' => null,
                'start_fetch_at'   => null,
                'state'            => FetchStackStateEnum::IN_PROCESS,
            ]);
        }
    }


}
