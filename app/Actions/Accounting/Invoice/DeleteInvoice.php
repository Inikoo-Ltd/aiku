<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Traits\WithDeleteInvoiceUI;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Helpers\Dashboard\InvalidateDashboardCaches;
use App\Actions\Accounting\InvoiceCategory\RedoInvoiceCategoryTimeSeries;
use App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeletedInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\RedoShopTimeSeries;
use App\Actions\Comms\Email\SendInvoiceDeletedNotification;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Actions\Dropshipping\Platform\RedoPlatformTimeSeries;
use App\Actions\Masters\MasterShop\RedoMasterShopTimeSeries;
use App\Actions\Ordering\SalesChannel\RedoSalesChannelTimeSeries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeletedInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeletedInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\RedoOrganisationTimeSeries;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Throwable;

class DeleteInvoice extends OrgAction
{
    use WithActionUpdate;
    use WithDeleteInvoiceUI;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        try {
            $invoice = DB::transaction(function () use ($invoice, $modelData) {
                $invoice = $this->update($invoice, $modelData);
                foreach ($invoice->invoiceTransactions as $invoiceTransaction) {
                    DeleteInvoiceTransaction::make()->action($invoiceTransaction);
                }

                $invoice->delete();

                return $invoice;
            });

            if (!$invoice->in_process) {
                StoreDeletedInvoiceHistory::run(invoice: $invoice);
                SendInvoiceDeletedNotification::dispatch($invoice);
                $this->postDeleteInvoiceHydrators($invoice);
            }
        } catch (Throwable) {
            //
        }

        UpdateCustomerLastInvoicedDate::run($invoice->customer);

        CustomerHydrateClv::dispatch($invoice->customer_id)->delay($this->hydratorsDelay);

        return $invoice;
    }

    public function htmlResponse(Invoice $invoice): RedirectResponse
    {
        if ($invoice->order) {
            return Redirect::route('grp.helpers.redirect_order', [
                $invoice->order->id
            ]);
        }


        return Redirect::route('grp.org.accounting.invoices.index', [
            $this->organisation->slug
        ]);
    }

    public function postDeleteInvoiceHydrators(Invoice $invoice): void
    {
        InvalidateDashboardCaches::run($invoice);

        $customer = $invoice->customer;
        CustomerHydrateInvoices::dispatch($invoice->customer_id);
        ShopHydrateInvoices::dispatch($customer->shop);
        ShopHydrateDeletedInvoices::dispatch($customer->shop);
        OrganisationHydrateInvoices::dispatch($customer->organisation);
        OrganisationHydrateDeletedInvoices::dispatch($customer->organisation);
        GroupHydrateInvoices::dispatch($customer->group);
        GroupHydrateDeletedInvoices::dispatch($customer->group);

        $invoiceDate = \Carbon\Carbon::parse($invoice->date);

        RedoShopTimeSeries::dispatch(shopId: $invoice->shop_id, from: $invoiceDate->toDateString(), to: $invoiceDate->toDateString())->delay(900);
        RedoOrganisationTimeSeries::dispatch(organisationId: $invoice->organisation_id, from: $invoiceDate->toDateString(), to: $invoiceDate->toDateString())->delay(900);

        if ($invoice->platform_id) {
            RedoPlatformTimeSeries::dispatch(platformId: $invoice->platform_id, from: $invoiceDate->toDateString(), to: $invoiceDate->toDateString())->delay(900);
        }

        if ($invoice->master_shop_id) {
            RedoMasterShopTimeSeries::dispatch(masterShopId: $invoice->master_shop_id, from: $invoiceDate->toDateString(), to: $invoiceDate->toDateString())->delay(900);
        }

        $invoiceCategory = $invoice->invoiceCategory;
        if ($invoiceCategory) {
            $invoiceCategory->refresh();
            InvoiceCategoryHydrateInvoices::dispatch($invoiceCategory);

            RedoInvoiceCategoryTimeSeries::dispatch($invoice->invoice_category_id, $invoiceDate->toDateString(), $invoiceDate->toDateString())->delay(2);
        }

        if ($invoice->sales_channel_id) {
            RedoSalesChannelTimeSeries::dispatch($invoice->sales_channel_id, $invoiceDate->toDateString(), $invoiceDate->toDateString())->delay(2);
        }

        if ($invoice->shipping_zone_id) {
            ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($this->hydratorsDelay);
        }
        if ($invoice->shipping_zone_schema_id) {
            ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($this->hydratorsDelay);
        }
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
            'deleted_by'   => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public string $commandSignature = 'invoice:delete {slug} {--deleted_note= : Reason for deletion} {--deleted_by= : User who deleted the invoice}';
}
