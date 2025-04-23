<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 23:07:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\UniversalSearch\UI;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\UniversalSearchResource;
use App\Models\Helpers\UniversalSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexUniversalSearch extends OrgAction
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


        $query = trim($query);
        $query = preg_replace('/(\+|\-|\=|&&|\|\||\>|\<|\!|\(|\)|\{|\}|\[|\]|\^|"|~|\*|\?|\:|\\\\|\/)/', '\\\\$1', $query);
        $query = preg_replace('/\b(AND|OR|NOT)\b/', '\\\\$0', $query);
        $query = UniversalSearch::search($query)->where('group_id', group()->id);


        // dd($sections, $organisationSlug, $shopSlug, $warehouseSlug, $websiteSlug, $fulfilmentSlug);
        if ($sections && count($sections) > 0) {
            $query->whereIn('sections', array_values($sections));
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
        // $param = Arr::except($request->all(), ['route_src', 'q']);
        // $aikuScopedSection = GetSectionRoute::make()->run($request->input('route_src'), $param);
        // dd($aikuScopedSection->code);
        $searchResults = $this->handle(
            query: $request->input('q', '') ?? '',
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
        if (str_starts_with($routeName, 'grp.')) {
            return $this->parseGroupSections(
                preg_replace('/^grp\./', '', $routeName)
            );
        }
        return null;
    }

    public function parseGroupSections($route): array|null
    {
        $routes = [
            'goods.stock-families.' => [],
            'goods.' => [],
        ];

        return null;
    }

    public function parseOrganisationSections($route): array|null
    {
        $routes = [
            'shops.show.crm.' => 'crm',
            'ordering.' => 'ordering',

            'fulfilments.show.crm' => 'fulfilment',
            'fulfilments.show.catalogue.rentals' => 'fulfilment',
            'procurement.' => 'procurement',
            'pallets.' => 'fulfilment',
            'stored_items.' => 'fulfilment',
            'stock_deliveries.' => 'fulfilment',
            'pallet_deliveries.' => 'fulfilment',
            'pallet_returns.' => 'fulfilment',
            'productions.' => 'productions',
        ];

        if (empty($route) || str_starts_with($route, "dashboard.") || str_starts_with($route, "settings.")) {
            $sections = array_values($routes);
            return array_unique($sections);
        }

        $sections = [];
        foreach ($routes as $prefix => $result) {
            if (str_contains($route, $prefix)) {
                $sections[] = $result;
            }
        }

        return $sections;
    }
}
