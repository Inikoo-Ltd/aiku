<?php
/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-10h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateForSale;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateProductVariants;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class MoveFamilyProductToOtherFamily extends OrgAction
{

    use WithActionUpdate;

    public function handle(Product $product, array $modelData): Product
    {
        $product = $this->update($product, $modelData);
        $product->refresh();

        FamilyHydrateProducts::dispatch($product->family);

        return $product;
    }

    public function rules(): array
    {
        $rules = [
            'family_id' => ['required', Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)->where('type', ProductCategoryTypeEnum::FAMILY)],
        ];

        return $rules;
    }

    public function asController(Product $product, ActionRequest $request)
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, $this->validatedData);
    }

}
