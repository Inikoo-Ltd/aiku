<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;

trait WithInvoiceAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $invoice = $this->getInvoiceToAuthorise($request);
        if (!$invoice) {
            return $request->user()->authTo("accounting.{$this->organisation->id}.view");
        }

        $isEdit = str_ends_with($request->route()->getName(), '.edit');

        $this->canEdit = $request->user()->authTo($this->getInvoicePermissions($invoice, 'edit'));

        return $isEdit ? $this->canEdit : $request->user()->authTo($this->getInvoicePermissions($invoice, 'view'));
    }

    protected function getInvoiceToAuthorise(ActionRequest $request): ?Invoice
    {
        $invoice = $request->route('refund') ?? $request->route('invoice');
        if ($invoice instanceof Invoice) {
            return $invoice;
        }

        $slug = $request->route('invoiceSlug');

        return $slug ? Invoice::withTrashed()->where('slug', $slug)->first() : null;
    }

    protected function getInvoicePermissions(Invoice $invoice, string $level): array
    {
        $permissions = [
            "accounting.$invoice->organisation_id.$level",
            "crm.$invoice->shop_id.$level",
            "orders.$invoice->shop_id.$level",
        ];

        if ($fulfilmentId = $invoice->shop->fulfilment?->id) {
            $permissions[] = "fulfilment-shop.$fulfilmentId.$level";
        }

        if ($level === 'view') {
            $permissions[] = "dispatching.$invoice->organisation_id.view";
            if ($buyerOrganisationId = $invoice->customer?->as_organisation_id) {
                $permissions[] = "procurement.$buyerOrganisationId.view";
            }
        }

        return $permissions;
    }
}
