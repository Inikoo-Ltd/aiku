<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Search\InvoiceRecordSearch;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
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
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Models\Accounting\Invoice;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateInvoice extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithNoStrictRules;

    private Invoice $invoice;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $oldShippingZoneSchemaId = $invoice->shipping_zone_schema_id;
        $oldShippingZoneId       = $invoice->shipping_zone_id;

        $billingAddressData = Arr::pull($modelData, 'billing_address');

        $deliveryAddressData = Arr::pull($modelData, 'delivery_address');


        $invoice = $this->update($invoice, $modelData, ['data']);

        if ($billingAddressData) {
            $this->updateFixedAddress(
                $invoice,
                $invoice->billingAddress,
                $billingAddressData,
                'Ordering',
                'billing',
                'address_id'
            );
            $invoice->update([
                'billing_country_id' => $invoice->billingAddress->country_id,
            ]);
        }


        if ($deliveryAddressData) {
            $this->updateFixedAddress(
                $invoice,
                $invoice->deliveryAddress,
                $deliveryAddressData,
                'Ordering',
                'delivery',
                'delivery_address_id'
            );

            $invoice->update([
                'delivery_country_id' => $invoice->deliveryAddress->country_id,
            ]);
        }


        $changes = Arr::except($invoice->getChanges(), ['updated_at', 'last_fetched_at']);


        if (count($changes) > 0) {
            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
                InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
                InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            }

            ShopHydrateSalesIntervals::dispatch($invoice->shop)->delay($this->hydratorsDelay);
            ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
            ShopHydrateInvoiceIntervals::dispatch($invoice->shop)->delay($this->hydratorsDelay);

            if ($invoice->master_shop_id) {
                MasterShopHydrateInvoiceIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
                MasterShopHydrateSalesIntervals::dispatch($invoice->master_shop_id)->delay($this->hydratorsDelay);
            }

            OrganisationHydrateSalesIntervals::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
            OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
            OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation)->delay($this->hydratorsDelay);

            GroupHydrateSalesIntervals::dispatch($invoice->group)->delay($this->hydratorsDelay);
            GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);
            GroupHydrateInvoiceIntervals::dispatch($invoice->group)->delay($this->hydratorsDelay);

            if (Arr::hasAny($changes, [
                'reference',
                'total_amount',
                'type',
                'date',
                'currency_id',
                'in_process',
                'slug',
                'customer_name',
                'tax_number',
                'tax_number_valid'

            ])) {
                InvoiceRecordSearch::dispatch($invoice);
            }
        }

        if (Arr::hasAny($changes, ['billing_country_id', 'sales_channel_id', 'is_vip', 'external_invoicer_id'])) {
            CategoriseInvoice::run($invoice);
        }

        if (Arr::has($changes, 'shipping_zone_schema_id')) {
            if ($oldShippingZoneSchemaId) {
                ShippingZoneSchemaHydrateUsageInInvoices::dispatch($oldShippingZoneSchemaId)->delay($this->hydratorsDelay);
            }

            if ($invoice->shipping_zone_schema_id) {
                ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($this->hydratorsDelay);
            }
        }

        if (Arr::has($changes, 'shipping_zone_id')) {
            if ($oldShippingZoneId) {
                ShippingZoneHydrateUsageInInvoices::dispatch($oldShippingZoneId)->delay($this->hydratorsDelay);
            }

            if ($invoice->shipping_zone_id) {
                ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($this->hydratorsDelay);
            }
        }

        return $invoice;
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'string',
                'max:64',
                new IUnique(
                    table: 'invoices',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'value' => $this->invoice->id, 'operator' => '!=']
                    ]
                ),
            ],

            'payment_amount' => ['sometimes', 'numeric'],


            'date'                    => ['sometimes', 'date'],
            'tax_liability_at'        => ['sometimes', 'date'],
            'footer'                  => ['sometimes', 'string'],
            'billing_address'         => ['sometimes', 'required', new ValidAddress()],
            'delivery_address'        => ['sometimes', 'required', new ValidAddress()],
            'sales_channel_id'        => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
            'shipping_zone_schema_id' => ['sometimes', 'nullable'],
            'shipping_zone_id'        => ['sometimes', 'nullable'],

        ];

        if (!$this->strict) {
            $rules['external_invoicer_id']               = ['sometimes', 'nullable', 'integer'];
            $rules['currency_id']                        = ['sometimes', 'required', 'exists:currencies,id'];
            $rules['net_amount']                         = ['sometimes', 'required', 'numeric'];
            $rules['total_amount']                       = ['sometimes', 'required', 'numeric'];
            $rules['gross_amount']                       = ['sometimes', 'required', 'numeric'];
            $rules['rental_amount']                      = ['sometimes', 'required', 'numeric'];
            $rules['goods_amount']                       = ['sometimes', 'required', 'numeric'];
            $rules['services_amount']                    = ['sometimes', 'required', 'numeric'];
            $rules['tax_amount']                         = ['sometimes', 'required', 'numeric'];
            $rules['footer']                             = ['sometimes', 'string'];
            $rules['data']                               = ['sometimes', 'array'];
            $rules['original_invoice_id']                = ['sometimes', 'nullable', 'integer'];
            $rules['is_vip']                             = ['sometimes', 'boolean'];
            $rules['as_organisation_id']                 = ['sometimes', 'nullable', 'integer'];
            $rules['as_employee_id']                     = ['sometimes', 'nullable', 'integer'];
            $rules['invoice_category_id']                = ['sometimes', 'nullable', Rule::exists('invoice_categories', 'id')->where('organisation_id', $this->organisation->id)];
            $rules['deleted_at']                         = ['sometimes', 'nullable', 'date'];
            $rules['deleted_note']                       = ['sometimes', 'string'];
            $rules['deleted_from_deleted_invoice_fetch'] = ['sometimes', 'boolean'];
            $rules['order_id']                           = ['sometimes', 'nullable', 'integer'];
            $rules['deleted_by']                         = ['sometimes', 'nullable', 'integer'];
            $rules['customer_name']                      = ['sometimes', 'nullable', 'string'];
            $rules['customer_contact_name']              = ['sometimes', 'nullable', 'string'];
            $rules['tax_number']                         = ['sometimes', 'nullable', 'string'];
            $rules['tax_number_status']                  = ['sometimes', 'nullable', 'string'];
            $rules['tax_number_valid']                   = ['sometimes', 'nullable', 'boolean'];
            $rules['identity_document_type']             = ['sometimes', 'nullable', 'string'];
            $rules['identity_document_number']           = ['sometimes', 'nullable', 'string'];


            $rules['reference'] = [
                'sometimes',
                'string',
                'max:64',
            ];
            $rules              = $this->orderNoStrictFields($rules);
            $rules              = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->invoice = $invoice;
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }

    public function action(Invoice $invoice, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Invoice
    {
        if (!$audit) {
            Invoice::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->invoice        = $invoice;
        $this->strict         = $strict;

        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $this->validatedData);
    }

    public function jsonResponse(Invoice $invoice): InvoicesResource
    {
        return new InvoicesResource($invoice);
    }
}
