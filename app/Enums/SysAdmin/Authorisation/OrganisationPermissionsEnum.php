<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Dec 2023 00:49:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;

enum OrganisationPermissionsEnum: string
{
    case ORG_ADMIN = 'org-admin'; // Can create and manage shops,warehouses,productions etc

    case ORG_REPORTS = 'org-reports';

    case ACCOUNTING = 'accounting';
    case ACCOUNTING_EDIT = 'accounting.edit';
    case ACCOUNTING_VIEW = 'accounting.view';

    case HUMAN_RESOURCES = 'human-resources';
    case HUMAN_RESOURCES_EDIT = 'human-resources.edit';
    case HUMAN_RESOURCES_VIEW = 'human-resources.view';

    case PROCUREMENT = 'procurement';
    case PROCUREMENT_EDIT = 'procurement.edit';
    case PROCUREMENT_VIEW = 'procurement.view';

    case INVENTORY = 'inventory';
    case INVENTORY_EDIT = 'inventory.edit';
    case INVENTORY_VIEW = 'inventory.view';

    case SHOPS_VIEW = 'shops-view';
    case WEBSITES_VIEW = 'websites-view';

    case FULFILMENTS_VIEW = 'fulfilments-view';
    case WAREHOUSES_VIEW = 'warehouses-view';
    case PRODUCTIONS_VIEW = 'productions-view';

    case SEO = 'seo';
    case SEO_EDIT = 'seo.edit';
    case SEO_VIEW = 'seo.view';

    case PPC = 'ppc';
    case PPC_EDIT = 'ppc.edit';
    case PPC_VIEW = 'ppc.view';

    case SOCIAL = 'social';
    case SOCIAL_EDIT = 'social.edit';
    case SOCIAL_VIEW = 'social.view';

    case SAAS = 'saas';
    case SAAS_EDIT = 'saas.edit';
    case SAAS_VIEW = 'saas.view';

    case SUPERVISOR = 'org-supervisor';

    case SUPERVISOR_HUMAN_RESOURCES = 'org-supervisor.human-resources';
    case SUPERVISOR_ACCOUNTING = 'org-supervisor.accounting';
    case SUPERVISOR_PROCUREMENT = 'org-supervisor.procurement';
    case SUPERVISOR_SEO = 'org-supervisor.seo';
    case SUPERVISOR_PPC = 'org-supervisor.ppc';
    case SUPERVISOR_SOCIAL = 'org-supervisor.social';
    case SUPERVISOR_SAAS = 'org-supervisor.saas';

    public function organisationTypes(): array
    {
        return match ($this) {
            OrganisationPermissionsEnum::PROCUREMENT,
            OrganisationPermissionsEnum::PROCUREMENT_EDIT,
            OrganisationPermissionsEnum::PROCUREMENT_VIEW,
            OrganisationPermissionsEnum::INVENTORY,
            OrganisationPermissionsEnum::INVENTORY_EDIT,
            OrganisationPermissionsEnum::INVENTORY_VIEW, => [OrganisationTypeEnum::AGENT, OrganisationTypeEnum::SHOP],

            OrganisationPermissionsEnum::SEO,
            OrganisationPermissionsEnum::SEO_EDIT,
            OrganisationPermissionsEnum::SEO_VIEW,
            OrganisationPermissionsEnum::PPC,
            OrganisationPermissionsEnum::PPC_EDIT,
            OrganisationPermissionsEnum::PPC_VIEW,
            OrganisationPermissionsEnum::SOCIAL,
            OrganisationPermissionsEnum::SOCIAL_EDIT,
            OrganisationPermissionsEnum::SOCIAL_VIEW,
            OrganisationPermissionsEnum::SAAS,
            OrganisationPermissionsEnum::SAAS_EDIT,
            OrganisationPermissionsEnum::SAAS_VIEW, => [OrganisationTypeEnum::DIGITAL_AGENCY],
            default => [OrganisationTypeEnum::DIGITAL_AGENCY, OrganisationTypeEnum::AGENT, OrganisationTypeEnum::SHOP]
        };
    }

    public static function getAllValues(Organisation $organisation): array
    {
        $permissionsNames = [];
        foreach (OrganisationPermissionsEnum::cases() as $case) {
            if (in_array($organisation->type, $case->organisationTypes())) {
                $permissionsNames[] = self::getPermissionName($case->value, $organisation);
            }
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Organisation $organisation): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$organisation->id], array_slice($permissionComponents, 1));

        return implode('.', $permissionComponents);
    }
}
