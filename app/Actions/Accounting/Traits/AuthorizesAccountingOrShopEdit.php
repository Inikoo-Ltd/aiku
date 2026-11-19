<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Traits;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A bank decision taken from Bank incoming needs accounting edit; the same decision taken from a shop's Bank payments
 * page needs to edit that shop's orders or customers, and reaches only that shop's orders and customers.
 */
trait AuthorizesAccountingOrShopEdit
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (isset($this->shop)) {
            return in_array($this->shop->type, [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING], true)
                && $request->user()->authTo(["orders.{$this->shop->id}.edit", "crm.{$this->shop->id}.edit"]);
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    /**
     * From a shop, only a payment still waiting for a person and pointing at the shop can be decided, read again under a lock: never one
     * accounting set aside or marked already paid, a payout, or one already tied to another shop's order or an invoice.
     * Call it inside the transaction that records the decision.
     */
    protected function lockLineOpenToShop(BankTransaction $bankTransaction): BankTransaction
    {
        $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id);
        if (!isset($this->shop)) {
            return $bankTransaction;
        }

        $waiting = $bankTransaction->payment_id === null
            && BankTransaction::whereKey($bankTransaction->id)
                ->whereRaw('('.IndexBankIncoming::BUCKET_SQL.") = 'needs_person'")
                ->whereRaw('bank_transactions.id in ('.IndexBankIncoming::shopLeadSql($this->shop).')')
                ->exists();
        $ownOrNoOrder = $bankTransaction->order_id === null
            ? !isset($bankTransaction->match_data['invoice_id'])
            : Order::whereKey($bankTransaction->order_id)->where('shop_id', $this->shop->id)->exists();
        if (!$waiting || !$ownOrNoOrder) {
            throw ValidationException::withMessages(['order' => __('This bank payment is no longer waiting for this shop: reload the page')]);
        }

        return $bankTransaction;
    }
}
