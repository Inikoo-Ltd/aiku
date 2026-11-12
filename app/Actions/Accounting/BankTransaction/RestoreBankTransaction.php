<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Undoes "set aside" on a bank line: it is matched again, as when it arrived, and lands in the queue it belongs to.
 */
class RestoreBankTransaction extends OrgAction
{
    use AuthorizesAccountingEdit;

    public function handle(BankTransaction $bankTransaction): BankTransaction
    {
        return DB::transaction(function () use ($bankTransaction) {
            $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id);
            if ($bankTransaction->match_state !== BankTransactionMatchStateEnum::DISMISSED || $bankTransaction->payment_id !== null) {
                throw ValidationException::withMessages(['note' => __('Only a bank payment that was set aside can be brought back')]);
            }

            [$state, $order, $matchData] = MatchBankTransactions::make()->decide($bankTransaction);
            $bankTransaction->update([
                'match_state' => $state === BankTransactionMatchStateEnum::MATCHED ? BankTransactionMatchStateEnum::REVIEW : $state,
                'order_id'    => $order?->id,
                'match_data'  => array_merge($matchData, ['restored_from' => $bankTransaction->match_data]),
                'matched_at'  => now(),
            ]);

            return $bankTransaction;
        });
    }

    public function asController(Organisation $organisation, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->initialisation($organisation, $request);
        abort_unless($bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
