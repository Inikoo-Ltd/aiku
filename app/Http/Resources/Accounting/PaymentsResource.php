<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Models\Accounting\Payment;
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
 *
 */
class PaymentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return array(
            'id'         => $payment->id,
            'status'     => $payment->status,
            'type'      => $payment->type,
            'payment_account_name' => $payment->payment_account_name,
            'payment_account_slug' => $payment->payment_account_slug,
            'payment_account_type' => $payment->payment_account_type,
            'payment_account' => $payment->paymentAccount ? [
                'type' => $payment->paymentAccount->type,
                'code' => $payment->paymentAccount->code,
                'name' => $payment->paymentAccount->name,
            ] : null,
            'status_icon' => $payment->status->statusIcon()[$payment->status->value],
            'date'       => $payment->date,
            'reference'  => $payment->reference,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'method'     => $payment->method,
            'amount'     => $payment->amount,
            'route' => match ($request->route()->getName()) {
                'grp.org.shops.show.crm.customers.show' => [
                        'name' => 'grp.org.shops.show.crm.customers.show.'.$payment->type->value.'s.show',
                        'params' => [
                            ...$request->route()->originalParameters(),
                            'payment' => $payment->id
                        ]
                ],
                default => [
                    'name' => 'grp.org.accounting.payments.show',
                    'params' => [
                        'organisation' => $payment->organisation_slug,
                        'payment' => $payment->id
                    ]
                ]
            },
            'currency_code' => $payment->currency_code,
            'organisation_id'   => $payment->organisation_id,
            'organisation_name' => $payment->organisation_name,
            'organisation_slug' => $payment->organisation_slug,
            'shop_name'         => $payment->shop_name,
            'shop_slug'         => $payment->shop_slug,
            'is_cancelled'      => $payment->state === PaymentStateEnum::CANCELLED,
        );
    }
}
