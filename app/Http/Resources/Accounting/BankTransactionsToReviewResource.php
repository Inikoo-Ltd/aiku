<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $booked_at
 * @property string $amount
 * @property string|null $counterparty_name
 * @property string|null $reference
 * @property BankTransactionMatchStateEnum $match_state
 * @property array<string, mixed> $match_data
 * @property string $bank
 * @property string $currency_code
 * @property string $org_currency_code
 * @property string|null $org_amount
 * @property string|null $order_reference
 * @property string|null $order_slug
 * @property string|null $shop_slug
 */
class BankTransactionsToReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'booked_at'         => $this->booked_at,
            'bank'              => $this->bank,
            'amount'            => (float)$this->amount,
            'currency_code'     => $this->currency_code,
            'org_amount'        => $this->currency_code === $this->org_currency_code || $this->org_amount === null ? null : (float)$this->org_amount,
            'org_currency_code' => $this->org_currency_code,
            'counterparty_name' => $this->counterparty_name,
            'reference'         => $this->reference,
            'reason'            => match ($this->match_state) {
                BankTransactionMatchStateEnum::MATCHED   => isset($this->match_data['invoice_id']) ? __('Matches the invoice, not paid automatically') : __('Matches the order, not paid automatically'),
                BankTransactionMatchStateEnum::DISMISSED => $this->match_data['dismissed_note'] ?? null,
                BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT => null,
                default                                  => $this->match_data['reason'] ?? null,
            },
            'is_dismissed'      => $this->match_state === BankTransactionMatchStateEnum::DISMISSED,
            'payout'            => $this->match_state === BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT ? [
                'provider'         => $this->match_data['provider'] ?? 'checkout',
                'payout_id'        => $this->match_data['payout_id'] ?? null,
                'reconciled'       => !empty($this->match_data['reconciled_at']),
                'payments'         => $this->match_data['payments_in_payout'] ?? 0,
                'refunds'          => $this->match_data['refunds_in_payout'] ?? 0,
                'processor_fees'   => (float)($this->match_data['processor_fees'] ?? 0),
                'bank_fee'         => (float)($this->match_data['bank_fee'] ?? 0),
                'payments_missing' => count($this->match_data['payments_missing'] ?? []),
            ] : null,
            'owed'              => $this->match_data['owed'] ?? null,
            'order_reference'   => $this->order_reference,
            'order_slug'        => $this->order_slug,
            'shop_slug'         => $this->shop_slug,
        ];
    }
}
