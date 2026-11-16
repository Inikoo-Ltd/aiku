<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 20:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: a person names the customer a bank payment that pays no order came from, and the money
 * is added to that customer's credit.
 */
class PayBankTransactionToCustomerCredit extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        $customer = Customer::where('organisation_id', $bankTransaction->organisation_id)->find($modelData['customer_id']);
        if (!$customer) {
            throw ValidationException::withMessages(['customer' => __('No such customer in this organisation')]);
        }

        PayMatchedBankTransactions::make()->payToCustomerCredit(
            $bankTransaction,
            $customer,
            $this->user?->contact_name ?? $this->user?->username ?? 'staff'
        );

        return $bankTransaction->refresh();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
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
