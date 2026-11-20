<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Models\Procurement\OrgPartner;
use Illuminate\Http\Resources\Json\JsonResource;

class CrossDispatchSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgPartner $orgPartner */
        $orgPartner         = $this->resource;
        $actingOrganisation = $request->route('organisation');
        $isOrigin           = $actingOrganisation->id === $orgPartner->organisation_id;
        $settings           = $orgPartner->crossDispatchSettings();

        return [
            'id'                      => $orgPartner->id,
            'is_origin'               => $isOrigin,
            'origin_name'             => $orgPartner->organisation->name,
            'dispatcher_name'         => $orgPartner->partner->name,
            'our_enabled'             => $isOrigin ? $settings['enabled_by_origin'] : $settings['enabled_by_dispatcher'],
            'their_enabled'           => $isOrigin ? $settings['enabled_by_dispatcher'] : $settings['enabled_by_origin'],
            'is_on'                   => $orgPartner->isCrossDispatchEnabled(),
            'goods_price_basis'       => $settings['goods_price_basis'],
            'dispatch_fee_per_parcel' => $settings['dispatch_fee_per_parcel'],
            'currency_code'           => $orgPartner->partner->currency->code,
            'shipping_pass_through'   => $settings['shipping_pass_through'],
            'settlement_period'       => $settings['settlement_period'],
            'allow_customs_crossing'  => $settings['allow_customs_crossing'],
            'tax_treatment'           => $settings['tax_treatment'],
            'update_route'            => [
                'name'       => 'grp.org.settings.cross_dispatch.update',
                'parameters' => [$actingOrganisation->slug, $orgPartner->id],
            ],
        ];
    }
}
