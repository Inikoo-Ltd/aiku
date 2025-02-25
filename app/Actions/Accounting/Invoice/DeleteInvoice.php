<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteInvoice extends OrgAction
{
    use WithActionUpdate;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $invoice = $this->update($invoice, $modelData);
        $invoice->invoiceTransactions()->delete();
        $invoice->delete();
        CustomerHydrateInvoices::dispatch($invoice->customer);

        return $invoice;
    }

    public function rules(): array
    {
        return [
            'deleted_notes' => ['required', 'string', 'max:4000'],
            'deleted_by'    => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->set('user_id', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }


    public function action(Invoice $invoice, array $modelData): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $this->validatedData);
    }
}
