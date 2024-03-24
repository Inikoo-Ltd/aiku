<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Hydrators\InvoiceHydrateUniversalSearch;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\Helpers\Address\AttachHistoricAddressToModel;
use App\Actions\Helpers\Address\StoreHistoricAddress;
use App\Actions\Market\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\OMS\Order;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rule;

class StoreInvoice extends OrgAction
{
    public function handle(
        Customer|Order $parent,
        array $modelData,
    ): Invoice {
        $billingAddress = $modelData['billing_address'];
        unset($modelData['billing_address']);


        if (class_basename($parent) == 'Customer') {
            $modelData['customer_id'] = $parent->id;
        } else {
            $modelData['customer_id'] = $parent->customer_id;
        }
        $modelData['shop_id']     = $parent->shop_id;
        $modelData['currency_id'] = $parent->shop->currency_id;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);


        /** @var \App\Models\Accounting\Invoice $invoice */
        $invoice = $parent->invoices()->create($modelData);
        $invoice->stats()->create();

        $billingAddress = StoreHistoricAddress::run($billingAddress);
        AttachHistoricAddressToModel::run($invoice, $billingAddress, ['scope' => 'billing']);

        CustomerHydrateInvoices::dispatch($invoice->customer)->delay($this->hydratorsDelay);
        ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
        InvoiceHydrateUniversalSearch::dispatch($invoice);


        return $invoice;
    }


    public function rules(): array
    {
        return [
            'number'          => ['required', 'unique:invoices', 'numeric'],
            'currency_id'     => ['required', 'exists:currencies,id'],
            'billing_address' => ['required', new ValidAddress()],
            'type'            => ['required', Rule::enum(InvoiceTypeEnum::class)],
            'exchange'        => ['required', 'numeric'],
            'net'             => ['required', 'numeric'],
            'total'           => ['required', 'numeric'],
            'source_id'       => ['sometimes', 'string'],
            'created_at'      => ['sometimes', 'date'],
            'data'            => ['sometimes', 'array'],


        ];
    }

    public function action(Customer|Order $parent, array $modelData, int $hydratorsDelay = 0): Invoice
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($parent->shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }
}
