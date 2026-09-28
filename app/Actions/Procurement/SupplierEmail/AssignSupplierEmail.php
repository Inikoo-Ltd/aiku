<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail;

use App\Actions\OrgAction;
use App\Enums\Procurement\SupplierEmail\SupplierEmailRoutedByEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AssignSupplierEmail extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * The whole thread moves together, and the sender's address is remembered through it: the
     * router reads past assignments, so the next mail from that address finds its supplier alone.
     */
    public function handle(SupplierEmail $supplierEmail, OrgSupplier $orgSupplier): int
    {
        return SupplierEmail::where('organisation_id', $supplierEmail->organisation_id)
            ->when(
                $supplierEmail->gmail_thread_id,
                fn ($query) => $query->where('gmail_thread_id', $supplierEmail->gmail_thread_id),
                fn ($query) => $query->where('id', $supplierEmail->id)
            )
            ->update([
                'org_supplier_id' => $orgSupplier->id,
                'supplier_id'     => $orgSupplier->supplier_id,
                'routed_by'       => SupplierEmailRoutedByEnum::MANUAL,
                'updated_at'      => now(),
            ]);
    }

    public function rules(): array
    {
        return [
            'org_supplier_id' => ['required', 'integer', Rule::exists('org_suppliers', 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function asController(Organisation $organisation, SupplierEmail $supplierEmail, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierEmail->organisation_id === $organisation->id, 404);

        $this->handle($supplierEmail, OrgSupplier::findOrFail($this->validatedData['org_supplier_id']));

        return back();
    }
}
