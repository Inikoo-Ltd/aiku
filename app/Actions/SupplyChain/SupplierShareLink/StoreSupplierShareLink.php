<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Makes a private link for a supplier. Staff give it for months; the supplier sign-in page emails one for days.
 */
class StoreSupplierShareLink extends OrgAction
{
    /**
     * 0 is forever.
     */
    public const array MONTHS = [1, 3, 6, 12, 0];

    /**
     * @param  array{months?: int, days?: int, email?: string|null, created_by_user_id?: int|null, supplier_portal_contact_id?: int|null}  $modelData
     * @return array{link: SupplierShareLink, url: string}
     */
    public function handle(Supplier $supplier, array $modelData): array
    {
        $token = Str::random(48);

        $link = SupplierShareLink::create([
            'group_id'           => $supplier->group_id,
            'supplier_id'        => $supplier->id,
            'token_hash'         => SupplierShareLink::hashToken($token),
            'token_prefix'       => substr($token, 0, 6),
            'email'              => $modelData['email'] ?? null,
            'expires_at'         => match (true) {
                isset($modelData['days'])          => now()->addDays((int) $modelData['days']),
                (int) $modelData['months'] === 0 => null,
                default                            => now()->addMonthsNoOverflow((int) $modelData['months']),
            },
            'created_by_user_id' => $modelData['created_by_user_id'] ?? null,
            'supplier_portal_contact_id' => $modelData['supplier_portal_contact_id'] ?? null,
        ]);

        return ['link' => $link, 'url' => route('grp.supplier_share.show', $token)];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', Rule::in(self::MONTHS)],
            'email'  => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array{link: SupplierShareLink, url: string}
     */
    public function asController(Supplier $supplier, ActionRequest $request): array
    {
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, [...$this->validatedData, 'created_by_user_id' => $request->user()->id]);
    }

    /**
     * @param  array{link: SupplierShareLink, url: string}  $result
     * @return array{url: string}
     */
    public function jsonResponse(array $result): array
    {
        return ['url' => $result['url']];
    }
}
