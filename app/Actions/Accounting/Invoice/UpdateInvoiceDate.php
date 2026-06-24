<?php

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\RedoInvoiceCategoryTimeSeries;
use App\Actions\Accounting\InvoiceTransaction\ProcessInvoiceTransactionTimeSeries;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\RedoShopTimeSeries;
use App\Actions\Comms\Email\SendInvoiceDateChangedNotification;
use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Actions\Dropshipping\Platform\RedoPlatformTimeSeries;
use App\Actions\Masters\MasterShop\RedoMasterShopTimeSeries;
use App\Actions\Ordering\SalesChannel\RedoSalesChannelTimeSeries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\RedoOrganisationTimeSeries;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;

class UpdateInvoiceDate extends OrgAction
{
    use WithActionUpdate;


    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $oldDate = $invoice->date;

        $invoice = $this->update($invoice, $modelData);

        $changes = $invoice->getChanges();


        if ($oldDate->toDateString() !== $invoice->date->toDateString()) {
            $invoice->invoiceTransactions()->update(['date' => $invoice->date]);
            UpdateCustomerLastInvoicedDate::run($invoice->customer);

            RedoShopTimeSeries::dispatch(shopId: $invoice->shop_id, from: $oldDate->toDateString(), to: $oldDate->toDateString())->delay(900);
            RedoShopTimeSeries::dispatch(shopId: $invoice->shop_id, from: $invoice->date->toDateString(), to: $invoice->date->toDateString())->delay(900);
            RedoOrganisationTimeSeries::dispatch(organisationId: $invoice->organisation_id, from: $oldDate->toDateString(), to: $oldDate->toDateString())->delay(900);
            RedoOrganisationTimeSeries::dispatch(organisationId: $invoice->organisation_id, from: $invoice->date->toDateString(), to: $invoice->date->toDateString())->delay(900);
            if ($invoice->master_shop_id) {
                RedoMasterShopTimeSeries::dispatch(masterShopId: $invoice->master_shop_id, from: $oldDate->toDateString(), to: $oldDate->toDateString())->delay(900);
                RedoMasterShopTimeSeries::dispatch(masterShopId: $invoice->master_shop_id, from: $invoice->date->toDateString(), to: $invoice->date->toDateString())->delay(900);
            }
            if ($invoice->platform_id) {
                RedoPlatformTimeSeries::dispatch(platformId: $invoice->platform_id, from: $oldDate->toDateString(), to: $oldDate->toDateString())->delay(900);
                RedoPlatformTimeSeries::dispatch(platformId: $invoice->platform_id, from: $invoice->date->toDateString(), to: $invoice->date->toDateString())->delay(900);
            }
        }


        if (isset($changes['date'])) {
            $invoice->invoiceTransactions()->update(['date' => $invoice->date]);

            GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);

            OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);

            ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);


            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            }

            $oldDateString = Carbon::parse($oldDate)->toDateString();
            $newDateString = Carbon::parse($invoice->date)->toDateString();

            if ($invoice->invoiceCategory) {
                RedoInvoiceCategoryTimeSeries::dispatch($invoice->invoice_category_id, $oldDateString, $oldDateString)->delay(2);
                RedoInvoiceCategoryTimeSeries::dispatch($invoice->invoice_category_id, $newDateString, $newDateString)->delay(2);
            }

            if ($invoice->sales_channel_id) {
                RedoSalesChannelTimeSeries::dispatch($invoice->sales_channel_id, $oldDateString, $oldDateString)->delay(2);
                RedoSalesChannelTimeSeries::dispatch($invoice->sales_channel_id, $newDateString, $newDateString)->delay(2);
            }


            foreach ($invoice->invoiceTransactions as $invoiceTransaction) {
                ProcessInvoiceTransactionTimeSeries::dispatch($invoiceTransaction, $oldDateString)->delay(120);
                ProcessInvoiceTransactionTimeSeries::dispatch($invoiceTransaction, $newDateString)->delay(120);
            }

            SendInvoiceDateChangedNotification::dispatch($invoice, $oldDate);
        }

        return $invoice;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }
}
