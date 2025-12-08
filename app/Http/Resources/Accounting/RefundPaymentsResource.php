<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $status
 * @property string $date
 * @property int $data
 * @property string $slug
 * @property string $reference
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $payment_service_providers_slug
 * @property string $payment_accounts_slug
 * @property mixed $id
 * @property mixed $refunded
 * @property mixed $payment_account_name
 * @property mixed $payment_account_slug
 * @property mixed $amount
 * @property mixed $organisation_slug
 * @property mixed $currency_code
 * @property mixed $payment_account_type
 * @property mixed $payment_account_code
 */
class RefundPaymentsResource extends JsonResource
{
    public function toArray($request): array
    {
        $apiRefund = false;
        $manualRefund = false;
        //        if ($this->payment_account_code == 'checkout-v2') {
        //            $apiRefund = true;
        //        }
        if ($this->payment_account_type != 'account') {
            $manualRefund = true;
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'refunded' => $this->refunded,
            'payment_account_code' => $this->payment_account_code,
            'payment_account_name' => $this->payment_account_name,
            'payment_account_slug' => $this->payment_account_slug,
            'date' => $this->date,
            'reference' => $this->reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'amount' => $this->amount,
            'manual_refund_route' => [
                'name' => 'grp.models.payment.refund_manual',
                'parameters' => [
                    'payment' => $this->id,
                ],
            ],
            'api_refund_route' => [
                'name' => 'grp.models.payment.refund_api',
                'parameters' => [
                    'payment' => $this->id,
                ],
            ],
            'balance_refund_route' => [
                'name' => 'grp.models.payment.refund_to_balance',
                'parameters' => [
                    'payment' => $this->id,
                ],
            ],
            'currency_code' => $this->currency_code,
            'can_api_refund' => $apiRefund,
            'can_manual_refund' => $manualRefund,
        ];
    }
}
