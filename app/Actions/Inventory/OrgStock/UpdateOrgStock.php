<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 11:10:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Inventory\OrgStock\Search\OrgStockRecordSearch;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private OrgStock $orgStock;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        $orgStock = $this->update($orgStock, $modelData, ['data', 'settings']);
        OrgStockRecordSearch::dispatch($orgStock);
        $changes = $orgStock->getChanges();

        if (Arr::has($changes, 'state')) {
            OrganisationHydrateOrgStocks::dispatch($orgStock->organisation);

            foreach ($orgStock->tradeUnits as $tradeUnit) {
                TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
            }


            if ($orgStock->orgStockFamily) {
                OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily);
            }
        }

        return $orgStock;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("inventory.orgStocks.edit");
    }

    public function rules(): array
    {
        $rules = [];
        if (!$this->strict) {
            $rules['discontinued_in_organisation_at'] = ['sometimes', 'nullable', 'date'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function action(OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrgStock
    {
        if (!$audit) {
            OrgStock::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->orgStock       = $orgStock;
        $this->strict         = $strict;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->orgStock = $orgStock;
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $this->validatedData);
    }


}
