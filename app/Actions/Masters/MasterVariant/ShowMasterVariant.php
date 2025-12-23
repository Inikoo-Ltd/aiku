<?php

/*
 * author Louis Perez
 * created on 23-12-2025-13h-27m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Closure;

class ShowMasterVariant extends OrgAction
{

    private MasterProductCategory $parent;

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request)
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request);

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant)
    {
        $masterProductInVariant = MasterAsset::whereIn('id', data_get($masterVariant->data, 'products.*.product.id'))->get();
        dd($masterVariant, $productInVariant);
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
