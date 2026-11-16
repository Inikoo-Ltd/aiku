<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Accounting\BankTransaction\UI\IndexBankOutgoing;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line the banks paid out, or in Needs a person a payee with all its lines.
 *
 * @property int|null $id
 * @property \Illuminate\Support\Carbon|string $booked_at
 * @property string|null $amount
 * @property string|null $fee_amount
 * @property string|null $counterparty_name
 * @property string|null $reference
 * @property string|null $provider_type
 * @property string|null $payee_key
 * @property string|null $bank
 * @property string $currency_code
 * @property string $org_currency_code
 * @property string|null $org_amount
 * @property string|null $organisation_slug
 * @property string|null $organisation_code
 * @property string|null $payee_source
 * @property string|null $payee_model_type
 * @property string|null $order_reference
 * @property string|null $order_slug
 * @property string|null $shop_slug
 * @property int|null $lines
 */
class BankOutgoingResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($this->lines !== null) {
            return [
                'id'                => $this->payee_key ?? '',
                'payee_key'         => $this->payee_key,
                'counterparty_name' => $this->counterparty_name,
                'organisation_code' => $this->organisation_code,
                'lines'             => (int) $this->lines,
                'org_amount'        => $this->org_amount === null ? null : (float) $this->org_amount,
                'org_currency_code' => $this->org_currency_code,
                'currency_code'     => $this->currency_code,
                'booked_at'         => $this->booked_at,
                'reference'         => $this->reference,
            ];
        }

        return [
            'id'                => $this->id,
            'booked_at'         => $this->booked_at,
            'organisation_slug' => $this->organisation_slug,
            'organisation_code' => $this->organisation_code,
            'bank'              => $this->bank,
            'amount'            => -(float) $this->amount,
            'fee_amount'        => $this->fee_amount === null ? null : (float) $this->fee_amount,
            'currency_code'     => $this->currency_code,
            'org_amount'        => $this->currency_code === $this->org_currency_code || $this->org_amount === null ? null : (float) $this->org_amount,
            'org_currency_code' => $this->org_currency_code,
            'counterparty_name' => $this->counterparty_name,
            'payee_key'         => $this->payee_key,
            'payee_source'      => $this->payee_source,
            'order_reference'   => $this->order_reference,
            'order_slug'        => $this->order_slug,
            'shop_slug'         => $this->shop_slug,
            'reference'         => $this->reference,
            'kind'              => IndexBankOutgoing::kindLabels()[$this->provider_type] ?? str_replace('_', ' ', strtolower((string) $this->provider_type)),
        ];
    }
}
