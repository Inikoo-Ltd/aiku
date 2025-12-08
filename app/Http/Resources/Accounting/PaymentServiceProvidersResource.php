<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $number_payments
 * @property int $number_payment_accounts
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property \App\Models\Helpers\Media $media
 * @property \App\Models\SysAdmin\Organisation $organisation
 * @property string $name
 */
class PaymentServiceProvidersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'number_payments' => $this->number_payments,
            'number_payment_accounts' => $this->number_payment_accounts,
            'slug' => $this->slug,
            'code' => $this->code,
            'logo' => GetImgProxyUrl::run($this->media->getImage()),
            'name' => $this->name,
            'created_at' => $this->created_at,
            'storeRoute' => [
                'name' => 'grp.models.org.payment-service-provider.store',
                'parameters' => [
                    'organisation' => $this->organisation->id,
                    'paymentServiceProvider' => $this->id,
                ],
            ],
        ];
    }
}
