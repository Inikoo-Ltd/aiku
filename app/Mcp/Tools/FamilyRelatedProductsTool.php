<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Catalogue\ProductCategory\RelatedProducts\SyncProductCategoryRelatedProducts;
use App\Actions\Catalogue\ProductCategory\UI\GetProductCategoryRecomendation;
use App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProducts\SyncMasterProductCategoryRelatedMasterAssets;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Website write tools are gated by can_use_mcp_web on the user's account, on top of the
 * edit permission the same change needs in the UI. Shops following the master catalogue
 * are changed on the master family, the only place the UI lets that list be edited.
 */
#[Description('Shows or replaces the related products ("Sells well with") of a family in a shop. Without product_codes it only shows the current list and where it is edited. With product_codes it replaces the whole list in that order (pass the full list, not only additions; an empty list clears it). When the shop follows the master catalogue the change is made on the master family and reaches every shop following it: show the user that master family and the shops following it, and pass master_family only after they confirmed it; a write without it is refused. Only write after the user confirmed in their own words, passing their request text. Only for users enrolled to change website content through their assistant.')]
class FamilyRelatedProductsTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'            => ['required', 'string'],
            'family'          => ['required', 'string'],
            'product_codes'   => ['sometimes', 'array', 'max:50'],
            'product_codes.*' => ['string'],
            'request_text'    => ['required_with:product_codes', 'string', 'max:4000'],
            'master_family'   => ['sometimes', 'string'],
        ]);

        if (!$request->user()?->can_use_mcp_web) {
            return Response::error('Changing website content is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $shop = Shop::whereRaw('lower(slug) = ?', [strtolower((string) $request->string('shop'))])
            ->orWhereRaw('lower(code) = ?', [strtolower((string) $request->string('shop'))])
            ->first();
        if (!$shop || !$this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_VIEW->value, $shop))) {
            return $this->notFoundError('shop', (string) $request->string('shop'), $this->accessibleShops($request), $request);
        }

        $family = ProductCategory::where('shop_id', $shop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereRaw('lower(code) = ?', [strtolower((string) $request->string('family'))])
            ->first();
        if (!$family) {
            return Response::error("'{$request->string('family')}' is not a family in {$shop->code}.");
        }

        $followsMaster = $family->master_product_category_id && data_get($shop->settings, 'catalog.related_product_follow_master', false);

        if (!$request->has('product_codes')) {
            return Response::json($this->summary($shop, $family, $followsMaster));
        }

        $codes    = array_values(array_unique(array_map('trim', $request->get('product_codes'))));
        $products = Product::where('shop_id', $shop->id)
            ->whereIn(DB::raw('lower(code)'), array_map('strtolower', $codes))
            ->get()
            ->keyBy(fn (Product $product) => strtolower($product->code));

        $missing = array_values(array_filter($codes, fn ($code) => !$products->has(strtolower($code))));
        if ($missing) {
            return Response::error('Unknown product codes in '.$shop->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }
        $ordered = collect($codes)->map(fn ($code) => $products->get(strtolower($code)));

        try {
            if ($followsMaster) {
                $masterFamily = $family->masterProductCategory;
                if (strtolower((string) $request->string('master_family')) !== strtolower($masterFamily->code)) {
                    return Response::error("Nothing was changed. {$shop->code} follows the master catalogue, so this list is edited on master family {$masterFamily->code} ({$masterFamily->name}) and reaches every shop following it: ".implode(', ', $this->shopsFollowingMaster($family)).". Show the user this master family and these shops, and once they confirm call again with master_family={$masterFamily->code}.");
                }

                if (!$this->userCan($request, 'masters.edit')) {
                    return Response::error("{$shop->code} follows the master catalogue, so this list is edited on the master family, which needs master edit permission this user does not have. Nothing was changed.");
                }

                $withoutMaster = $ordered->filter(fn (Product $product) => !$product->master_product_id)->pluck('code');
                if ($withoutMaster->isNotEmpty()) {
                    return Response::error('These products have no master product, so they cannot go on the master family: '.$withoutMaster->implode(', ').'. Nothing was changed.');
                }

                $this->recordChange(
                    $request,
                    McpChangeTypeEnum::RELATED_PRODUCTS,
                    "Related products of master family {$masterFamily->code} (asked from {$shop->code})",
                    ['level' => 'master', 'id' => $masterFamily->id],
                    fn () => SyncMasterProductCategoryRelatedMasterAssets::make()->action($masterFamily, [
                        'master_asset_ids' => $ordered->pluck('master_product_id')->all(),
                    ]),
                    ['shops' => $this->shopsFollowingMaster($family)]
                );
            } else {
                if (!$this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_EDIT->value, $shop))) {
                    return Response::error("This user cannot edit products in {$shop->code}. Nothing was changed.");
                }

                $this->recordChange(
                    $request,
                    McpChangeTypeEnum::RELATED_PRODUCTS,
                    "Related products of family {$family->code} in {$shop->code}",
                    ['level' => 'shop', 'id' => $family->id],
                    fn () => SyncProductCategoryRelatedProducts::make()->action($family, [
                        'product_ids' => $ordered->pluck('id')->all(),
                    ]),
                    ['shops' => [$shop->code]]
                );
            }
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            'changed'        => true,
            'change_log_id'  => $this->mcpChange?->id,
            ...$this->summary($shop, $family->refresh(), $followsMaster),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Shop $shop, ProductCategory $family, bool $followsMaster): array
    {
        $summary = [
            'shop'             => $shop->code,
            'family'           => $family->code,
            'edited_on'        => $followsMaster ? 'master family '.$family->masterProductCategory->code.' ('.$family->masterProductCategory->name.')' : 'this shop only',
            'related_products' => $followsMaster
                ? $family->masterProductCategory->relatedMasterAssets()->pluck('code')->all()
                : GetProductCategoryRecomendation::run($family)->pluck('code')->all(),
        ];

        if ($followsMaster) {
            $summary['master_family']          = $family->masterProductCategory->code;
            $summary['shops_following_master'] = $this->shopsFollowingMaster($family);
        }

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    private function shopsFollowingMaster(ProductCategory $family): array
    {
        return $family->masterProductCategory->productCategories()
            ->with('shop:id,code,settings')
            ->get()
            ->filter(fn (ProductCategory $productCategory) => data_get($productCategory->shop->settings, 'catalog.related_product_follow_master', false))
            ->map(fn (ProductCategory $productCategory) => $productCategory->shop->code)
            ->values()
            ->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'          => $schema->string()->description('Shop slug or code')->required(),
            'family'        => $schema->string()->description('Family code in that shop')->required(),
            'product_codes' => $schema->array()->items($schema->string())->description('Full new list of related product codes of this shop, in display order, up to 50. Omit to only show the current list'),
            'request_text'  => $schema->string()->description('The user\'s request, verbatim; required when writing'),
            'master_family' => $schema->string()->description('Required to write when the shop follows the master catalogue: the master family code shown to the user and confirmed by them'),
        ];
    }
}
