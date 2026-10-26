<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff allow an email to ask for the supplier's private link. Adding an email revoked before allows it again.
 */
class StoreSupplierPortalContact extends OrgAction
{
    /**
     * @param  array{email: string, name?: string|null, authorised_by_user_id?: int|null}  $modelData
     */
    public function handle(Supplier $supplier, array $modelData): SupplierPortalContact
    {
        $contact = SupplierPortalContact::firstOrNew(['supplier_id' => $supplier->id, 'email' => mb_strtolower(trim($modelData['email']))]);
        $contact->fill([
            'group_id'              => $supplier->group_id,
            'name'                  => $modelData['name'] ?? $contact->name,
            'authorised_by_user_id' => $modelData['authorised_by_user_id'] ?? null,
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
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name'  => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(Supplier $supplier, ActionRequest $request): SupplierPortalContact
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, [...$this->validatedData, 'authorised_by_user_id' => $request->user()->id]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
