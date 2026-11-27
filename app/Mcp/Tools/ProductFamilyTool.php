<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateProductFamily;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * HELP-4998. A product with a master is moved on the master, as the master product edit page does,
 * and UpdateMasterAsset moves every shop product of it whatever the shop's follow settings say.
 * Old web addresses keep working: ShowIrisWebpage resolves the product slug and 301s to the new path.
 */
#[Description('Shows, moves to another family, or switches the golden product flag of a product. Call it first with shop and code only: it shows the product, its family and master family, and every shop product of the same master with its family, golden flag and web address. Call it with family and/or is_golden_product but no request_text to get the plan (per shop, the family it would move to); show that to the user. A product with a master product is moved on the master and every shop product of it moves too; its golden flag is also set on the master for every shop unless only_this_shop=true. To write, pass request_text with the user\'s confirmation in their own words and, when the product has a master, master_product with the master product code the user confirmed. Old web addresses forward to the new page by themselves. Every change is logged and can be reverted with ai-changes-tool. Only for users enrolled to change website content through their assistant.')]
class ProductFamilyTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'              => ['required', 'string'],
            'code'              => ['required', 'string'],
            'family'            => ['sometimes', 'string'],
            'is_golden_product' => ['sometimes', 'boolean'],
            'only_this_shop'    => ['sometimes', 'boolean'],
            'master_product'    => ['sometimes', 'string'],
            'request_text'      => ['sometimes', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_use_mcp_web) {
            return Response::error('Changing website content is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $given = strtolower((string) $request->string('shop'));
        $shop  = Shop::where('group_id', $request->user()->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$given])->orWhereRaw('lower(code) = ?', [$given]))
            ->first();
        if (!$shop || !$this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_VIEW->value, $shop))) {
            return $this->notFoundError('shop', (string) $request->string('shop'), $this->accessibleShops($request), $request);
        }

        $product = Product::where('shop_id', $shop->id)->whereRaw('lower(code) = ?', [strtolower((string) $request->string('code'))])->first();
        if (!$product) {
            return Response::error("'{$request->string('code')}' is not a product in {$shop->code}.");
        }

        $onlyThisShop = $request->boolean('only_this_shop');
        $masterAsset  = $product->masterProduct;
        $onMaster     = $masterAsset && ($request->has('family') || !$onlyThisShop);
        $products     = $onMaster ? $masterAsset->products()->with('shop:id,code')->orderBy('shop_id')->get() : $product->newCollection([$product]);

        if ($masterAsset && $onlyThisShop && $request->has('family')) {
            return Response::error('A family move is always made on the master product, for every shop, so only_this_shop cannot go with family. Make two calls: one moving the family, one setting the golden flag with only_this_shop. Nothing was changed.');
        }

        $changes = [];
        if ($request->has('is_golden_product')) {
            $changes['is_golden_product'] = $request->boolean('is_golden_product');
        }

        $targetFamilies = null;
        if ($request->has('family')) {
            $targetFamilies = $this->targetFamilies($request, $shop, $masterAsset, $products);
            if (is_string($targetFamilies)) {
                return Response::error($targetFamilies);
            }
            $changes['family'] = (string) $request->string('family');
        }

        $summary = $this->summary($product, $masterAsset, $products, $targetFamilies, $changes['is_golden_product'] ?? null);

        if (!$changes || !$request->get('request_text')) {
            return Response::json([
                ...$summary,
                'changed' => false,
                'note'    => $changes
                    ? 'Nothing was changed. Show the user this plan; once they confirm, call again with request_text'.($onMaster ? " and master_product={$masterAsset->code}" : '').'.'
                    : 'Pass family and/or is_golden_product to plan a change.',
            ]);
        }

        if ($targetFamilies && $missing = $targetFamilies->filter(fn ($family) => !$family)->keys()->all()) {
            return Response::error('Nothing was changed. These shops have no family '.$request->string('family').', so their product would be left without a family: '.implode(', ', $missing).'. Create the family there first.');
        }

        if ($targetFamilies && $withoutFamily = $products->filter(fn (Product $shopProduct) => !$shopProduct->family_id)->pluck('shop.code')->all()) {
            return Response::error('Nothing was changed. The product has no family in '.implode(', ', $withoutFamily).', so the move could not be undone from the change log; an engineer places it.');
        }

        if ($onMaster && $targetFamilies && !$masterAsset->master_family_id) {
            return Response::error("Nothing was changed. Master product {$masterAsset->code} has no master family, so the move could not be undone from the change log; an engineer places it.");
        }

        if ($onMaster) {
            if (strtolower((string) $request->string('master_product')) !== strtolower($masterAsset->code)) {
                return Response::error("Nothing was changed. {$product->code} follows master product {$masterAsset->code}, so the change is made on the master and reaches these shops: ".$products->pluck('shop.code')->implode(', ').". Show the user and once they confirm call again with master_product={$masterAsset->code}.");
            }
            if (!$this->userCan($request, 'masters.edit')) {
                return Response::error('This change is made on the master product, which needs master edit permission this user does not have. Nothing was changed.');
            }
        } elseif (!$this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_EDIT->value, $shop))) {
            return Response::error("This user cannot edit products in {$shop->code}. Nothing was changed.");
        }

        try {
            DB::transaction(fn () => $this->recordChange(
                $request,
                McpChangeTypeEnum::PRODUCT_FAMILY,
                ($onMaster ? "Master product {$masterAsset->code}" : "Product {$product->code} in {$shop->code}").': '
                    .collect($changes)->map(fn ($value, string $field) => $field.'='.(is_bool($value) ? ($value ? 'yes' : 'no') : $value))->implode(', '),
                ['master_asset_id' => $onMaster ? $masterAsset->id : null, 'product_ids' => $products->pluck('id')->all()],
                function () use ($onMaster, $masterAsset, $products, $targetFamilies, $changes) {
                    if ($onMaster) {
                        UpdateMasterAsset::make()->action($masterAsset, array_filter([
                            'master_family_id'  => $targetFamilies ? $this->masterFamily($masterAsset, $changes['family'])->id : null,
                            'is_golden_product' => $changes['is_golden_product'] ?? null,
                        ], fn ($value) => $value !== null));
                    }

                    foreach ($products as $shopProduct) {
                        $shopProduct->refresh();
                        $family = $targetFamilies?->get($shopProduct->shop->code);
                        if ($family && $shopProduct->family_id !== $family->id) {
                            UpdateProductFamily::run($shopProduct, ['family_id' => $family->id]);
                        }
                        if (array_key_exists('is_golden_product', $changes) && $shopProduct->is_golden_product !== $changes['is_golden_product']) {
                            UpdateProduct::make()->action($shopProduct, ['is_golden_product' => $changes['is_golden_product']]);
                        }
                    }
                },
                ['shops' => $products->pluck('shop.code')->all()]
            ));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            ...$this->summary($product->refresh(), $masterAsset?->refresh(), $products->each->refresh(), null, null),
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            'note'          => 'Web pages refresh in the next minutes; the old web addresses forward to the new pages.',
        ]);
    }

    /**
     * The family each shop product would move to, keyed by shop code; null where that shop lacks it.
     *
     * @return Collection<string, ProductCategory|null>|string
     */
    private function targetFamilies(Request $request, Shop $shop, ?MasterAsset $masterAsset, Collection $products): Collection|string
    {
        $code = (string) $request->string('family');

        if (!$masterAsset) {
            $family = ProductCategory::where('shop_id', $shop->id)->where('type', ProductCategoryTypeEnum::FAMILY)->whereRaw('lower(code) = ?', [strtolower($code)])->first();

            return $family ? collect([$shop->code => $family]) : "'$code' is not a family in {$shop->code}.";
        }

        $masterFamily = $this->masterFamily($masterAsset, $code);
        if (!$masterFamily) {
            return "'$code' is not a master family of master product {$masterAsset->code}'s master shop.";
        }

        $families = ProductCategory::where('master_product_category_id', $masterFamily->id)->where('type', ProductCategoryTypeEnum::FAMILY)->get()->keyBy('shop_id');

        return $products->mapWithKeys(fn (Product $shopProduct) => [$shopProduct->shop->code => $families->get($shopProduct->shop_id)]);
    }

    private function masterFamily(MasterAsset $masterAsset, string $code): ?MasterProductCategory
    {
        return MasterProductCategory::where('master_shop_id', $masterAsset->master_shop_id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->whereRaw('lower(code) = ?', [strtolower($code)])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Product $product, ?MasterAsset $masterAsset, Collection $products, ?Collection $targetFamilies, ?bool $golden): array
    {
        $products->loadMissing(['family:id,code,name', 'webpage:id,canonical_url', 'shop:id,code']);

        return [
            'product'        => $product->code,
            'name'           => $product->name,
            'master_product' => $masterAsset ? [
                'code'              => $masterAsset->code,
                'master_family'     => $masterAsset->masterFamily?->code,
                'is_golden_product' => (bool) $masterAsset->is_golden_product,
            ] : null,
            'shop_products'  => $products->map(fn (Product $shopProduct) => array_filter([
                'shop'                   => $shopProduct->shop->code,
                'code'                   => $shopProduct->code,
                'family'                 => $shopProduct->family?->code,
                'is_golden_product'      => (bool) $shopProduct->is_golden_product,
                'url'                    => $shopProduct->webpage?->canonical_url,
                'would_move_to_family'   => $targetFamilies ? ($targetFamilies->get($shopProduct->shop->code)?->code ?? 'MISSING: this shop has no such family') : null,
                'would_be_golden'        => $golden,
            ], fn ($value) => $value !== null))->values()->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'              => $schema->string()->description('Shop slug or code')->required(),
            'code'              => $schema->string()->description('Product code in that shop')->required(),
            'family'            => $schema->string()->description('Family code to move it to: the master family code when the product has a master product, else a family of this shop. Omit to leave the family as it is'),
            'is_golden_product' => $schema->boolean()->description('true = golden product, false = not golden. Omit to leave it as it is'),
            'only_this_shop'    => $schema->boolean()->description('true = set the golden flag only on this shop\'s product, not on the master. Ignored for family moves, which always go on the master'),
            'master_product'    => $schema->string()->description('Required to write when the product has a master product: the master product code the user confirmed'),
            'request_text'      => $schema->string()->description('The user\'s confirmation, verbatim, kept in the change log; required to write'),
        ];
    }
}
