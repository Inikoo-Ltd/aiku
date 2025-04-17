<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Analytics;

use App\Actions\OrgAction;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsController;

class GetSectionRoute extends OrgAction
{
    use AsController;

    public function handle(string $routeName, array $routeParameters): AikuScopedSection|null
    {
        // route redirect dashboard
        if ($routeName == 'grp.') {
            return AikuScopedSection::where('code', AikuSectionEnum::GROUP_DASHBOARD)->where('model_slug', end($routeParameters))->first();
        }

        if ($routeName == 'grp.org.') {
            return AikuScopedSection::where('code', AikuSectionEnum::ORG_DASHBOARD)->where('model_slug', end($routeParameters))->first();
        }

        if ($routeName == 'grp.org.shops.') {
            return AikuScopedSection::where('code', AikuSectionEnum::SHOP_DASHBOARD)->where('model_slug', end($routeParameters))->first();
        }

        // shops
        if (str_starts_with($routeName, 'grp.org.shops.show.')) {
            return $this->parseShopSections(
                preg_replace('/^grp\.org\.shops\.show\./', '', $routeName),
                $routeParameters
            );
        }

        // fulfilment
        if (str_starts_with($routeName, 'grp.org.fulfilments.show.')) {
            return $this->parseFulfilmentSections(
                preg_replace('/^grp\.org\.fulfilments\.show\./', '', $routeName),
                $routeParameters
            );
        }

        // production
        if (str_starts_with($routeName, 'grp.org.productions.show.')) {
            return $this->parseProductionSections(
                preg_replace('/^grp\.org\.productions\.show\./', '', $routeName),
                $routeParameters
            );
        }

        // Warehouse
        if (str_starts_with($routeName, 'grp.org.warehouses.show.')) {
            return $this->parseWarehouseSections(
                preg_replace('/^grp\.org\.warehouses\.show\./', '', $routeName),
                $routeParameters
            );
        }

        // organisation
        if (str_starts_with($routeName, 'grp.org.')) {
            return $this->parseOrganisationSections(
                preg_replace('/^grp\.org\./', '', $routeName),
                $routeParameters
            );
        }

        // group
        if (str_starts_with($routeName, 'grp.')) {
            return $this->parseGroupSections(
                preg_replace('/^grp\./', '', $routeName),
                $routeParameters
            );
        }


        return null;
    }

