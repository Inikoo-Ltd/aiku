<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 May 2026 14:34:14 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserOrganisationLayout
{
    use AsObject;

    public function handle(User $user, Organisation $organisation): array
    {
        return [
            'id'                     => $organisation->id,
            'slug'                   => $organisation->slug,
            'code'                   => $organisation->code,
            'label'                  => $organisation->name,
            'type'                   => $organisation->type,
            'currency'               => $organisation->currency,
            'logo'                   => $organisation->imageSources(48, 48),
            'route'                  => [
                'name'       => 'grp.org.dashboard.show',
                'parameters' => [
                    $organisation->slug
                ]
            ],
            'authorised_shops'       => $this->getShops($user, $organisation),
            'authorised_fulfilments' => $this->getFulfilments($user, $organisation),
            'authorised_warehouses'  => $this->getAuthorisedWarehouses($user, $organisation),
            'authorised_productions' => $this->getAuthorisedProductions($user, $organisation),
        ];
    }

    public function getAuthorisedProductions(User $user, Organisation $organisation): array
    {
        $productions = [];

        $authorisedProductions = $user->authorisedProductions()->where('organisation_id', $organisation->id)->get();

        /** @var Production $production */
        foreach ($authorisedProductions as $production) {
            $productions[] = [
                'id'    => $production->id,
                'slug'  => $production->slug,
                'code'  => $production->code,
                'label' => $production->name
            ];
        }

        return $productions;
    }

    public function getAuthorisedWarehouses(User $user, Organisation $organisation): array
    {
        $warehouses = [];

        $authorisedWarehouses = $user->authorisedWarehouses()->where('organisation_id', $organisation->id)->get();

        /** @var Warehouse $warehouse */
        foreach ($authorisedWarehouses as $warehouse) {
            $warehouses[] = [
                'id'    => $warehouse->id,
                'slug'  => $warehouse->slug,
                'code'  => $warehouse->code,
                'label' => $warehouse->name,
                'route' => [
                    'name'       => 'grp.org.warehouses.show.infrastructure.dashboard',
                    'parameters' => [
                        $warehouse->organisation->slug,
                        $warehouse->slug
                    ]
                ],
            ];
        }

        return $warehouses;
    }

    public function getFulfilments(User $user, Organisation $organisation): array
    {
        $fulfilments = [];

        $authorisedFulfilments = $user->authorisedFulfilments()->where('organisation_id', $organisation->id)->get();

        /** @var Fulfilment $fulfilment */
        foreach ($authorisedFulfilments as $fulfilment) {
            $fulfilments[] = [
                'id'    => $fulfilment->id,
                'slug'  => $fulfilment->slug,
                'code'  => $fulfilment->shop->code,
                'label' => $fulfilment->shop->name,
                'state' => $fulfilment->shop->state,
                'type'  => $fulfilment->shop->type,
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.operations.dashboard',
                    'parameters' => [
                        $fulfilment->organisation->slug,
                        $fulfilment->slug
                    ]
                ]
            ];
        }

        return $fulfilments;
    }

    public function getShops(User $user, Organisation $organisation): array
    {
        $shopsData = [];

        $authorisedShops = $user->authorisedShops()->where('organisation_id', $organisation->id)->where('shops.type', '!=', ShopTypeEnum::FULFILMENT)->get();

        /** @var Shop $shop */
        foreach ($authorisedShops as $shop) {
            $shopsData[] = [
                'id'                   => $shop->id,
                'slug'                 => $shop->slug,
                'code'                 => $shop->code,
                'label'                => $shop->name,
                'state'                => $shop->state,
                'type'                 => $shop->type,
                'website_domain'       => $shop->website?->domain ?? null,
                'route'                => [
                    'name'       => 'grp.org.shops.show.dashboard.show',
                    'parameters' => [
                        $shop->organisation->slug,
                        $shop->slug
                    ]
                ],
                'is_external'          => $shop->type == ShopTypeEnum::EXTERNAL,
                'external_api_problem' => !$shop->external_shop_platform_status && !is_null($shop->external_shop_connection_failed_at) // true if connection fails & fail timestamp exists
            ];
        }

        return $shopsData;
    }

    public function getOrganisations(User $user): array
    {
        $organisationLayoutData = [];
        /** @var Organisation $organisation */
        foreach ($user->authorisedShopOrganisations as $organisation) {
            $organisationLayoutData[] = $this->handle($user, $organisation);
        }

        return ['data' => $organisationLayoutData];
    }

    public function getAgents(User $user): array
    {
        $organisationLayoutData = [];
        /** @var Organisation $organisation */
        foreach ($user->authorisedAgentsOrganisations as $organisation) {
            $organisationLayoutData[] = $this->handle($user, $organisation);
        }

        return ['data' => $organisationLayoutData];
    }

    public function getDigitalAgencies(User $user): array
    {
        $organisationLayoutData = [];
        /** @var Organisation $organisation */
        foreach ($user->authorisedDigitalAgencyOrganisations as $organisation) {
            $organisationLayoutData[] = $this->handle($user, $organisation);
        }

        return ['data' => $organisationLayoutData];
    }

}
