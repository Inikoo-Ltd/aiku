<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_payments
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $code
 * @property mixed $number_pas_state_active
 * @property mixed $org_amount_successfully_paid
 * @property mixed $payment_service_provider_slug
 * @property mixed $payment_service_provider_name
 * @property mixed $payment_service_provider_code
 * @property mixed $org_currency_code
 *
 */
class PaymentAccountsResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'id'                            => $this->id,
            'slug'                          => $this->slug,
            'name'                          => $this->name,
            'number_payments'               => $this->number_payments,
            'number_customers'               => $this->number_customers,
            'number_pas_state_active'       => $this->number_pas_state_active,
            'code'                          => $this->code,
            'org_amount_successfully_paid'  => $this->org_amount_successfully_paid,
            'org_currency_code'             => $this->org_currency_code,
            'payment_service_provider_slug' => $this->payment_service_provider_slug,
            'payment_service_provider_name' => $this->payment_service_provider_name,
            'payment_service_provider_code' => $this->payment_service_provider_code,
        );
    }
}
