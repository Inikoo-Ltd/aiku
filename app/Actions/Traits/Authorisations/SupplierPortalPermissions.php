<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 14:24
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits\Authorisations;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

/**
 * A supplier's portal is managed by supply chain staff and, for a supplier bought through an agent, by the agent organisation's procurement staff from the agent's own supplier page.
 */
class SupplierPortalPermissions
{
    public static function canView(User $user, Supplier $supplier): bool
    {
        return $user->authTo(['supply-chain.view', 'compliance.view']) || self::agentOrganisationAuthTo($user, $supplier, 'view');
    }

    public static function canEdit(User $user, Supplier $supplier): bool
    {
        return $user->authTo('supply-chain.edit') || self::agentOrganisationAuthTo($user, $supplier, 'edit');
    }

    public static function agentOrganisationBuysThrough(Organisation $organisation, Supplier $supplier): bool
    {
        return $organisation->type === OrganisationTypeEnum::AGENT
            && $supplier->agent_id !== null
            && $supplier->agent_id === $organisation->agent?->id;
    }

    private static function agentOrganisationAuthTo(User $user, Supplier $supplier, string $access): bool
    {
        $agentOrganisationId = $supplier->agent?->organisation_id;

        return $agentOrganisationId !== null && $user->authTo("procurement.$agentOrganisationId.$access");
    }
}
