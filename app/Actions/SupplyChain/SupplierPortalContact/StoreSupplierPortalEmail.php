<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff let one more email at the supplier log in by itself. The email goes into the supplier's other emails, so the
 * supplier's contact details stay the one list.
 */
class StoreSupplierPortalEmail extends OrgAction
{
    private Supplier $supplier;

    public function handle(Supplier $supplier, string $email, ?int $userId = null): SupplierPortalContact
    {
        $email = mb_strtolower(trim($email));

        if (!in_array($email, SyncSupplierPortalContact::emails($supplier), true)) {
            UpdateSupplier::make()->action($supplier, ['extra_emails' => [...($supplier->data['extra_emails'] ?? []), $email]]);
        }

        $contact = SupplierPortalContact::firstOrNew(['supplier_id' => $supplier->id, 'email' => $email]);
        $contact->fill([
            'group_id'              => $supplier->group_id,
            'authorised_by_user_id' => $userId ?? $contact->authorised_by_user_id,
            'revoked_at'            => null,
            'revoked_by_user_id'    => null,
        ])->save();

        return $contact;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255', SyncSupplierPortalContact::notUsedByAnotherSupplierRule($this->supplier)]];
    }

    public function asController(Supplier $supplier, ActionRequest $request): SupplierPortalContact
    {
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData['email'], $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
