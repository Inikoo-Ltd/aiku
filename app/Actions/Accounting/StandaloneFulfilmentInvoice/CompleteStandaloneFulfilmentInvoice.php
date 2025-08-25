<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-09h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoice;

use App\Actions\Accounting\Invoice\Search\InvoiceRecordSearch;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CompleteStandaloneFulfilmentInvoice extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice) {
            foreach ($invoice->invoiceTransactions as $transaction) {
                $this->update($transaction, [
                    'date'       => now(),
                    'in_process' => false,
                    'grp_net_amount' => $transaction->net_amount * $transaction->grp_exchange,
                    'org_net_amount' => $transaction->net_amount * $transaction->org_exchange
                ]);
            }

            return $this->update($invoice, [
                'date'       => now(),
                'in_process' => false,
                'grp_net_amount' => $invoice->net_amount * $invoice->grp_exchange,
                'org_net_amount' => $invoice->net_amount * $invoice->org_exchange
            ]);
        });

        ShopHydrateInvoices::dispatch($invoice->shop);
        OrganisationHydrateInvoices::dispatch($invoice->organisation);
        GroupHydrateInvoices::dispatch($invoice->group);

        if ($invoice->invoiceCategory) {
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory);
            InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory);
        }

        ShopHydrateSalesIntervals::dispatch($invoice->shop);
        OrganisationHydrateSalesIntervals::dispatch($invoice->organisation);
        GroupHydrateSalesIntervals::dispatch($invoice->group);

        ShopHydrateInvoiceIntervals::dispatch($invoice->shop);
        OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation);
        GroupHydrateInvoiceIntervals::dispatch($invoice->group);

        if($invoice->master_shop_id){
            MasterShopHydrateSalesIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
            MasterShopHydrateInvoiceIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
        }


        InvoiceRecordSearch::dispatch($invoice);


        return $invoice;
    }



    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if (!$this->asAction) {
            $invoice = $request->route()->parameter('invoice');

            if (!$invoice->customer->fulfilmentCustomer->rentalAgreement) {
                $validator->errors()->add('invoice', 'Invoice customer must have rental agreement');
            }

            if (!in_array($invoice->customer->status, [CustomerStatusEnum::APPROVED,CustomerStatusEnum::BANNED ])) {
                $validator->errors()->add('invoice', 'Invoice must be from an approved customer');
            }

            if ($invoice->shop->type != ShopTypeEnum::FULFILMENT) {
                $validator->errors()->add('invoice', 'Invoice must be from a fulfilment shop');
            }

            if ($invoice->invoiceTransactions->count() == 0) {
                $validator->errors()->add('invoice', 'Invoice must have at least one transaction');
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $invoice): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, []);

        return $this->handle($invoice);
    }

    public function htmlResponse(Invoice $invoice): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.show', [
            'organisation'       => $invoice->organisation->slug,
            'fulfilment'         => $invoice->shop->fulfilment->slug,
            'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug,
            'invoice'            => $invoice->slug
        ]);
    }

}
