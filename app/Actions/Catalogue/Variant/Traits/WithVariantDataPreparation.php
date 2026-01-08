<?php

/*
 * author Louis Perez
 * created on 16-12-2025-15h-36m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Variant\Traits;

use Illuminate\Support\Arr;
use App\Models\Masters\MasterAsset;
use App\Models\Catalogue\Product;
use App\Http\Resources\Catalogue\ProductResourceForVariant;
use App\Enums\Catalogue\Product\ProductStateEnum;

trait WithVariantDataPreparation
{
    /**
     * Prepare and normalize Variant data for Create/Update actions.
     * THIS ONLY WORKS FOR VARIANT, DO NOT USE ON MASTER VARIANT
     */
    protected ?array $leadItem = null;

    public function prepareForVariantCreation(): void
    {
        $this->prepareForeignKeys();
        $this->prepareVariantData();
        $this->prepareCodeSlug();
    }

    public function prepareForVariantUpdate(): void
    {
        $this->prepareVariantData();
    }

    private function prepareVariantData(): void
    {
        $masterProductList = MasterAsset::whereIn(
            'id',
            array_keys(data_get($this->data, 'products', []))
        )->get()->keyBy('id');

        $products = collect(data_get($this->data, 'products'))
            ->mapWithKeys(function ($variant) use ($masterProductList) {
                $product = $masterProductList[data_get($variant, 'product.id')]
                    ->products()
                    ->where('shop_id', $this->shop->id)
                    // ->whereNot('state', ProductStateEnum::DISCONTINUED)
                    ->first();

                if (!$product) {
                    return [];
                }

                $data = Arr::except($variant, 'product');
                data_set($data, 'product', ProductResourceForVariant::make($product)->resolve());

                return [$product->id => $data];
            })
            ->filter()
            ->toArray();

        $this->set('data.products', $products);

        $this->leadItem = collect($products)->where('is_leader', true)->first();

        $this->leader_id = data_get($this->leadItem, 'product.id');
        $options = data_get($this->data['variants'], '*.options');
        $this->number_minions             = (count($options) > 1 ? count($options[0]) * count($options[1]) : count($options[0])) - 1;
        $this->number_dimensions          = count($this->data['variants']);
        $this->number_used_slots          = count($products);
        $this->number_used_slots_for_sale = Product::whereIn('id', array_keys($products))
            ->where('is_for_sale', true)
            ->count();
    }

    private function prepareForeignKeys(): void
    {
        $this->family_id = $this->parent->masterFamily
            ->productCategories
            ->where('shop_id', $this->shop->id)
            ->firstOrFail()
            ->id;

        $this->department_id = $this->parent->masterDepartment
            ? $this->parent->masterDepartment->productCategories()
                ->where('shop_id', $this->shop->id)
                ->first()
                ?->id
            : null;

        $this->sub_department_id = $this->parent->masterSubDepartment
            ? $this->parent->masterSubDepartment->productCategories()
                ->where('shop_id', $this->shop->id)
                ->first()
                ?->id
            : null;

        $this->organisation_id = $this->shop->organisation_id;
        $this->group_id        = $this->shop->group->id;
        $this->shop_id         = $this->shop->id;

        $this->master_variant_id = $this->parent->id;
    }

    private function prepareCodeSlug(): void
    {
        $code = data_get($this->leadItem, 'product.code');
        $this->set('code', $code);
        $this->set('slug', strtolower($code));
    }
}
