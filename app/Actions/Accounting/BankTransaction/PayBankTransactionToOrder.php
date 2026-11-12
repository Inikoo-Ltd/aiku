<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: a person names the order a bank payment belongs to, and it is recorded there.
 */
class PayBankTransactionToOrder extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        $orders = Order::where('organisation_id', $bankTransaction->organisation_id)
            ->where('reference', strtoupper(trim($modelData['order_reference'])))
            ->get();
        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(['order' => __('No order :reference in this organisation', ['reference' => $modelData['order_reference']])]);
        }
        $order = $orders->count() === 1 ? $orders->first() : $orders->firstWhere('id', $bankTransaction->order_id);
        if (!$order) {
            throw ValidationException::withMessages(['order' => __('Several orders are numbered :reference: record this payment from the order page', ['reference' => $modelData['order_reference']])]);
        }

        PayMatchedBankTransactions::make()->payToOrder(
            $bankTransaction,
            $order,
            (bool)($modelData['absorb_bank_fee'] ?? false),
            $this->user?->contact_name ?? $this->user?->username ?? 'staff'
        );

        return $bankTransaction->refresh();
    }

    public function rules(): array
    {
        return [
            'order_reference' => ['required', 'string', 'max:64'],
            'absorb_bank_fee' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     */
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
