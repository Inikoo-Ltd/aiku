<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Oct 2024 21:48:29 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

trait WithPrepareDeliveryStoreFields
{
    protected function prepareDeliveryStoreFields($parent, $modelData)
    {
        $organisation = $parent->organisation;
        data_set($modelData, 'group_id', $organisation->group_id);

        if (class_basename($parent) == 'OrgSupplier') {
            data_set($modelData, 'supplier_id', $parent->supplier_id);
            data_set($modelData, 'parent_code', $parent->supplier->code, false);
            data_set($modelData, 'parent_name', $parent->supplier->name, false);
        } elseif (class_basename($parent) == 'OrgAgent') {
            data_set($modelData, 'agent_id', $parent->agent_id);
            data_set($modelData, 'parent_code', $parent->agent->code, false);
            data_set($modelData, 'parent_name', $parent->agent->name, false);
        } elseif (class_basename($parent) == 'OrgPartner') {
            data_set($modelData, 'partner_id', $parent->organisation_id);
            data_set($modelData, 'parent_code', $parent->organisation->code, false);
            data_set($modelData, 'parent_name', $parent->organisation->name, false);
        }

        return $modelData;
    }
}
