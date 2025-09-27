<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
use App\Actions\Comms\Email\SendInvoiceToFulfilmentCustomerEmail;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateInvoices;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Accounting\Invoice\InvoicePayDetailedStatusEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
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
        data_set($modelData, 'ulid', Str::ulid());

        data_set($modelData, 'pay_status', InvoicePayStatusEnum::UNPAID);
        data_set($modelData, 'pay_detailed_status', InvoicePayDetailedStatusEnum::UNPAID);

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
            $customer = $parent;
        } elseif (class_basename($parent) == 'RecurringBill') {
            $customer = $parent->fulfilmentCustomer->customer;
        } else {
            $customer = $parent->customer;
        }


        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'customer_name', $customer->name, false);
        data_set($modelData, 'customer_contact_name', $customer->contact_name, false);
        data_set($modelData, 'identity_document_type', $customer->identity_document_type, false);
        data_set($modelData, 'identity_document_number', $customer->identity_document_number, false);


        $taxNumber = $customer->taxNumber;
        if ($taxNumber) {
            data_set($modelData, 'tax_number', $taxNumber->getFormattedTaxNumber(), false);
            data_set($modelData, 'tax_number_status', $taxNumber->status, false);
            data_set($modelData, 'tax_number_valid', $taxNumber->valid, false);
        } else {
            data_set($modelData, 'tax_number', null, false);
            data_set($modelData, 'tax_number_status', 'na', false);
            data_set($modelData, 'tax_number_valid', false, false);
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

        $deliveryAddressData = null;
        if (Arr::has($modelData, 'delivery_address')) {
            $deliveryAddressData = Arr::pull($modelData, 'delivery_address');
        }


        if (!Arr::exists($modelData, 'tax_category_id')) {
            $modelData = $this->processTaxCategory($modelData, $parent);
        }


        $billingAddressData = Arr::pull($modelData, 'billing_address');

        $modelData['shop_id']        = $this->shop->id;
        $modelData['master_shop_id'] = $this->shop->master_shop_id;
        $modelData['currency_id']    = $this->shop->currency_id;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        $modelData = $this->processExchanges($modelData, $this->shop);


        $date = now();
        data_set($modelData, 'date', $date, overwrite: false);
        data_set($modelData, 'tax_liability_at', $date, overwrite: false);
        data_set($modelData, 'effective_total', Arr::get($modelData, 'total_amount', 0));


        $invoice = DB::transaction(function () use ($parent, $modelData, $billingAddressData, $deliveryAddressData) {
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


            if ($deliveryAddressData) {
                $this->createFixedAddress(
                    $invoice,
                    $deliveryAddressData,
                    'Ordering',
                    'delivery',
                    'delivery_address_id'
                );

                $invoice->updateQuietly(
                    [
                        'delivery_country_id' => $invoice->deliveryAddress->country_id
                    ]
                );
            }

            return $invoice;
        });

        if ($this->strict) {
            CategoriseInvoice::run($invoice);
        } elseif ($invoice->invoiceCategory) { // run hydrators when category from fetch
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
        }

        if ($invoice->customer_id) {
            CustomerHydrateInvoices::dispatch($invoice->customer)->delay($this->hydratorsDelay);
        }

        if ($invoice->customer_client_id) {
            CustomerClientHydrateInvoices::dispatch($invoice->customerClient)->delay($this->hydratorsDelay);
        }


        $this->runInvoiceHydrators($invoice);
        if ($invoice->shop->type == 'fulfilment') {
            SendInvoiceToFulfilmentCustomerEmail::dispatch($invoice);
        }

        if (!$invoice->in_procexss) {
            if ($invoice->shipping_zone_id) {
                ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($this->hydratorsDelay);
            }
            if ($invoice->shipping_zone_schema_id) {
                ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($this->hydratorsDelay);
            }
        }

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
            'reference'                 => [
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
            'currency_id'               => ['required', 'exists:currencies,id'],
            'type'                      => ['required', Rule::enum(InvoiceTypeEnum::class)],
            'net_amount'                => ['required', 'numeric'],
            'total_amount'              => ['required', 'numeric'],
            'gross_amount'              => ['required', 'numeric'],
            'rental_amount'             => ['sometimes', 'required', 'numeric'],
            'goods_amount'              => ['sometimes', 'required', 'numeric'],
            'insurance_amount'          => ['sometimes', 'required', 'numeric'],
            'shipping_amount'           => ['sometimes', 'required', 'numeric'],
            'services_amount'           => ['sometimes', 'required', 'numeric'],
            'charges_amount'            => ['sometimes', 'required', 'numeric'],
            'tax_amount'                => ['required', 'numeric'],
            'footer'                    => ['sometimes', 'string'],
            'in_process'                => ['sometimes', 'boolean'],
            'date'                      => ['sometimes', 'date'],
            'tax_liability_at'          => ['sometimes', 'date'],
            'data'                      => ['sometimes', 'array'],
            'customer_sales_channel_id' => [
                'sometimes',
                'nullable',
                'exists:customer_sales_channels,id',
            ],
            'platform_id'               => [
                'sometimes',
                'nullable',
                'exists:platforms,id',
            ],

            'sales_channel_id' => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],

            'shipping_zone_schema_id'   => ['sometimes', 'nullable'],
            'shipping_zone_id'          => ['sometimes', 'nullable'],

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

            $rules['invoice_category_id'] = ['sometimes', 'nullable', Rule::exists('invoice_categories', 'id')->where('organisation_id', $this->organisation->id)];
            $rules['tax_category_id']     = ['sometimes', 'required', 'exists:tax_categories,id'];
            $rules['billing_address']     = ['required', new ValidAddress()];
            $rules['delivery_address']    = ['sometimes', new ValidAddress()];

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
