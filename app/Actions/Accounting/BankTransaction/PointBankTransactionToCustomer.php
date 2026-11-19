<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingOrShopEdit;
use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Shop staff who know the payer say which customer of their shop sent a bank payment that is still waiting for a
 * person, in any currency, so accounting can place it. Nothing is paid. The hint is kept apart from what the matcher
 * writes, so a new sync or match never loses it; naming another customer replaces it.
 */
class PointBankTransactionToCustomer extends OrgAction
{
    use AuthorizesAccountingOrShopEdit;

    private ?User $user = null;

    /**
     * @param array{customer_id: int, note?: string|null} $modelData
     */
    public function handle(BankTransaction $bankTransaction, Shop $shop, array $modelData): BankTransaction
    {
        $customer = Customer::where('shop_id', $shop->id)->find($modelData['customer_id']);
        if (!$customer) {
            throw ValidationException::withMessages(['customer' => __('No such customer in this shop')]);
        }

        return DB::transaction(function () use ($bankTransaction, $shop, $customer, $modelData) {
            $bankTransaction = $this->lockLineOpenToShop($bankTransaction);

            $note = trim((string)($modelData['note'] ?? '')) ?: null;
            $bankTransaction->update([
                'staff_hint' => [
                    'customer_id'        => $customer->id,
                    'customer_reference' => $customer->reference,
                    'customer_name'      => $customer->name,
                    'shop_id'            => $shop->id,
                    'shop_code'          => $shop->code,
                    'note'               => $note,
                    'user_id'            => $this->user?->id,
                    'by'                 => $this->user?->contact_name ?? $this->user?->username ?? 'staff',
                    'at'                 => now()->toIso8601String(),
                ],
            ]);
            $bankTransaction->recordDecision('pointed_to_customer', array_filter([
                'customer'  => $customer->reference,
                'from_shop' => $shop->code,
                'note'      => $note,
            ]));

            return $bankTransaction;
        });
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function inShop(Organisation $organisation, Shop $shop, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisationFromShop($shop, $request);
        abort_unless($shop->organisation_id === $organisation->id && $bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
