<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Models\Web\Website;

trait WithSearchMerchandising
{
    /**
     * Props for the shared boost + synonym admin widget (SearchMerchandising.vue).
     *
     * @param array<string, string> $routeParameters
     *
     * @return array<string, mixed>
     */
    protected function searchMerchandisingProps(Website $website, array $routeParameters): array
    {
        $routes = [];
        foreach ([
            'boost_candidates' => 'grp.org.shops.show.web.analytics.search.boost_candidates',
            'boosts_update'    => 'grp.org.shops.show.web.analytics.search.boosts.update',
            'synonyms_index'   => 'grp.org.shops.show.web.analytics.search.synonyms.index',
            'synonyms_store'   => 'grp.org.shops.show.web.analytics.search.synonyms.store',
            'synonyms_delete'  => 'grp.org.shops.show.web.analytics.search.synonyms.delete',
            'suggestions_index'  => 'grp.org.shops.show.web.analytics.search.synonym_suggestions.index',
            'suggestions_decide' => 'grp.org.shops.show.web.analytics.search.synonym_suggestions.decide',
        ] as $key => $name) {
            $routes[$key] = ['name' => $name, 'parameters' => $routeParameters];
        }

        return [
            'search_boosts'    => UpdateWebsiteSearchBoosts::currentBoosts($website),
            'synonym_language' => $website->shop->language->name,
            'routes'           => $routes,
        ];
    }
}
