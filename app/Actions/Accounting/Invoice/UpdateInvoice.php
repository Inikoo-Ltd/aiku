<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Helpers\Dashboard\InvalidateDashboardCaches;
use App\Actions\Accounting\InvoiceCategory\RedoInvoiceCategoryTimeSeries;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\SalesChannel\RedoSalesChannelTimeSeries;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class UpdateInvoice extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithNoStrictRules;

    private Invoice $invoice;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        if (Arr::has($modelData, 'date')) {
            UpdateInvoiceDate::run($invoice, [
                'date' => Arr::pull($modelData, 'date')
            ]);
        }

        $oldShippingZoneSchemaId = $invoice->shipping_zone_schema_id;
        $oldShippingZoneId       = $invoice->shipping_zone_id;
        $oldDate                 = $invoice->date;

        $newBillAddressData = Arr::pull($modelData, 'invoice_billing_address');
        $oldBillAddressData = clone $invoice->address;

        // TODO: Refactor invoice_billing_address logic after done migrating completely from Aurora
        if ($newBillAddressData) {
            $parent = $invoice->order ?? $invoice;
            if ($parent->address_id != $parent->delivery_address_id) {
                $newAddress = UpdateAddress::run($invoice->address, $newBillAddressData, null, 'contact address');
            } else {
                $newBillAddressData = array_merge(
                    $newBillAddressData,
                    Arr::only($invoice->address->toArray(), ['group_id', 'multiplicity', 'is_fixed', 'fixed_scope'])
                );
                data_set($newBillAddressData, 'usage', 0);
                data_set($newBillAddressData, 'fixed_usage', 0);

                $newAddress = Address::create($newBillAddressData);
            }

            data_set($modelData, 'billing_address', $newAddress);
        }

        $billingAddressData = Arr::pull($modelData, 'billing_address');

        $deliveryAddressData = Arr::pull($modelData, 'delivery_address');

        $invoice = $this->update($invoice, $modelData, ['data']);

        if ($billingAddressData) {
            $staleInvoice = clone $invoice;

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

            $customer = $invoice->customer;


            $taxCategoryInvoice = GetTaxCategory::run(
                country: $invoice->organisation->country,
                taxNumber: $customer->taxNumber,
                billingAddress: $invoice->billingAddress,
                deliveryAddress: $invoice->deliveryAddress,
                isRe: $customer?->is_re,
            );
            $invoice->update([
                'tax_category_id' => $taxCategoryInvoice->id,
            ]);

            CalculateInvoiceTotals::run($invoice);
            RunInvoiceHydrators::run($invoice, $this->hydratorsDelay);

            $this->auditBillingAddressUpdate($invoice, $oldBillAddressData, $billingAddressData, $staleInvoice);
            $this->auditBillingAddressUpdate($customer, $oldBillAddressData, $billingAddressData);

            if ($order = $invoice->order) {
                $staleOrder = clone $order;

                $this->updateFixedAddress(
                    $order,
                    $order->billingAddress,
                    $billingAddressData,
                    'Ordering',
                    'billing',
                    'billing_address_id'
                );

                $order->update([
                    'tax_category_id' => $taxCategoryInvoice->id,
                ]);
                $order->refresh();
                CalculateOrderTotalAmounts::run($order, false, false);

                $this->auditBillingAddressUpdate($order, $oldBillAddressData, $billingAddressData, $staleOrder);
            }
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
            InvalidateDashboardCaches::run($invoice);

            $invoiceDate    = \Carbon\Carbon::parse($invoice->date);
            $newDateString  = $invoiceDate->toDateString();
            $dateHasChanged = Arr::has($changes, 'date');
            $oldDateString  = $dateHasChanged ? \Carbon\Carbon::parse($oldDate)->toDateString() : null;


            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);


                if ($dateHasChanged) {
                    RedoInvoiceCategoryTimeSeries::dispatch($oldDateString, $oldDateString)->delay($this->hydratorsDelay);
                }
                RedoInvoiceCategoryTimeSeries::dispatch($newDateString, $newDateString)->delay($this->hydratorsDelay);
            }

            if ($invoice->sales_channel_id) {
                if ($dateHasChanged) {
                    RedoSalesChannelTimeSeries::dispatch($oldDateString, $oldDateString)->delay($this->hydratorsDelay);
                }
                RedoSalesChannelTimeSeries::dispatch($newDateString, $newDateString)->delay($this->hydratorsDelay);
            }

            ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
            OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
            GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);
        }


        if (Arr::hasAny($changes, ['in_process', 'net_amount', 'org_net_amount', 'grp_net_amount'])) {
            CustomerHydrateClv::dispatch($invoice->customer_id)->delay($this->hydratorsDelay);
        }

        if (Arr::hasAny($changes, ['billing_country_id', 'sales_channel_id', 'is_vip', 'external_invoicer_id'])) {
            CategoriseInvoice::run($invoice);
        }

        if (Arr::has($changes, 'date')) {
            InvoiceTransaction::where('invoice_id', $invoice->id)->update([
                'date' => $invoice->date,
            ]);
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

    private function auditBillingAddressUpdate(Customer|Invoice|Order $parent, Address $oldBillAddressData, Address $newBillAddressData, Invoice|Order|null $stale = null): void
    {
        $parent->auditEvent    = 'invoice_billing_address_update';
        $parent->isCustomEvent = true;

        $oldData = Arr::except($oldBillAddressData->toArray(), ['updated_at']);
        $newData = Arr::except($newBillAddressData->toArray(), ['updated_at']);

        if ($parent instanceof Customer) {
            $newData = array_merge($newData, [
                'affected_invoice' => $this->invoice->reference,
            ]);
        } elseif ($stale) {
            $oldData = array_merge($oldData, [
                'total_amount' => $stale->total_amount,
            ]);
            $newData = array_merge($newData, [
                'total_amount' => $parent->total_amount,
            ]);
        }

        $parent->auditCustomOld = $oldData;
        $parent->auditCustomNew = $newData;

        Event::dispatch(new AuditCustom($parent));
    }

    public function rules(): array
    {
        $rules = [
            'reference'                => [
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
            'payment_amount'           => ['sometimes', 'numeric'],
            'date'                     => ['sometimes', 'date'],
            'tax_liability_at'         => ['sometimes', 'date'],
            'footer'                   => ['sometimes', 'string'],
            'billing_address'          => ['sometimes', 'required', new ValidAddress()],
            'invoice_billing_address'  => ['sometimes', 'required', new ValidAddress()], // TODO: consolidate(rename) this fields names after aurora migration
            'delivery_address'         => ['sometimes', 'required', new ValidAddress()],
            'sales_channel_id'         => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
            'shipping_zone_schema_id'  => ['sometimes', 'nullable'],
            'shipping_zone_id'         => ['sometimes', 'nullable'],
            'identity_document_number' => ['sometimes', 'nullable', 'string'],

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
