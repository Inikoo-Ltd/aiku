<?php

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Search\InvoiceRecordSearch;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Accounting\InvoiceCategory\RedoInvoiceCategoryTimeSeries;
use App\Actions\Accounting\InvoiceTransaction\ProcessInvoiceTransactionTimeSeries;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Catalogue\Shop\RedoShopTimeSeries;
use App\Actions\Comms\Email\SendInvoiceDateChangedNotification;
use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Actions\Dropshipping\Platform\RedoPlatformTimeSeries;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Actions\Masters\MasterShop\RedoMasterShopTimeSeries;
use App\Actions\Ordering\SalesChannel\RedoSalesChannelTimeSeries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\RedoOrganisationTimeSeries;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;

class UpdateInvoiceDate extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        //  Todo: Edit restriction
        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $oldDate = $invoice->date;
        $invoice = $this->update($invoice, $modelData);

        $changes = $invoice->getChanges();

        if (isset($changes['date'])) {
            $invoice->invoiceTransactions()->update(['date' => $invoice->date]);

            // Todo: I think we don't need this
            // UpdateCustomerLastInvoicedDate::run($invoice->customer);

            GroupHydrateSalesIntervals::dispatch($invoice->group)->delay($this->hydratorsDelay);
            GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);
            GroupHydrateInvoiceIntervals::dispatch($invoice->group)->delay($this->hydratorsDelay);

            OrganisationHydrateSalesIntervals::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
            OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
            OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation)->delay($this->hydratorsDelay);

            ShopHydrateSalesIntervals::dispatch($invoice->shop)->delay($this->hydratorsDelay);
            ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
            ShopHydrateInvoiceIntervals::dispatch($invoice->shop)->delay($this->hydratorsDelay);

            if ($invoice->master_shop_id) {
                MasterShopHydrateInvoiceIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
                MasterShopHydrateSalesIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
            }

            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
                InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
                InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            }

            $invoiceDate = Carbon::parse($invoice->date);

            RedoOrganisationTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            RedoShopTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);

            if ($invoice->master_shop_id) {
                RedoMasterShopTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            }

            if ($invoice->invoiceCategory) {
                RedoInvoiceCategoryTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            }

            if ($invoice->sales_channel_id) {
                RedoSalesChannelTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            }

            if ($invoice->platform_id) {
                RedoPlatformTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            }

            // Todo: Uncomment if needed
            // foreach ($invoice->invoiceTransactions as $invoiceTransaction) {
            //     ProcessInvoiceTransactionTimeSeries::run($invoiceTransaction);
            // }

            InvoiceRecordSearch::dispatch($invoice);

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
