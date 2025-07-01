<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-16h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use Illuminate\Http\Resources\Json\JsonResource;

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
 *  @property \App\Models\Helpers\Media $media
 * @property \App\Models\SysAdmin\Organisation $organisation
 *
 */
class OrgPaymentServiceProvidersResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($this->media && $this->media->isNotEmpty()) {
            $logo = GetImgProxyUrl::run($this->media->first()?->getImage());
        } else {
            $logo = null;
        }

        return [
            'number_payments'             => $this->number_payments,
            'number_payment_accounts'     => $this->number_payment_accounts,
            'slug'                        => $this->slug,
            'org_id'                      => $this->organisation_id,
            'org_slug'                    => $this->org_slug,
            'code'                        => $this->code,
            'org_code'                    => $this->org_code,
            'name'                        => $this->name,
            'state'                       => $this->state,
            'logo'                        => $logo,
        ];
    }
}
