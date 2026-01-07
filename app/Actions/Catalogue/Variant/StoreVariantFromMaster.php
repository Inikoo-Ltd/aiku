<?php

/*
 * author Louis Perez
 * created on 30-12-2025-14h-05m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Variant\Traits\WithVariantDataPreparation;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\Masters\MasterVariant;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreVariantFromMaster extends OrgAction
{
    use WithVariantDataPreparation;

    protected MasterVariant $parent;
    protected Shop $shop;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): Variant
    {
        /** @var Variant $variant */
        $variant = DB::transaction(function () use ($modelData) {
            $variant = Variant::create($modelData);

            $variant->salesStats()->create();
            $variant->salesIntervals()->create();
            $variant->salesOrderingStats()->create();
            $variant->salesOrderingIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $variant->timeSeries()->create(['frequency' => $frequency]);
            }

            $leaderProduct = $variant->leaderProduct;

            foreach ($variant->fetchProductFromData() as $product) {
                $isLeader = $leaderProduct->id == $product->id;
                $product->updateQuietly([
                    'variant_id'        => $variant->id,
                    'is_main'           => $isLeader,
                    'is_variant_leader' => $isLeader,
                    'is_minion_variant' => !$isLeader

                ]);

                UpdateWebpage::make()->action($product->webpage()->first(), [
                     'state_data' => [
                         'state'                 => $isLeader ? WebpageStateEnum::LIVE->value : WebpageStateEnum::CLOSED->value,
                         'redirect_webpage_id'   => $leaderProduct->webpage->id
                     ]
                 ]);
            }

            $variant->refresh();

            return $variant;
        });

        return $variant;
    }

    public function prepareForValidation(): void
    {
        // Call function set on Trait. If you need to update logic, update it here, please.
        $this->prepareForVariantCreation();
    }

    public function rules(): array
    {
        return [
            'leader_id'                  => ['required', Rule::exists('products', 'id')->whereNull('variant_id')],
            'family_id'                  => ['required', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::FAMILY)],
            'department_id'              => ['nullable', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::DEPARTMENT)],
            'sub_department_id'          => ['nullable', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)],
            'shop_id'                    => ['required', Rule::exists('shops', 'id')],
            'organisation_id'            => ['required', Rule::exists('organisations', 'id')],
            'group_id'                   => ['required', Rule::exists('groups', 'id')],
            'code'                       => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'variants',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id ?? null],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'slug'                       => ['required', 'string'],
            'number_minions'             => ['required', 'numeric'],
            'number_dimensions'          => ['required', 'numeric'],
            'number_used_slots'          => ['required', 'numeric'],
            'number_used_slots_for_sale' => ['required', 'numeric'],
            'data'                       => ['required', 'array'],
            'data.variants'              => ['required', 'array'],
            'data.groupBy'               => ['required', 'string'],
            'data.products'              => ['required', 'array', 'min:1'],
            'master_variant_id'          => ['required'],
        ];
    }

    public function getValidationMessages(): array
    {
        $validationMessages = [
            'data.groupBy'  => __('A grouping criteria must be selected'),
            'data.products' => __('At least one product must be present in the variant'),
        ];

        if ($this->asAction) {
            $validationMessages = array_merge($validationMessages, [
                'data.leader_id' => __("Unable to assign leader. This master product is missing in one or more Families mapped under the Master Family"),
            ]);
        }

        return $validationMessages;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterVariant $masterVariant, Shop $shop, array $modelData, int $hydratorsDelay = 0): Variant
    {
        $this->parent = $masterVariant;
        $this->shop   = $shop;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($masterVariant->group, $modelData);

        return $this->handle($this->validatedData);
    }
}
