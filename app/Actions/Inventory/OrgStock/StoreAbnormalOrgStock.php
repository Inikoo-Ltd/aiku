<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 20:34:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Search\OrgStockRecordSearch;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreAbnormalOrgStock extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|OrgStockFamily $parent, $modelData): OrgStock
    {
        if ($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
            data_set($modelData, 'org_stock_family_id', $parent->id);
        }

        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'state', OrgStockStateEnum::ABNORMALITY);


        $orgStock = DB::transaction(function () use ($organisation, $modelData, $parent) {
            /** @var OrgStock $orgStock */
            $orgStock = $organisation->orgStocks()->create($modelData);
            $orgStock->stats()->create();
            $orgStock->intervals()->create();

            if ($parent instanceof OrgStockFamily) {
                $orgStock->orgStockFamily()->associate($parent);
                $orgStock->save();
            }

            $orgStock->refresh();

            return $orgStock;
        });


        OrganisationHydrateOrgStocks::dispatch($organisation)->delay($this->hydratorsDelay);
        if ($orgStock->orgStockFamily) {
            OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily)->delay($this->hydratorsDelay);
        }

        OrgStockRecordSearch::dispatch($orgStock);

        return $orgStock;
    }


    public function rules(ActionRequest $request): array
    {
        $rules = [

        ];

        if (!$this->strict) {
            $rules['code']                            = ['required', 'string', 'max:255'];
            $rules['name']                            = ['required', 'string', 'max:255'];
            $rules['state']                           = ['required', Rule::enum(OrgStockStateEnum::class)];
            $rules['quantity_status']                 = ['sometimes', 'nullable', Rule::enum(OrgStockQuantityStatusEnum::class)];
            $rules['discontinued_in_organisation_at'] = ['sometimes', 'nullable', 'date'];
            $rules                                    = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }



    /**
     * @throws \Throwable
     */
    public function action(Organisation|OrgStockFamily $parent, $modelData = [], int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrgStock
    {
        if (!$audit) {
            OrgStock::disableAuditing();
        }

        if ($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($parent, $this->validatedData);
    }


}
