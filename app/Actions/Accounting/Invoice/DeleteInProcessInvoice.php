<?php
/*
 * author Arya Permana - Kirin
 * created on 09-04-2025-13h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Comms\Email\SendInvoiceDeletedNotification;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteInProcessInvoice extends OrgAction
{
    use WithActionUpdate;

    private Invoice $invoice;

    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        $customer = $invoice->customer;
        $invoiceCategory = $invoice->invoiceCategory;

        $invoice = DB::transaction(function () use ($invoice, $modelData) {
                    $invoice = $this->update($invoice, $modelData);
                    $invoice->invoiceTransactions()->delete();
            
                    $invoice->customer->auditEvent    = 'delete';
                    $invoice->customer->isCustomEvent = true;
                    $invoice->customer->auditCustomOld = [
                        'invoice' => $invoice->reference
                    ];
                    $invoice->customer->auditCustomNew = [
                        'invoice' => __("The invoice :ref has been deleted.", ['ref' => $invoice->reference])
                    ];
                    Event::dispatch(AuditCustom::class, [$invoice->customer]);

                    SendInvoiceDeletedNotification::dispatch($invoice);
            
                    $invoice->delete();
                });

        $customer->refresh();
        
        CustomerHydrateInvoices::dispatch($customer);
        ShopHydrateInvoices::dispatch($customer->shop);
        OrganisationHydrateInvoices::dispatch($customer->organisation);
        GroupHydrateInvoices::dispatch($customer->group);

        if ($invoiceCategory) {
            $invoiceCategory->refresh();
            InvoiceCategoryHydrateInvoices::dispatch($invoiceCategory);
        }

        return $invoice;
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['required', 'string', 'max:4000'],
            'deleted_by'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->invoice = $invoice;
        $this->set('user_id', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }


    public function action(Invoice $invoice, array $modelData): Invoice
    {
        $this->invoice = $invoice;
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
