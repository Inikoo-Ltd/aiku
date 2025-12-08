<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Nov 2024 21:11:58 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\OrgAction;
use App\Actions\Traits\WithStoreModelAddress;
use App\Models\SysAdmin\Organisation;
use App\Rules\ValidAddress;

class StoreOrganisationAddress extends OrgAction
{
    use WithStoreModelAddress;

    public function handle(Organisation $organisation, array $modelData): Organisation
    {
        $addressData = $modelData['address'];
        data_set($addressData, 'group_id', $organisation->group_id);
        $address = $this->storeModelAddress($addressData);

        $organisation->updateQuietly(
            [
                'address_id' => $address->id,
            ]
        );

        return $organisation;
    }

    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress],

        ];
    }

    public function action(Organisation $organisation, array $modelData, int $hydratorsDelay = 0, bool $audit = true): Organisation
    {
        if (! $audit) {
            Organisation::disableAuditing();
        }
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }
}
