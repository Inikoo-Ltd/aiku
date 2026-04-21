<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterAssetIndex extends GrpAction
{
    private MasterShop $masterShop;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): void
    {
        if ($masterProductCategory->type !== MasterProductCategoryTypeEnum::FAMILY) {
            abort(403, "Unable to modify this product index");
        }

        $indexOrders = collect(data_get($modelData, 'products', []))->keyBy('code')->toArray();
        $masterAssets = MasterAsset::whereIn('code', array_keys($indexOrders))->where('master_shop_id', $this->masterShop->id)->get();

        foreach ($masterAssets as $masterAsset) {
            $masterAsset->updateQuietly([
                "index_under_master_{$masterProductCategory->type->value}"    => data_get($indexOrders, "{$masterAsset->code}.index_under_master_{$masterProductCategory->type->value}", null)
            ]);
        };

        HydrateIndexFromMasterShopToShops::dispatch($this->masterShop, $masterProductCategory, $indexOrders);
    }

    public function rules(): array
    {
        return [
            'products.*.id'                             => ['required', 'numeric'],
            'products.*.code'                           => ['required', 'string'],
            'products.*.index_under_master_family'      => ['sometimes', 'numeric', "gte:0"],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->masterShop   = $masterProductCategory->masterShop;
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $this->validatedData);
    }
}
