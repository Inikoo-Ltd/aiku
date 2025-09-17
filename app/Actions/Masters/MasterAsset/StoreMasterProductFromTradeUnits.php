<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterProductFromTradeUnits extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;
    use WithAttachMediaToModel;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterFamily;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, array $modelData): MasterAsset
    {
        $tradeUnits = Arr::pull($modelData, 'trade_units', []);
        $shopProducts = Arr::pull($modelData, 'shop_products', []);

        if (!Arr::has($modelData, 'unit') && count($tradeUnits) == 1) {
            data_set($modelData, 'unit', Arr::get($tradeUnits, '0.type'));
        }

        

        $masterAsset = DB::transaction(function () use ($parent, $modelData, $tradeUnits, $shopProducts) {
            // dd($modelData);
            $data        = [
                'code'    => Arr::get($modelData, 'code'),
                'name'    => Arr::get($modelData, 'name'),
                'unit'    => Arr::get($modelData, 'unit'),
                'description'             => Arr::get($modelData, 'description'),
                'description_title'       => Arr::get($modelData, 'description_title'),
                'description_extra'       => Arr::get($modelData, 'description_extra'),
                'units'                   => Arr::get($modelData, 'units', 1),
                'marketing_weight'        => Arr::get($modelData, 'marketing_weight', 0),
                'gross_weight'            => Arr::get($modelData, 'gross_weight', 0),
                'marketing_dimensions'    => Arr::get($modelData, 'marketing_dimensions', []),
                'is_main' => true,
                'type'    => MasterAssetTypeEnum::PRODUCT,
                'trade_units'  => $tradeUnits,
                'shop_products' => $shopProducts
            ];

            $masterAsset = StoreMasterAsset::make()->action($parent, $data);

            if (Arr::has($modelData, 'image') && !$masterAsset->is_single_trade_unit) {
                $medias = UploadImagesToMasterProduct::run($masterAsset, 'image', [
                    'images' => [
                        Arr::get($modelData, 'image')
                    ]
                ]);

                UpdateMasterProductImages::run($masterAsset, [
                    'image_id' => Arr::get($medias, '0.id')
                ]);
            }

            $masterAsset->refresh();
            return $masterAsset;
        });

        MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
        GroupHydrateMasterAssets::dispatch($parent->group)->delay($this->hydratorsDelay);


        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'code'                     => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'master_shop_id', 'value' => $this->masterFamily->master_shop_id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'name'                   => ['required', 'string'],
            'unit'                   => ['sometimes', 'string'],
            'units'                  => ['sometimes', 'nullable'],
            'description'            => ['sometimes', 'string', 'nullable'],
            'description_title'      => ['sometimes', 'string', 'nullable'],
            'description_extra'      => ['sometimes', 'string', 'nullable'],
            'price'                  => ['sometimes', 'numeric', 'min:0'],
            'marketing_weight'       => ['sometimes', 'numeric', 'min:0'],
            'trade_units'            => [
                'required',
                'array'
            ],
            'trade_units.*.id'       => [
                'required',
                'integer',
                'exists:trade_units,id'
            ],
            'trade_units.*.quantity' => [
                'required',
                'numeric',
                'min:1'
            ],
            'shop_products' => ['sometimes', 'array'],
            'shop_products.*.price'       => [
                'required',
                'numeric',
                'min:0'
            ],
            'image' => ["sometimes", "mimes:jpg,png,jpeg,gif", "max:50000"],
            'marketing_weight'       => ['sometimes', 'numeric', 'min:0'],
            'gross_weight'           => ['sometimes', 'numeric', 'min:0'],
            'marketing_dimensions'   => ['sometimes'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterFamily, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): MasterAsset
    {
        if (!$audit) {
            MasterAsset::disableAuditing();
        }
        $this->masterFamily = $masterFamily;

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisation($masterFamily->group, $modelData);

        return $this->handle($masterFamily, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterFamily, ActionRequest $request): MasterAsset
    {
        $this->masterFamily = $masterFamily;

        $this->initialisation($masterFamily->group, $request);
        return $this->handle($masterFamily, $this->validatedData);
    }
}
