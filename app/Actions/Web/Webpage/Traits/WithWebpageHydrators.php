<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Dec 2025 12:48:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (AI Assistant)
 * Purpose: Share common webpage hydration/search/cache dispatching logic
 */

namespace App\Actions\Web\Webpage\Traits;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebpages;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateChildWebpages;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Models\Web\Webpage;

trait WithWebpageHydrators
{
    protected function dispatchWebpageHydrators(Webpage $webpage): void
    {
        GroupHydrateWebpages::dispatch($webpage->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWebpages::dispatch($webpage->organisation)->delay($this->hydratorsDelay);
        WebsiteHydrateWebpages::dispatch($webpage->website)->delay($this->hydratorsDelay);
        if ($webpage->parent_id) {
            WebpageHydrateChildWebpages::dispatch($webpage->parent)->delay($this->hydratorsDelay);
        }
    }

    protected function refreshWebpageSearch(Webpage $webpage): void
    {
        WebpageRecordSearch::dispatch($webpage);
    }

    protected function breakWebpageProductCache(Webpage $webpage): void
    {
        BreakProductInWebpagesCache::make()->breakCache($webpage);
    }

    protected function dispatchWebpageHydratorsAndRefresh(Webpage $webpage): void
    {
        $this->dispatchWebpageHydrators($webpage);
        $this->refreshWebpageSearch($webpage);
        $this->breakWebpageProductCache($webpage);
    }
}
