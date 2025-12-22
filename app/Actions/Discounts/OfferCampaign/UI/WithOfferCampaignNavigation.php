<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 10:28:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait WithOfferCampaignNavigation
{
    use WithNavigation;

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var OfferCampaign $model */
        $query->where('shop_id', $model->shop_id);
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var OfferCampaign $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var OfferCampaign $model */
        return [
            'organisation'        => $model->organisation->slug,
            'shop'                => $model->shop->slug,
            'offerCampaign'       => $model->slug,
        ];
    }
}
