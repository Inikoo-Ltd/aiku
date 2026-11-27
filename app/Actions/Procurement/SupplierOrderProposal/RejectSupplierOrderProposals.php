<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\SupplierOrderProposal;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalOrderNow;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Models\Procurement\SupplierOrderProposal;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buyer rejects lines a supplier proposed, with the reason the supplier reads on its portal. The product is not
 * offered to the supplier again for GetSupplierPortalOrderNow::REJECTED_REST_DAYS.
 */
class RejectSupplierOrderProposals extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * @param  list<int>  $ids
     */
    public function handle(Organisation $organisation, array $ids, string $reason, int $userId): int
    {
        return SupplierOrderProposal::where('organisation_id', $organisation->id)
            ->whereIn('id', $ids)
            ->where('state', SupplierOrderProposalStateEnum::PENDING)
            ->update([
                'state'              => SupplierOrderProposalStateEnum::REJECTED->value,
                'reject_reason'      => $reason,
                'decided_by_user_id' => $userId,
                'decided_at'         => now(),
                'updated_at'         => now(),
            ]);
    }

    public function rules(): array
    {
        return [
            'ids'    => ['required', 'array', 'min:1', 'max:500'],
            'ids.*'  => ['required', 'integer', 'distinct'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'reason.required' => __('Tell the supplier why, it reads this on its page. It will not be offered these products again for :days days.', ['days' => GetSupplierPortalOrderNow::REJECTED_REST_DAYS]),
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, array_map('intval', $this->validatedData['ids']), trim($this->validatedData['reason']), $request->user()->id);
    }

    /**
     * @return array{rejected: int}
     */
    public function jsonResponse(int $rejected): array
    {
        return ['rejected' => $rejected];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
