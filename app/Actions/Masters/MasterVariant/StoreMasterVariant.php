<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Dec 2025 11:28:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterVariant;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterVariant
    {
        data_set($modelData, 'master_family_id', $masterProductCategory->id);
        data_set($modelData, 'master_sub_department_id', $masterProductCategory->master_sub_department_id);
        data_set($modelData, 'master_department_id', $masterProductCategory->master_department_id);
        data_set($modelData, 'group_id', $masterProductCategory->group_id);
        data_set($modelData, 'master_shop_id', $masterProductCategory->master_shop_id);


        /** @var MasterVariant $masterVariant */
        $masterVariant = DB::transaction(function () use ($modelData) {
            $masterVariant = MasterVariant::create($modelData);

            // Initialize aggregates/relations
            $masterVariant->stats()->create();
            $masterVariant->salesIntervals()->create();
            $masterVariant->orderingStats()->create();
            $masterVariant->orderingIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterVariant->timeSeries()->create(['frequency' => $frequency]);
            }



            $masterVariant->refresh();

            return $masterVariant;
        });

        return $masterVariant;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_variants',
                    extraConditions: [
                        ['column' => 'master_shop_id', 'value' => $this->masterShop->id ?? null],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'data' => ['sometimes', 'array'],
        ];
    }


    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0): MasterVariant
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterVariant
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }


}
