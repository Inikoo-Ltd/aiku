<?php

/*
 * Author Louis Perez
 * Created on 28-09-2026-09h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreProductsStepDiscount extends OrgAction
{
    /**
     * @return Collection<int, Offer>
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Collection
    {
        $productIds = Arr::pull($modelData, 'product_ids');

        $products = Product::where('shop_id', $shop->id)
            ->whereIn('id', $productIds)
            ->orderBy('code')
            ->get();

        return DB::transaction(function () use ($products, $modelData) {
            return $products->map(
                fn (Product $product) => StoreProductStepDiscount::make()->action($product, $modelData)
            );
        });
    }

    public function rules(): array
    {
        return [
            'product_ids'            => ['required', 'array', 'min:1'],
            'product_ids.*'          => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'name'                   => ['sometimes', 'string', 'max:255'],
            'duration'               => ['required', 'string', 'in:interval,permanent'],
            'steps'                  => ['required', 'array', 'min:1'],
            'steps.*.min_quantity'   => ['required', 'integer', 'min:1', 'distinct'],
            'steps.*.percentage_off' => ['required', 'numeric', 'gt:0', 'lte:1'],
            'steps.*.is_popular'     => ['sometimes', 'boolean'],
            'start_at'               => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'                 => ['nullable', 'required_if:duration,interval', 'date'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $stepOfferCampaign = OfferCampaign::where('shop_id', $this->shop->id)
            ->where('type', OfferCampaignTypeEnum::STEP_OFFERS)
            ->first();

        if (!$stepOfferCampaign) {
            $validator->errors()->add('product_ids', __('This shop has no step offers campaign'));

            return;
        }

        $productCodesWithStepDiscount = Product::whereIn('id', (array)$this->get('product_ids'))
            ->whereExists(function ($query) use ($stepOfferCampaign) {
                $query->select(DB::raw(1))
                    ->from('offers')
                    ->where('offers.offer_campaign_id', $stepOfferCampaign->id)
                    ->where('offers.trigger_type', 'Product')
                    ->whereColumn('offers.trigger_id', 'products.id')
                    ->whereIn('offers.state', [
                        OfferStateEnum::ACTIVE->value,
                        OfferStateEnum::IN_PROCESS->value,
                        OfferStateEnum::SUSPENDED->value,
                    ])
                    ->whereNull('offers.deleted_at');
            })
            ->orderBy('code')
            ->pluck('code');

        if ($productCodesWithStepDiscount->isNotEmpty()) {
            $validator->errors()->add(
                'product_ids',
                __('These products already have a step discount: :codes', ['codes' => $productCodesWithStepDiscount->implode(', ')])
            );
        }
    }

    /**
     * @return Collection<int, Offer>
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @param  Collection<int, Offer>  $offers
     * @return array{number_offers: int, slugs: array<int, string>}
     */
    public function jsonResponse(Collection $offers): array
    {
        return [
            'number_offers' => $offers->count(),
            'slugs'         => $offers->pluck('slug')->all(),
        ];
    }

    /**
     * @return Collection<int, Offer>
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData): Collection
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
