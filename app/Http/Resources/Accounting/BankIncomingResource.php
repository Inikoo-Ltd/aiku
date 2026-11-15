<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\Accounting\BankTransaction\ReconcileCodPayouts;
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
 * @property int|null $payment_id
 * @property bool $read_only
 * @property string $organisation_slug
 * @property string $organisation_code
 * @property string|null $provider_type
 */
class BankIncomingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'booked_at'         => $this->booked_at,
            'organisation_slug' => $this->organisation_slug,
            'organisation_code' => $this->organisation_code,
            'bank'              => $this->bank,
            'amount'            => (float)$this->amount,
            'currency_code'     => $this->currency_code,
            'org_amount'        => $this->currency_code === $this->org_currency_code || $this->org_amount === null ? null : (float)$this->org_amount,
            'org_currency_code' => $this->org_currency_code,
            'counterparty_name' => $this->counterparty_name,
            'reference'         => $this->reference,
            'reason'            => match (true) {
                $this->payment_id !== null && isset($this->match_data['paid_by'])     => __('Recorded on the order by :name', ['name' => $this->match_data['paid_by']]),
                $this->payment_id !== null                                            => __('Paid automatically'),
                $this->match_state === BankTransactionMatchStateEnum::MATCHED         => isset($this->match_data['invoice_id']) ? __('Matches the invoice, not paid automatically') : __('Matches the order, not paid automatically'),
                $this->match_state === BankTransactionMatchStateEnum::DISMISSED       => $this->match_data['dismissed_note'] ?? null,
                $this->match_state === BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT => null,
                $this->match_state === BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE => __('Faire payout of this order'),
                $this->match_state === null => in_array($this->provider_type, MatchBankTransactions::CUSTOMER_TRANSFER_TYPES, true)
                    ? __('Not matched yet')
                    : __('Movement of the bank itself (:type), not a payment received', ['type' => str_replace('_', ' ', strtolower((string) $this->provider_type))]),
                default                                                               => $this->match_data['reason'] ?? null,
            },
            'is_dismissed'      => $this->match_state === BankTransactionMatchStateEnum::DISMISSED,
            'actionable'        => !$this->read_only && $this->payment_id === null && in_array($this->match_state, [BankTransactionMatchStateEnum::REVIEW, BankTransactionMatchStateEnum::UNMATCHED, BankTransactionMatchStateEnum::MATCHED], true),
            'payout'            => $this->match_state === BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT ? [
                'provider'         => $this->match_data['provider'] ?? 'checkout',
                'payout_id'        => $this->match_data['payout_id'] ?? null,
                'orders'           => $this->match_data['order_references'] ?? [],
                'reconciled'       => !empty($this->match_data['reconciled_at']),
                'payments'         => $this->match_data['payments_in_payout'] ?? 0,
                'refunds'          => $this->match_data['refunds_in_payout'] ?? 0,
                'processor_fees'   => (float)($this->match_data['processor_fees'] ?? 0),
                'bank_fee'         => (float)($this->match_data['bank_fee'] ?? 0),
                'payments_missing' => count($this->match_data['payments_missing'] ?? []),
                'carrier'          => ReconcileCodPayouts::CARRIERS[$this->match_data['carrier'] ?? '']['name'] ?? null,
                'differences'      => $this->match_data['differences'] ?? [],
                'not_recorded'     => $this->match_data['payments_not_recorded'] ?? [],
            ] : null,
            'owed'              => $this->match_data['owed'] ?? null,
            'order_reference'   => $this->order_reference,
            'order_slug'        => $this->order_slug,
            'shop_slug'         => $this->shop_slug,
        ];
    }
}
