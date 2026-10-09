<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Intervention\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\UI\IndexOrgPartners;
use App\Actions\Procurement\OrgPartner\UI\ShowPartnerShoppingDashboard;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexInterventions extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $this->organisation->is_manufacturing_hub && $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_procurement.{$this->production->id}.view",
            "productions_procurement.{$this->production->id}.edit",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    /**
     * The organisations that buy from this hub, as each one's own buyers see the hub on their partners page.
     *
     * @return Collection<int, OrgPartner>
     */
    public function handle(Organisation $hub): Collection
    {
        return OrgPartner::where('partner_id', $hub->id)
            ->where('organisation_id', '!=', $hub->id)
            ->with(['organisation.country', 'organisation.currency', 'partner'])
            ->get()
            ->sortBy(fn (OrgPartner $orgPartner) => $orgPartner->organisation->code)
            ->values();
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Collection
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    /**
     * @return array<string, mixed>
     */
    public function buyerCard(OrgPartner $orgPartner): array
    {
        $buyer         = $orgPartner->organisation;
        $shoppingList  = IndexOrgPartners::make()->shoppingListRows($orgPartner);
        $draft         = $shoppingList->firstWhere('state', ShoppingListItemStateEnum::DRAFT->value);
        $deliveryState = StockDeliveryStateEnum::labels();

        return [
            'id'            => $orgPartner->id,
            'code'          => $buyer->code,
            'name'          => $buyer->name,
            'country_code'  => $buyer->country?->code,
            'country_name'  => $buyer->country?->name,
            'currency_code' => $buyer->currency?->code,
            'rescuable'     => GetPartnerStockCoverBuckets::make()->rescuable($orgPartner),
            'on_list'       => ['lines' => $draft['lines'] ?? 0, 'cost' => $draft['value'] ?? 0],
            'current'       => $shoppingList
                ->map(fn (array $row) => collect($row)->except('url')->all())
                ->concat(collect(ShowPartnerShoppingDashboard::make()->openStockDeliveries($orgPartner))->map(fn (array $stockDelivery) => [
                    'type'        => 'stock_delivery',
                    'reference'   => $stockDelivery['reference'],
                    'state'       => $stockDelivery['state'],
                    'state_label' => $deliveryState[$stockDelivery['state']] ?? $stockDelivery['state'],
                    'lines'       => $stockDelivery['items'],
                    'date'        => $stockDelivery['date'],
                ]))
                ->values()
                ->all(),
        ];
    }

    public function htmlResponse(Collection $orgPartners, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/Interventions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Intervention'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-hands-helping'],
                        'title' => __('Intervention'),
                    ],
                    'title' => __('Intervention'),
                ],
                'can_order'   => $request->user()->authTo([
                    'org-supervisor.'.$this->organisation->id,
                    "productions_procurement.{$this->production->id}.edit",
                    "productions_operations.{$this->production->id}.orchestrate",
                ]),
                'production'  => $this->production->slug,
                'buyers'      => $orgPartners->map(fn (OrgPartner $orgPartner) => $this->buyerCard($orgPartner))->all(),
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProduction::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.intervention.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Intervention'),
                        'icon'  => 'fal fa-hands-helping',
                    ],
                ],
            ]
        );
    }
}
