<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Actions\Web\Website\UpdateWebsiteSearchFeaturedItems;
use App\Models\Web\Website;

trait WithSearchMerchandising
{
    /**
     * Props for the shared boost + featured items + synonym admin widget (SearchMerchandising.vue).
     *
     * @param array<string, string> $routeParameters
     *
     * @return array<string, mixed>
     */
    protected function searchMerchandisingProps(Website $website, array $routeParameters): array
    {
        $routes = [];
        foreach ([
            'boost_candidates'      => 'grp.org.shops.show.web.analytics.search.boost_candidates',
            'boosts_update'         => 'grp.org.shops.show.web.analytics.search.boosts.update',
            'featured_items_update' => 'grp.org.shops.show.web.analytics.search.featured_items.update',
            'synonyms_index'        => 'grp.org.shops.show.web.analytics.search.synonyms.index',
            'synonyms_store'        => 'grp.org.shops.show.web.analytics.search.synonyms.store',
            'synonyms_delete'       => 'grp.org.shops.show.web.analytics.search.synonyms.delete',
            'suggestions_index'     => 'grp.org.shops.show.web.analytics.search.synonym_suggestions.index',
            'suggestions_decide'    => 'grp.org.shops.show.web.analytics.search.synonym_suggestions.decide',
        ] as $key => $name) {
            $routes[$key] = ['name' => $name, 'parameters' => $routeParameters];
        }

        return [
            'search_boosts'               => UpdateWebsiteSearchBoosts::currentBoosts($website),
            'search_featured_items'       => UpdateWebsiteSearchFeaturedItems::currentFeaturedItems($website),
            'max_featured_items_per_type' => UpdateWebsiteSearchFeaturedItems::MAX_FEATURED_ITEMS_PER_TYPE,
            'synonym_language'            => $website->shop->language->name,
            'routes'                      => $routes,
        ];
    }
}
