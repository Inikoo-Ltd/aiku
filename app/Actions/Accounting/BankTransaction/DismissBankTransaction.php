<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: the line pays no order (a marketplace payout, a refund, recorded by hand already) and
 * leaves the queue with the reason. Matching never takes it up again.
 */
class DismissBankTransaction extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        return DB::transaction(function () use ($bankTransaction, $modelData) {
            $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id);

            $problem = match (true) {
                $bankTransaction->payment_id !== null => __('This bank payment is already recorded on an order'),
                $bankTransaction->direction !== BankTransactionDirectionEnum::IN || $bankTransaction->status !== BankTransactionStatusEnum::BOOKED => __('Only money received and booked by the bank is reviewed here'),
                default => null,
            };
            if ($problem) {
                throw ValidationException::withMessages(['note' => $problem]);
            }

            $bankTransaction->update([
                'match_state' => BankTransactionMatchStateEnum::DISMISSED,
                'match_data'  => array_merge($bankTransaction->match_data, [
                    'dismissed_note' => $modelData['note'],
                    'dismissed_by'   => $this->user?->contact_name ?? $this->user?->username ?? 'staff',
                    'dismissed_at'   => now()->toIso8601String(),
                ]),
                'matched_at'  => now(),
            ]);

            return $bankTransaction;
        });
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
