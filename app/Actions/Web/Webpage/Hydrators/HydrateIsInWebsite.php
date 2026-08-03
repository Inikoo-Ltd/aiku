<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Hydrators;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateIsInWebsite
{
    use AsAction;

    /**
     * is_in_website: the model has a live webpage, and for products it also passes the
     * storefront sellable rule (variant leader, or for-sale non-minion). Kept as a column
     * so both SQL and the search index (via toSearchableArray) can filter on it.
     */
    public function handle(Product|ProductCategory|Collection $model): void
    {
        $hasLiveWebpage = (bool) ($model->webpage_id && Webpage::where('id', $model->webpage_id)->where('state', WebpageStateEnum::LIVE)->exists());

        $isInWebsite = $hasLiveWebpage;
        if ($model instanceof Product) {
            $isInWebsite = $hasLiveWebpage && ($model->is_variant_leader || (!$model->is_minion_variant && $model->is_for_sale));
        }

        $modelData = [];
        if ((bool) $model->is_in_website !== $isInWebsite) {
            $modelData['is_in_website'] = $isInWebsite;
        }
        if ($model instanceof Product && (bool) $model->has_live_webpage !== $hasLiveWebpage) {
            $modelData['has_live_webpage'] = $hasLiveWebpage;
        }

        if ($modelData) {
            $model->update($modelData);
        }
    }

    public function fromWebpage(Webpage $webpage): void
    {
        $model = $webpage->model;
        if ($model instanceof Product || $model instanceof ProductCategory || $model instanceof Collection) {
            $this->handle($model);
        }
    }
}
