<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithSupplierProductImageEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $orgSupplierProduct = $request->route('orgSupplierProduct');
        if ($orgSupplierProduct instanceof OrgSupplierProduct) {
            return self::canEditOrgSupplierProductPictures($request->user(), $orgSupplierProduct);
        }

        return $request->user()->authTo(['supply-chain.edit', 'goods.edit']);
    }

    /**
     * The buying organisation's procurement staff, and the procurement staff of the agent organisation
     * whose warehouse handles the goods.
     */
    public static function canEditOrgSupplierProductPictures(?User $user, OrgSupplierProduct $orgSupplierProduct): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->authTo("procurement.{$orgSupplierProduct->organisation_id}.edit")) {
            return true;
        }

        $agentOrganisationId = $orgSupplierProduct->orgAgent?->agent?->organisation_id;

        return $agentOrganisationId && $user->authTo("procurement.$agentOrganisationId.edit");
    }
}
