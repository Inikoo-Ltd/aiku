<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterAssetIndex extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory, array $modelData): void
    {
        if ($masterProductCategory->type !== MasterProductCategoryTypeEnum::FAMILY) {
            abort(403, "Unable to modify this product index");
        }

        $indexOrders = collect(data_get($modelData, 'products', []))->keyBy('id')->toArray();
        $masterAssets = MasterAsset::whereIn('id', array_keys($indexOrders))->get();


        foreach ($masterAssets as $masterAsset) {
            $masterAsset->updateQuietly([
                "index_under_master_{$masterProductCategory->type->value}"    => data_get($indexOrders, "{$masterAsset->id}.index_under_master_{$masterProductCategory->type->value}", null)
            ]);
        };

        // todo hydrate to childs
    }

    public function rules(): array
    {
        return [
            'products.*.id'                            => ['required', 'numeric'],
            'products.*.index_under_master_family'     => ['sometimes', 'numeric', "gte:0"],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $this->validatedData);
    }
}
