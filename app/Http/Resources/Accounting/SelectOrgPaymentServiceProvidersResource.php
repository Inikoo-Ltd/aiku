<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 15:55:59 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\UI\Accounting\Traits\HasPaymentServiceProviderFields;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $number_payments
 * @property int $number_payment_accounts
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property string $name
 * @property string $state
 * @property int $organisation_id
 * @property int $id
 * @property mixed $org_slug
 * @property mixed $org_code
 * @property \App\Models\Helpers\Media $media
 * @property \App\Models\SysAdmin\Organisation $organisation
 */
class SelectOrgPaymentServiceProvidersResource extends JsonResource
{
    use HasPaymentServiceProviderFields;

    public function toArray($request): array
    {
        $provider = Arr::get(explode('-', $this->code), 1);

        if (! $provider) {
            $provider = $this->code;
        }

        $additionalFields = $this->blueprint($provider);

        $formData = [
            'blueprint' => [
                [
                    'title' => __('payment account'),
                    'fields' => [
                        'code' => [
                            'type' => 'input',
                            'label' => __('code'),
                            'required' => true,
                            /*    'column'   => '1/2' */
                        ],
                        'name' => [
                            'type' => 'input',
                            'label' => __('name'),
                            'required' => true,
                            /*      'column'   => '1/2' */
                        ],
                        ...$additionalFields,
                    ],
                ],
            ],
            'route' => [
                'name' => 'grp.models.org.payment-service-provider-account.store',
                'parameters' => [
                    'paymentServiceProvider' => $this->id,
                ],
            ],
        ];

        if ($this->media && $this->media->isNotEmpty()) {
            $logo = GetImgProxyUrl::run($this->media->first()?->getImage());
        } else {
            $logo = null;
        }

        return [
            'number_payments' => $this->number_payments,
            'number_payment_accounts' => $this->number_payment_accounts,
            'slug' => $this->slug,
            'org_id' => $this->organisation_id,
            'org_slug' => $this->org_slug,
            'code' => $this->code,
            'org_code' => $this->org_code,
            'name' => $this->name,
            'state' => $this->state,
            'logo' => $logo,
            'formData' => $formData,
        ];
    }
}
