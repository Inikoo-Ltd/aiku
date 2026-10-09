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
    use WithInvoiceEditPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $invoice = $this->getInvoiceToAuthorise($request);
        if (!$invoice) {
            return $request->user()->authTo("accounting.{$this->organisation->id}.view");
        }

        $routeName = $request->route()->getName();

        $this->canEdit = $request->user()->authTo($this->getInvoiceEditPermissions($invoice));

        if (str_ends_with($routeName, '.edit')) {
            return $this->canEdit;
        }

        if (str_starts_with($routeName, 'grp.org.accounting.')) {
            $permissions = ["accounting.$invoice->organisation_id.view"];
            if ($buyerOrganisationId = $invoice->customer?->as_organisation_id) {
                $permissions[] = "procurement.$buyerOrganisationId.view";
            }

            return $request->user()->authTo($permissions);
        }

        return $request->user()->authTo($this->getInvoiceViewPermissions($invoice));
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

    protected function getInvoiceViewPermissions(Invoice $invoice): array
    {
        $permissions = [
            "accounting.$invoice->organisation_id.view",
            "crm.$invoice->shop_id.view",
            "orders.$invoice->shop_id.view",
        ];

        if ($fulfilmentId = $invoice->shop->fulfilment?->id) {
            $permissions[] = "fulfilment-shop.$fulfilmentId.view";
        }

        foreach ($invoice->organisation->warehouses()->pluck('id') as $warehouseId) {
            $permissions[] = "dispatching.$warehouseId.view";
        }
        if ($buyerOrganisationId = $invoice->customer?->as_organisation_id) {
            $permissions[] = "procurement.$buyerOrganisationId.view";
        }

        return $permissions;
    }
}
