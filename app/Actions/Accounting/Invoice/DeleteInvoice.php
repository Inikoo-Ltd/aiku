<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeletedInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Comms\Email\SendInvoiceDeletedNotification;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeletedInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeletedInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class DeleteInvoice extends OrgAction
{
    use WithActionUpdate;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        try {
            $invoice = DB::transaction(function () use ($invoice, $modelData) {
                $invoice = $this->update($invoice, $modelData);
                $invoice->delete();
                foreach ($invoice->invoiceTransactions as $invoiceTransaction) {
                    DeleteInvoiceTransaction::make()->action($invoiceTransaction);
                }

                return $invoice;
            });
            StoreDeletedInvoiceHistory::run(invoice: $invoice);
            SendInvoiceDeletedNotification::dispatch($invoice);
            $this->postDeleteInvoiceHydrators($invoice);
        } catch (Throwable) {
            //
        }

        return $invoice;
    }

    public function postDeleteInvoiceHydrators(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        CustomerHydrateInvoices::dispatch($customer);
        ShopHydrateInvoices::dispatch($customer->shop);
        ShopHydrateDeletedInvoices::dispatch($customer->shop);
        OrganisationHydrateInvoices::dispatch($customer->organisation);
        OrganisationHydrateDeletedInvoices::dispatch($customer->organisation);
        GroupHydrateInvoices::dispatch($customer->group);
        GroupHydrateDeletedInvoices::dispatch($customer->group);
        $invoiceCategory = $invoice->invoiceCategory;
        if ($invoiceCategory) {
            $invoiceCategory->refresh();
            InvoiceCategoryHydrateInvoices::dispatch($invoiceCategory);
        }
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
            'deleted_by'   => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }


    public function action(Invoice $invoice, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $this->validatedData);
    }

    public string $commandSignature = 'invoice:delete {slug} {--deleted_note= : Reason for deletion} {--deleted_by= : User who deleted the invoice}';


    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Invoice $invoice */
            $invoice = Invoice::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }


        $modelData = [];

        if ($command->option('deleted_note')) {
            $modelData['deleted_note'] = $command->option('deleted_note');
        }
        if ($command->option('deleted_by')) {
            $modelData['deleted_by'] = $command->option('deleted_by');
        }

        $this->action($invoice, $modelData);

        return 0;
    }


}
