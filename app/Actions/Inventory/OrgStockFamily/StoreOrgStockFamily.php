<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:20:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStockFamilies;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgStockFamily extends OrgAction
{
    public function handle(Organisation $organisation, StockFamily $stockFamily, $modelData = []): OrgStockFamily
    {
        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'code', $stockFamily->code);
        data_set($modelData, 'name', $stockFamily->name);

        data_set(
            $modelData,
            'state',
            match ($stockFamily->state) {
                StockFamilyStateEnum::IN_PROCESS => OrgStockFamilyStateEnum::IN_PROCESS,
                StockFamilyStateEnum::ACTIVE => OrgStockFamilyStateEnum::ACTIVE,
                StockFamilyStateEnum::DISCONTINUING => OrgStockFamilyStateEnum::DISCONTINUING,
                StockFamilyStateEnum::DISCONTINUED => OrgStockFamilyStateEnum::DISCONTINUED,
            }
        );


        /** @var OrgStockFamily $orgStockFamily */
        $orgStockFamily = $stockFamily->orgStockFamilies()->create($modelData);
        $orgStockFamily->stats()->create();
        $orgStockFamily->intervals()->create();
        $orgStockFamily->salesIntervals()->create();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $orgStockFamily->timeSeries()->create(['frequency' => $frequency]);
        }

        OrgStockFamilyHydrateUniversalSearch::dispatch($orgStockFamily);

        OrganisationHydrateOrgStockFamilies::dispatch($organisation)->delay($this->hydratorsDelay);


        return $orgStockFamily;
    }


    public function rules(ActionRequest $request): array
    {
        return [
            'source_id' => 'nullable|string',
        ];
    }

    public function action(Organisation $organisation, StockFamily $stockFamily, $modelData, $hydratorsDelay = 0): OrgStockFamily
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $stockFamily, $this->validatedData);
    }


}
