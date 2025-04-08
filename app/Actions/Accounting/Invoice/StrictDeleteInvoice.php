<?php
/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-15h-54m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StrictDeleteInvoice extends OrgAction
{
    use WithActionUpdate;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $deleteConfirmation = Arr::pull($modelData, 'delete_confirmation');

        if(strtolower(trim($deleteConfirmation ?? '')) === strtolower($invoice->reference)){
            $invoice = $this->update($invoice, $modelData);
            $invoice->invoiceTransactions()->delete();
            $invoice->delete();
            CustomerHydrateInvoices::dispatch($invoice->customer);
            ShopHydrateInvoices::dispatch($invoice->shop);
            OrganisationHydrateInvoices::dispatch($invoice->organisation);
            GroupHydrateInvoices::dispatch($invoice->group);
            if ($invoice->invoiceCategory) {
                InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory);
            }
        } else {
            abort(419);
        }

        return $invoice;
    }

    public function rules(): array
    {
        return [
            // 'deleted_note' => ['required', 'string', 'max:4000'],
            'delete_confirmation'   => ['required'],
            'deleted_by'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }
}
