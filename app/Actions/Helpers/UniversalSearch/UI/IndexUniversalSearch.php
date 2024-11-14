<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 23:07:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\UniversalSearch\UI;

use App\Actions\InertiaAction;
use App\Http\Resources\Helpers\UniversalSearchResource;
use App\Models\Helpers\UniversalSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexUniversalSearch extends InertiaAction
{
    use AsController;


    public function handle(
        string $query,
        ?array $sections,
        ?string $organisationSlug,
        ?string $shopSlug,
        ?string $warehouseSlug,
        ?string $websiteSlug,
        ?string $fulfilmentSlug
    ): Collection {



        $query = UniversalSearch::search($query)->where('group_id', group()->id);


        // dd($sections, $organisationSlug, $shopSlug, $warehouseSlug, $websiteSlug, $fulfilmentSlug);
        if ($sections && count($sections) > 0) {
            $query->whereIn('sections', $sections);
        }

        if ($organisationSlug) {
            $query->where('organisation_slug', $organisationSlug);
        }

        if ($shopSlug) {
            $query->where('shop_slug', $shopSlug);
        }

        if ($warehouseSlug) {
            $query->where('warehouse_slug', $warehouseSlug);
        }

        if ($websiteSlug) {
            $query->where('website_slug', $websiteSlug);
        }

        if ($fulfilmentSlug) {
            $query->where('fulfilment_slug', $fulfilmentSlug);
        }


        return $query->get();
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        $searchResults = $this->handle(
            query: $request->input('q', ''),
            sections: $this->parseSections($request->input('route_src')),
            organisationSlug: $request->input('organisation'),
            shopSlug: $request->input('shop'),
            warehouseSlug: $request->input('warehouse'),
            websiteSlug: $request->input('website'),
            fulfilmentSlug: $request->input('fulfilment'),
        );
        return UniversalSearchResource::collection($searchResults);
    }

    public function parseSections($routeName): array|null
    {
        if (str_starts_with($routeName, 'grp.org.')) {
            return $this->parseOrganisationSections(
                preg_replace('/^grp\.org./', '', $routeName)
            );
        }
        return null;
    }

    public function parseOrganisationSections($route): array|null
    {
        $routes = [
            'accounting.' => ['accounting'],
            'productions.' => ['productions'],
            'fulfilments.' => ['fulfilments'],
            'procurement.' => ['procurement'],
            'reports.' => ['reports'],
            'shops.show.assets.' => ['assets'],
            'shops.show.catalogue.' => ['catalogue'],
            'shops.show.mail.' => ['mail'],
            'shops.show.marketing.' => ['marketing'],
            'show.discounts.' => ['discounts'],
            'shops.show.ordering.' => ['ordering'],
            'shops.show.web.' => ['web'],
            'shops.show.crm.' => ['crm'],
            'hr.' => ['hr']
        ];

        if (empty($route)) {
            return array_unique(array_merge(...array_values($routes)));
        }

        foreach ($routes as $prefix => $result) {
            if (str_starts_with($route, $prefix)) {
                return $result;
            }
        }

        // if (str_starts_with($route, 'accounting.')) {
        //     return ['accounting'];
        // }
        // if (str_starts_with($route, 'productions.')) {
        //     return ['productions'];
        // }
        // if (str_starts_with($route, 'fulfilments.')) {
        //     return ['fulfilments'];
        // }
        // if (str_starts_with($route, 'procurement.')) {
        //     return ['procurement'];
        // }
        // if (str_starts_with($route, 'reports.')) {
        //     return ['reports'];
        // }
        // if (str_starts_with($route, 'shops.show.assets.')) {
        //     return ['assets'];
        // }
        // if (str_starts_with($route, 'shops.show.catalogue.')) {
        //     return ['catalogue'];
        // }
        // if (str_starts_with($route, 'shops.show.mail.')) {
        //     return ['mail'];
        // }
        // if (str_starts_with($route, 'shops.show.marketing.')) {
        //     return ['marketing'];
        // }
        // if (str_starts_with($route, 'show.discounts.')) {
        //     return ['discounts'];
        // }
        // if (str_starts_with($route, 'shops.show.ordering.')) {
        //     return ['ordering'];
        // }
        // if (str_starts_with($route, 'shops.show.dashboard')) {
        //     return ['dashboard'];
        // }
        // if (str_starts_with($route, 'show')) {
        //     return ['show'];
        // }
        // if (str_starts_with($route, 'warehouses.')) {
        //     return ['warehouses'];
        // }
        // if (str_starts_with($route, 'shops.show.web.')) {
        //     return ['web'];
        // }
        // if (str_starts_with($route, 'shops.show.crm.')) {
        //     return ['crm'];
        // }

        // if (str_starts_with($route, 'hr.')) {
        //     return ['hr'];
        // }


        return null;
    }


}
