<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 14:32:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterShops;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterShop extends GrpAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    public function handle(MasterShop $masterShop, array $modelData): MasterShop
    {
        $masterShop = $this->update($masterShop, $modelData, ['data']);
        if ($masterShop->wasChanged('status')) {
            GroupHydrateMasterShops::dispatch($masterShop->group)->delay($this->hydratorsDelay);
        }

        return $masterShop;
    }

    public function rules(): array
    {
        return [
            'type'   => ['sometimes', Rule::enum(ShopTypeEnum::class)],
            'code'   => [
                'sometimes',
                'required',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_shops',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'   => ['sometimes', 'max:250', 'string'],
            'status' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function action(MasterShop $masterShop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): MasterShop
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;

        $this->initialisation($masterShop->group, $modelData);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterShop, $this->validatedData);
    }

}
