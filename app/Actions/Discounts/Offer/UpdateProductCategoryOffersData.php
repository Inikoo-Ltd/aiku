<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Jan 2026 14:08:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
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
            unset($modelOfferData[$offer->id]);
        } else {
            $modelOfferData[$offer->id] = $offerData;
        }

        $model->update(['offers_data' => $modelOfferData]);
    }


    public function getBasicOfferData(Offer $offer): array|null
    {
        $allowances      = [];
        $offerAllowances = $offer->offerAllowances()->where('status', true)->get();
        foreach ($offerAllowances as $offerAllowance) {
            if ($offerAllowance && $offerAllowance->class) {
                $allowances[] = [
                    'class' => $offerAllowance->class->value,
                    'type'  => $offerAllowance->type->value,
                    'label' => $this->getAllowanceLabel($offerAllowance)
                ];
            }
        }

        if (empty($allowances)) {
            return null;
        }


        $triggerLabels = [];

        if ($offer->type == 'Category Quantity Ordered Order Interval') {
            $triggerLabels[] = __('Order :n or more', ['n' => $offer->trigger_data['item_quantity']]);
            $triggerLabels[] = __('Order with in :n days', ['n' => $offer->trigger_data['interval']]);
        }




        $offerData = [
            'state'      => $offer->state->value,
            'duration'   => $offer->duration->value,
            'label'      => $offer->label ?? $offer->name,
            'allowances' => $allowances,
            'triggers_labels' => $triggerLabels,
            'note'       => ''
        ];

        if ($offer->duration->value == OfferDurationEnum::INTERVAL) {
            $offerData['start_at'] = $offer->start_at;
            $offerData['end_at']   = $offer->end_at;
        }

        return $offerData;
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
