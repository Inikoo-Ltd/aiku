<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 11:19:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Accounting\Invoice\CategoriseInvoice;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceCategories;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceCategories;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Console\Command;

class DeleteInvoiceCategory extends OrgAction
{
    public function handle(InvoiceCategory $invoiceCategory): InvoiceCategory
    {
        $organisation = $invoiceCategory->organisation; // can be null if group-level only
        $group        = $invoiceCategory->group;        // expected to exist

        // Delete the invoice category. FK on invoices is nullOnDelete, so related invoices are unassigned automatically
        $invoiceCategory->delete();

        // Recompute stats at organisation and group level
        if ($organisation) {
            OrganisationHydrateInvoiceCategories::dispatch($organisation)->delay($this->hydratorsDelay);
        }
        if ($group) {
            GroupHydrateInvoiceCategories::dispatch($group)->delay($this->hydratorsDelay);
        }

        return $invoiceCategory;
    }


    public function reCategorizeInvoices(int $invoiceCategoryID): void
    {


        Invoice::where('invoice_category_id', $invoiceCategoryID)
            ->orderBy('id')
            ->chunkById(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    CategoriseInvoice::make()->handle($invoice);
                }
            });
    }

    public function getCommandSignature(): string
    {
        return 'invoice_category:delete {id}';
    }

    public function asCommand(Command $command): int
    {
        /** @var InvoiceCategory $invoiceCategory */
        $invoiceCategory = InvoiceCategory::find($command->argument('id'));

        if($invoiceCategory) {
            $this->handle($invoiceCategory);
        }else{
            $this->reCategorizeInvoices($command->argument('id'));
        }


        $command->info("Deleted invoice category: $invoiceCategory->slug");

        return 0;
    }
}
