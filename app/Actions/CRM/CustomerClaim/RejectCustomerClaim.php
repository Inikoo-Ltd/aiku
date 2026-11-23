<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Accounting\Invoice\DeleteInProcessInvoice;
use App\Actions\CRM\CustomerClaim\UI\ShowCustomerClaim;
use App\Actions\OrgAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RejectCustomerClaim extends OrgAction
{
    public const array REASONS = ['outside_window', 'photos_missing', 'not_confirmed', 'other'];

    private CustomerClaim $customerClaim;

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'outside_window' => __('Outside the claim window'),
            'photos_missing' => __('Photos missing or unclear'),
            'not_confirmed'  => __('We could not confirm the problem'),
            default          => __('Other'),
        };
    }

    public function handle(CustomerClaim $claim, User $reviewer, string $reason, ?string $note = null): CustomerClaim
    {
        if ($claim->state !== CustomerClaimStateEnum::IN_REVIEW) {
            return $claim;
        }

        if ($claim->refund?->in_process) {
            DeleteInProcessInvoice::make()->action($claim->refund, ['deleted_note' => __('Claim :reference rejected', ['reference' => $claim->reference]), 'deleted_by' => $reviewer->id]);
        }

        $claim->update([
            'state'               => CustomerClaimStateEnum::REJECTED,
            'rejection_reason'    => $reason,
            'rejection_note'      => $note,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at'         => now(),
        ]);

        return $claim;
    }

    public function authorize(ActionRequest $request): bool
    {
        return ShowCustomerClaim::canSettle($this->customerClaim, $request->user());
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(self::REASONS)],
            'note'   => ['required_if:reason,other', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function asController(CustomerClaim $customerClaim, ActionRequest $request): CustomerClaim
    {
        $this->customerClaim = $customerClaim;
        $this->initialisationFromShop($customerClaim->shop, $request);

        return $this->handle($customerClaim, $request->user(), $this->validatedData['reason'], $this->validatedData['note'] ?? null);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
