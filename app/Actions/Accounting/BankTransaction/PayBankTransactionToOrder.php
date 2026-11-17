<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Traits\AuthorizesAccountingOrShopEdit;
use App\Actions\OrgAction;
use App\Models\Accounting\BankTransaction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * From the bank review queue: a person names the order a bank payment belongs to, and it is recorded there. From a
 * shop's Bank payments page only an order of that shop can be named, and no bank fee is absorbed.
 */
class PayBankTransactionToOrder extends OrgAction
{
    use AuthorizesAccountingOrShopEdit;

    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(BankTransaction $bankTransaction, array $modelData): BankTransaction
    {
        $orders = Order::where('organisation_id', $bankTransaction->organisation_id)
            ->when(isset($this->shop), fn ($query) => $query->where('shop_id', $this->shop->id))
            ->when(
                $modelData['order_id'] ?? null,
                fn ($query, $orderId) => $query->where('id', $orderId),
                fn ($query) => $query->where('reference', strtoupper(trim($modelData['order_reference'])))
            )
            ->get();
        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(['order' => isset($this->shop)
                ? __('No order :reference in this shop', ['reference' => $modelData['order_reference'] ?? ''])
                : __('No order :reference in this organisation', ['reference' => $modelData['order_reference'] ?? ''])]);
        }
        $order = $orders->count() === 1 ? $orders->first() : $orders->firstWhere('id', $bankTransaction->order_id);
        if (!$order) {
            throw ValidationException::withMessages(['order' => __('Several orders are numbered :reference: record this payment from the order page', ['reference' => $modelData['order_reference']])]);
        }

        if ($modelData['from_suggestion'] ?? false) {
            DB::transaction(fn () => $this->checkStillSuggested(BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id), Order::lockForUpdate()->findOrFail($order->id)));
        }

        DB::transaction(fn () => $this->lockLineOpenToShop($bankTransaction));
        PayMatchedBankTransactions::make()->payToOrder(
            $bankTransaction,
            $order,
            !isset($this->shop) && ($modelData['absorb_bank_fee'] ?? false),
            $this->user?->contact_name ?? $this->user?->username ?? 'staff'
        );

        $bankTransaction->refresh()->recordDecision('paid_to_order', array_filter([
            'order'     => $order->reference,
            'amount'    => $bankTransaction->amount,
            'currency'  => $bankTransaction->currency->code,
            'from_shop' => isset($this->shop) ? $this->shop->code : null,
        ]));

        return $bankTransaction;
    }

    /**
     * Paying from a row suggestion: decided again under the locks, the order must still be the one order this line
     * would pay, so a page left open while the order changed cannot record the money on it. payToOrder then locks
     * both again and refuses an order that owes nothing, so a second click elsewhere in between cannot pay it twice.
     */
    private function checkStillSuggested(BankTransaction $bankTransaction, Order $order): void
    {
        $stillSuggested = !$bankTransaction->order_id && collect(MatchBankTransactions::make()->suggestedOrders($bankTransaction))
            ->contains(fn (array $suggestion) => $suggestion['id'] === $order->id && $suggestion['kind'] === 'pay');
        if (!$stillSuggested) {
            throw ValidationException::withMessages(['order' => __(':order is no longer the one order this payment pays: open Pay order to choose', ['order' => $order->reference])]);
        }
    }

    public function rules(): array
    {
        return [
            'order_id'        => ['sometimes', 'nullable', 'integer'],
            'order_reference' => ['required_without:order_id', 'nullable', 'string', 'max:64'],
            'absorb_bank_fee' => ['sometimes', 'boolean'],
            'from_suggestion' => ['sometimes', 'boolean'],
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

    /**
     * @throws \Throwable
     */
    public function inShop(Organisation $organisation, Shop $shop, BankTransaction $bankTransaction, ActionRequest $request): BankTransaction
    {
        $this->user = $request->user();
        $this->initialisationFromShop($shop, $request);
        abort_unless($shop->organisation_id === $organisation->id && $bankTransaction->organisation_id === $organisation->id, 404);

        return $this->handle($bankTransaction, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
