<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSales;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateInvoices;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreInvoice extends OrgAction
{
    use WithFixedAddressActions;
    use WithOrderExchanges;
    use WithNoStrictRules;
    use WithRunInvoiceHydrators;


    private Order|Customer|RecurringBill $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Customer|Order|RecurringBill $parent, array $modelData): Invoice
    {
        data_set($modelData, 'uuid', Str::uuid());

        if (!Arr::has($modelData, 'footer')) {
            data_set($modelData, 'footer', $this->shop->invoice_footer);
        }


        if (!Arr::has($modelData, 'reference')) {
            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $this->shop,
                    modelType: SerialReferenceModelEnum::INVOICE
                )
            );
        }

        if (class_basename($parent) == 'Customer') {
            $modelData['customer_id'] = $parent->id;
        } elseif (class_basename($parent) == 'RecurringBill') {
            $modelData['customer_id'] = $parent->fulfilmentCustomer->customer_id;
        } else {
            $modelData['customer_id'] = $parent->customer_id;
        }

        if (!Arr::has($modelData, 'billing_address')) {
            if ($parent instanceof Order) {
                $modelData['billing_address'] = $parent->billingAddress;
            } elseif ($parent instanceof RecurringBill) {
                $modelData['billing_address'] = $parent->fulfilmentCustomer->customer->address;
            } else {
                $modelData['billing_address'] = $parent->address;
            }
        }

        if (!Arr::exists($modelData, 'tax_category_id')) {
            $modelData = $this->processTaxCategory($modelData, $parent);
        }


        $billingAddressData = Arr::pull($modelData, 'billing_address');

        $modelData['shop_id']     = $this->shop->id;
        $modelData['currency_id'] = $this->shop->currency_id;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        $modelData = $this->processExchanges($modelData, $this->shop);


        $date = now();
        data_set($modelData, 'date', $date, overwrite: false);
        data_set($modelData, 'tax_liability_at', $date, overwrite: false);


        $invoice = DB::transaction(function () use ($parent, $modelData, $billingAddressData) {
            /** @var Invoice $invoice */
            $invoice = $parent->invoices()->create($modelData);
            $invoice->stats()->create();
            $this->createFixedAddress(
                $invoice,
                $billingAddressData,
                'Ordering',
                'billing',
                'address_id'
            );
            $invoice->updateQuietly(
                [
                    'billing_country_id' => $invoice->address->country_id
                ]
            );

            return $invoice;
        });

        if ($this->strict) {
            CategoriseInvoice::run($invoice);
        } elseif ($invoice->invoiceCategory) { // run hydrators when category from fetch
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateSales::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
        }

        if ($invoice->customer_id) {
            CustomerHydrateInvoices::dispatch($invoice->customer)->delay($this->hydratorsDelay);
        }

        if ($invoice->customer_client_id) {
            CustomerClientHydrateInvoices::dispatch($invoice->customerClient)->delay($this->hydratorsDelay);
        }


        $this->runInvoiceHydrators($invoice);

        return $invoice;
    }

    public function processTaxCategory(array $modelData, Customer|Order|RecurringBill $parent): array
    {
        if ($parent instanceof Order || $parent instanceof RecurringBill) {
            $modelData['tax_category_id'] = $parent->tax_category_id;
        } else {
            /** @var Customer $customer */
            $customer = Customer::find($modelData['customer_id']);

            $billingAddress  = $customer->address;
            $deliveryAddress = $customer->deliveryAddress;

            data_set(
                $modelData,
                'tax_category_id',
                GetTaxCategory::run(
                    country: $this->organisation->country,
                    taxNumber: $customer->taxNumber,
                    billingAddress: $billingAddress,
                    deliveryAddress: $deliveryAddress
                )->id
            );
        }

        return $modelData;
    }


    public function rules(): array
    {
        $rules = [
            'reference'       => [
                'sometimes',
                'required',
                'max:64',
                'string',
                new IUnique(
                    table: 'invoices',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],
            'currency_id'     => ['required', 'exists:currencies,id'],
            'type'            => ['required', Rule::enum(InvoiceTypeEnum::class)],
            'net_amount'      => ['required', 'numeric'],
            'total_amount'    => ['required', 'numeric'],
            'gross_amount'    => ['required', 'numeric'],
            'rental_amount'   => ['sometimes', 'required', 'numeric'],
            'goods_amount'    => ['sometimes', 'required', 'numeric'],
            'services_amount' => ['sometimes', 'required', 'numeric'],
            'tax_amount'      => ['required', 'numeric'],
            'footer'          => ['sometimes', 'string'],
            'in_process'      => ['sometimes', 'boolean'],

            'date'             => ['sometimes', 'date'],
            'tax_liability_at' => ['sometimes', 'date'],
            'data'             => ['sometimes', 'array'],


            'sales_channel_id' => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
        ];


        if (!$this->strict) {
            $rules['reference']            = [
                'required',
                'max:64',
                'string'
            ];
            $rules['is_vip']               = ['sometimes', 'boolean'];
            $rules['as_organisation_id']   = ['sometimes', 'nullable', 'integer'];
            $rules['as_employee_id']       = ['sometimes', 'nullable', 'integer'];
            $rules['external_invoicer_id'] = ['sometimes', 'nullable', 'integer'];

            $rules['customer_name']            = ['sometimes', 'nullable', 'string'];
            $rules['customer_contact_name']    = ['sometimes', 'nullable', 'string'];
            $rules['tax_number']               = ['sometimes', 'nullable', 'string'];
            $rules['tax_number_status']        = ['sometimes', 'nullable', 'string'];
            $rules['tax_number_valid']         = ['sometimes', 'nullable', 'boolean'];
            $rules['identity_document_type']   = ['sometimes', 'nullable', 'string'];
            $rules['identity_document_number'] = ['sometimes', 'nullable', 'string'];


            $rules['invoice_category_id']                = ['sometimes', 'nullable', Rule::exists('invoice_categories', 'id')->where('organisation_id', $this->organisation->id)];
            $rules['tax_category_id']                    = ['sometimes', 'required', 'exists:tax_categories,id'];
            $rules['billing_address']                    = ['required', new ValidAddress()];
            $rules['deleted_at']                         = ['sometimes', 'nullable', 'date'];
            $rules['deleted_note']                       = ['sometimes', 'string'];
            $rules['deleted_from_deleted_invoice_fetch'] = ['sometimes', 'boolean'];
            $rules['deleted_by']                         = ['sometimes', 'nullable', 'integer'];
            $rules['original_invoice_id']                = ['sometimes', 'nullable', 'integer'];
            $rules['order_id']                           = ['sometimes', 'nullable', 'integer'];
            $rules                                       = $this->orderingAmountNoStrictFields($rules);
            $rules                                       = $this->noStrictStoreRules($rules);
        }


        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer|Order|RecurringBill $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Invoice
    {
        if (!$audit) {
            Invoice::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $parent;


        if ($parent instanceof RecurringBill) {
            $this->shop = $parent->fulfilment->shop;
            $this->initialisationFromFulfilment($parent->fulfilment, $modelData);
        } else {
            $this->initialisationFromShop($parent->shop, $modelData);
        }

        return $this->handle($parent, $this->validatedData);
    }

}
