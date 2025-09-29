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
 *
 */
class RefundPaymentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'id'                   => $this->id,
            'status'               => $this->status,
            'refunded'             => $this->refunded,
            'payment_account_name' => $this->payment_account_name,
            'payment_account_slug' => $this->payment_account_slug,
            'date'                 => $this->date,
            'reference'            => $this->reference,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'amount'               => $this->amount,
            'route'                => [
                'name'   => 'grp.org.accounting.payments.show',
                'params' => [
                    'organisation' => $this->organisation_slug,
                    'payment'      => $this->id
                ]
            ],
            'currency_code'        => $this->currency_code,
        );
    }
}
