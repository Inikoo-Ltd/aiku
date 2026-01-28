<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Jan 2026 14:08:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductCategoryOffersData
{
    use asAction;

    public function handle(Offer $offer): void
    {
        $offerData = null;
        if ($offer->status) {
            $offerData = $this->getBasicOfferData($offer);
        }

        $model = $this->getTriggerModel($offer);
        if (!$model) {
            return;
        }

        $modelOfferData = $model->offers_data ?? [];
        if (!$offerData) {
            if (isset($modelOfferData['offers'])) {
                unset($modelOfferData['offers'][$offer->id]);
            }
        } else {
            $modelOfferData['offers'][$offer->id] = $offerData;
        }
        $modelOfferData['number_offers'] = count(Arr::get($modelOfferData, 'offers', []));
        $modelOfferData                  = $this->getBestOffers($modelOfferData);
        $modelOfferData['v']             = 1;
        $model->update(['offers_data' => $modelOfferData]);

        if ($model instanceof ProductCategory) {
            foreach ($model->getProducts() as $product) {
                $productOfferData = $product->offers_data ?? [];
                if (!$offerData) {
                    if (isset($productOfferData['offers'])) {
                        unset($productOfferData['offers'][$offer->id]);
                    }
                } else {
                    $productOfferData['offers'][$offer->id] = $offerData;
                }
                $productOfferData['number_offers'] = count(Arr::get($productOfferData, 'offers', []));
                $productOfferData                  = $this->getBestOffers($productOfferData);
                $productOfferData['v']             = 1;

                $product->update(['offers_data' => $productOfferData]);
            }
        }
    }


    public function getBasicOfferData(Offer $offer): array|null
    {
        $allowances      = [];
        $offerAllowances = $offer->offerAllowances()->where('status', true)->get();

        $maxPercentageDiscount = 0;

        foreach ($offerAllowances as $offerAllowance) {
            if ($offerAllowance && $offerAllowance->class) {
                $percentageOff = Arr::get($offerAllowance->data, 'percentage_off', '');
                if ($percentageOff && $percentageOff > $maxPercentageDiscount) {
                    $maxPercentageDiscount = $percentageOff;
                }

                $allowanceData = [
                    'class'          => $offerAllowance->class->value,
                    'type'           => $offerAllowance->type->value,
                    'label'          => $this->getAllowanceLabel($offerAllowance),
                    'percentage_off' => $percentageOff
                ];

                $allowances[] = $allowanceData;
            }
        }

        if ($maxPercentageDiscount == 0) {
            $maxPercentageDiscount = '';
        }

        if (empty($allowances)) {
            return null;
        }


        $triggerLabels           = [];
        $categoryQuantityTrigger = null;
        $productsTriggerLabel    = null;

        $currentLocale = app()->getLocale();
        $locale        = $offer->shop->language->code;
        app()->setLocale($locale);

        $categoryLink = '';
        /** @var ProductCategory $category */
        $category = $offer->trigger;
        if ($category) {
            $categoryLink = $category->code;
            if ($category->webpage && $category->webpage->state == WebpageStateEnum::LIVE) {
                $categoryLink = '<a href="'.e($category->webpage->canonical_url).'" class="underline">'.e($category->code).'</a>';
            }
        }

        $percentage = $maxPercentageDiscount;
        if ($percentage == '') {
            $percentage = 'X';
        } else {
            $percentage = percentage($maxPercentageDiscount, 1, null);
        }

        if ($offer->type == 'Category Quantity Ordered Order Interval') {
            $triggerLabels[] = __('Order :n or more', ['n' => $offer->trigger_data['item_quantity']]);
            $triggerLabels[] = __('Order with in :n days', ['n' => $offer->trigger_data['interval']]);

            $categoryQuantityTrigger = $offer->trigger_data['item_quantity'];


            $productsTriggerLabel = __('Order :n+ from :category range to get :percentage off', [
                'n'          => (int)$offer->trigger_data['item_quantity'],
                'category'   => $categoryLink,
                'percentage' => $percentage
            ]);
        } elseif ($offer->type == 'Category Ordered') {
            $triggerLabels[] = __('Order any product in this range');

            $productsTriggerLabel = __('Order any product from :category range to get :percentage off', [
                'category'   => $categoryLink,
                'percentage' => $percentage
            ]);
        }
        app()->setLocale($currentLocale);


        $offerData = [
            'id'                      => $offer->id,
            'state'                   => $offer->state->value,
            'type'                    => $offer->type,
            'duration'                => $offer->duration->value,
            'label'                   => $offer->label ?? $offer->name,
            'allowances'              => $allowances,
            'triggers_labels'         => $triggerLabels,
            'products_triggers_label' => $productsTriggerLabel,
            'note'                    => '',
            'max_percentage_discount' => $maxPercentageDiscount,

        ];

        if ($categoryQuantityTrigger) {
            $offerData['category_qty_trigger'] = $categoryQuantityTrigger;
        }

        if ($offer->duration->value == OfferDurationEnum::INTERVAL) {
            $offerData['start_at'] = $offer->start_at;
            $offerData['end_at']   = $offer->end_at;
        }

        return $offerData;
    }

    public function getBestOffers(array $offersData): array
    {
        if (!Arr::get($offersData, 'number_offers')) {
            unset($offersData['best_percentage_off']);

            return $offersData;
        }

        $bestPercentageOff        = 0;
        $bestPercentageOffOfferId = null;

        foreach (Arr::get($offersData, 'offers', []) as $offerId => $offerData) {
            $maxPercentageDiscount = $offerData['max_percentage_discount'] ?? 0;
            if ($maxPercentageDiscount && $maxPercentageDiscount > $bestPercentageOff) {
                $bestPercentageOff        = $maxPercentageDiscount;
                $bestPercentageOffOfferId = $offerId;
            }
        }

        $offersData['best_percentage_off'] = [
            'percentage_off' => $bestPercentageOff,
            'offer_id'       => $bestPercentageOffOfferId
        ];

        return $offersData;
    }

    protected function getAllowanceLabel(OfferAllowance $offerAllowance): string
    {
        $label = '';
        if ($offerAllowance->type == OfferAllowanceType::PERCENTAGE_OFF) {
            $label = percentage($offerAllowance->data['percentage_off'], 1);
        }

        return $label;
    }

    protected function getTriggerModel(Offer $offer): Product|ProductCategory|Collection|Shop|null
    {
        return $offer->trigger;
    }

}
