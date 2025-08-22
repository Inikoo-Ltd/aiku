<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterAssets extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, array $modelData)
    {
        dd('xx');
        foreach (Arr::get($modelData, 'trade_units', []) as $tradeUnitId) {
            $tradeUnit = TradeUnit::find($tradeUnitId);
            $data = [
                // TODO
            ];
            StoreMasterAsset::make()->action($parent, $data);
        }
    }

    public function rules(): array
    {
        $rules = [
            'trade_units'                     => [
                'required',
                'array'
            ],
        ];

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true)
    {
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisation($parent->group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterFamily, ActionRequest $request)
    {
        $this->initialisation($masterFamily->group, $request);

        return $this->handle($masterFamily, $this->validatedData);
    }

}