    public function parseShopSections(string $route, $routeParameters): AikuScopedSection|null
    {
        $sectionCode = match (true) {
            str_starts_with($route, 'catalogue') => AikuSectionEnum::SHOP_CATALOGUE,
            str_starts_with($route, 'billables') => AikuSectionEnum::SHOP_BILLABLES,
            str_starts_with($route, 'discounts') => AikuSectionEnum::SHOP_OFFER,
            str_starts_with($route, 'marketing') => AikuSectionEnum::SHOP_MARKETING,
            str_starts_with($route, 'web') => AikuSectionEnum::SHOP_WEBSITE,
            str_starts_with($route, 'crm.customers.show.platforms') => AikuSectionEnum::DROPSHIPPING,
            str_starts_with($route, 'crm') => AikuSectionEnum::SHOP_CRM,
            str_starts_with($route, 'ordering') => AikuSectionEnum::SHOP_ORDERING,
            str_starts_with($route, 'settings') => AikuSectionEnum::SHOP_SETTINGS,
            str_starts_with($route, 'dashboard'), '' => AikuSectionEnum::SHOP_DASHBOARD,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        return AikuScopedSection::where('code', $sectionCode)->where('model_slug', $routeParameters['shop'])->first();
    }

    public function parseOrganisationSections(string $route, $routeParameters): AikuScopedSection|null
    {
        $org = $routeParameters['organisation'];
        $org = Organisation::where('slug', $org)->first();

        if (!$org) {
            return null;
        }
        $orgType = $org->type;
        $sectionCode = match (true) {
            str_starts_with($route, 'dashboard') => $orgType == OrganisationTypeEnum::AGENT ? AikuSectionEnum::AGENT_DASHBOARD : AikuSectionEnum::ORG_DASHBOARD,
            str_starts_with($route, 'settings') => AikuSectionEnum::ORG_SETTINGS,
            str_starts_with($route, 'procurement') => $orgType == OrganisationTypeEnum::AGENT ? AikuSectionEnum::AGENT_PROCUREMENT : AikuSectionEnum::ORG_PROCUREMENT,
            str_starts_with($route, 'accounting') => AikuSectionEnum::ORG_ACCOUNTING,
            str_starts_with($route, 'hr') => AikuSectionEnum::ORG_HR,
            str_starts_with($route, 'reports') => AikuSectionEnum::ORG_REPORT,

            str_starts_with($route, 'shops') => AikuSectionEnum::ORG_SHOP,
            str_starts_with($route, 'fulfilments') => AikuSectionEnum::ORG_FULFILMENT,
            str_starts_with($route, 'productions') => AikuSectionEnum::ORG_PRODUCTION,
            str_starts_with($route, 'warehouses') => AikuSectionEnum::ORG_WAREHOUSE,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        return AikuScopedSection::where('code', $sectionCode)->where('model_slug', $org->slug)->first();
    }

    public function parseFulfilmentSections(string $route, $routeParameters): AikuScopedSection|null
    {
        $sectionCode = match (true) {
            str_starts_with($route, 'dashboard') => AikuSectionEnum::FULFILMENT_DASHBOARD,
            str_starts_with($route, 'catalogue') => AikuSectionEnum::FULFILMENT_CATALOGUE,
            str_starts_with($route, 'operations') => AikuSectionEnum::FULFILMENT_OPERATION,
            str_starts_with($route, 'web') => AikuSectionEnum::FULFILMENT_WEBSITE,
            str_starts_with($route, 'crm') => AikuSectionEnum::FULFILMENT_CRM,
            str_starts_with($route, 'settings') => AikuSectionEnum::FULFILMENT_SETTINGS,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        return AikuScopedSection::where('code', $sectionCode)->where('model_slug', $routeParameters['fulfilment'])->first();
    }

    public function parseProductionSections(string $route, $routeParameters): AikuScopedSection|null
    {
        $sectionCode = match (true) {
            str_starts_with($route, 'crafts') => AikuSectionEnum::PRODUCTION_CRAFT,
            str_starts_with($route, 'operations') => AikuSectionEnum::PRODUCTION_OPERATION,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        return AikuScopedSection::where('code', $sectionCode)->where('model_slug', $routeParameters['production'])->first();
    }

    public function parseWarehouseSections(string $route, $routeParameters): AikuScopedSection|null
    {
        $sectionCode = match (true) {
            str_starts_with($route, 'inventory') => AikuSectionEnum::INVENTORY,
            str_starts_with($route, 'infrastructure') => AikuSectionEnum::INVENTORY_INFRASTRUCTURE,
            str_starts_with($route, 'incoming') => AikuSectionEnum::INVENTORY_INCOMING,
            str_starts_with($route, 'dispatching') => AikuSectionEnum::INVENTORY_DISPATCHING,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        return AikuScopedSection::where('code', $sectionCode)->where('model_slug', $routeParameters['warehouse'])->first();
    }

    public function parseGroupSections(string $route, $routeParameters): AikuScopedSection|null
    {

        $sectionCode = match (true) {
            str_starts_with($route, 'dashboard') => AikuSectionEnum::GROUP_DASHBOARD,
            str_starts_with($route, 'goods') => AikuSectionEnum::GROUP_GOODS,
            str_starts_with($route, 'supply-chain') => AikuSectionEnum::GROUP_SUPPLY_CHAIN,
            str_starts_with($route, 'organisations') => AikuSectionEnum::GROUP_ORGANISATION,
            str_starts_with($route, 'overview') => AikuSectionEnum::GROUP_OVERVIEW,
            str_starts_with($route, 'sysadmin') => AikuSectionEnum::GROUP_SYSADMIN,
            str_starts_with($route, 'profile') => AikuSectionEnum::GROUP_PROFILE,
            default => null,
        };

        if (!$sectionCode) {
            return null;
        }

        if ($routeParameters) {
            return AikuScopedSection::where('code', $sectionCode)->where('model_slug', group()->slug)->first();
        } else {
            return AikuScopedSection::where('code', $sectionCode)->first();
        }
    }
}
