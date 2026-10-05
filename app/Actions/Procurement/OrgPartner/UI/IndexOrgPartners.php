<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexOrgPartners extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * @return Collection<int, OrgPartner>
     */
    public function handle(Organisation $organisation): Collection
    {
        return OrgPartner::where('organisation_id', $organisation->id)
            ->with(['partner.country', 'partner.currency', 'stats'])
            ->get()
            ->sortBy(fn (OrgPartner $orgPartner) => [!$orgPartner->partner->is_manufacturing_hub, $orgPartner->partner->code])
            ->values();
    }

    public function asController(Organisation $organisation, ActionRequest $request): Collection
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /**
     * @return array<string, mixed>
     */
    public function partnerCard(OrgPartner $orgPartner): array
    {
        $partner = $orgPartner->partner;
        $stats   = $orgPartner->stats;

        return [
            'id'            => $orgPartner->id,
            'code'          => $partner->code,
            'name'          => $partner->name,
            'country_code'  => $partner->country?->code,
            'country_name'  => $partner->country?->name,
            'currency_code' => $partner->currency?->code,
            'is_hub'        => $partner->is_manufacturing_hub,
            'stats'         => $partner->is_manufacturing_hub
                ? [
                    'open_shopping_list_items'       => (int) $stats?->number_open_shopping_list_items,
                    'open_shopping_list_items_value' => round((float) $stats?->open_shopping_list_items_value * $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner), 2),
                ]
                : $this->sisterStats($orgPartner),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sisterStats(OrgPartner $orgPartner): array
    {
        $organisationSlug = $orgPartner->organisation->slug;

        $purchaseOrders = $orgPartner->purchaseOrders()
            ->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
            ->whereDoesntHave('stockDeliveries')
            ->orderByDesc('created_at')
            ->get(['id', 'slug', 'reference', 'state', 'number_purchase_order_transactions', 'submitted_at', 'created_at'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'type'        => 'purchase_order',
                'reference'   => $purchaseOrder->reference,
                'state'       => $purchaseOrder->state->value,
                'state_label' => $purchaseOrder->state->labels()[$purchaseOrder->state->value],
                'lines'       => (int) $purchaseOrder->number_purchase_order_transactions,
                'date'        => $purchaseOrder->submitted_at ?? $purchaseOrder->created_at,
                'url'         => route('grp.org.procurement.org_partners.show.purchase-orders.show', [$organisationSlug, $orgPartner->id, $purchaseOrder->slug]),
            ]);

        $stockDeliveryStateLabels = StockDeliveryStateEnum::labels();
        $stockDeliveries          = collect(ShowPartnerShoppingDashboard::make()->openStockDeliveries($orgPartner))
            ->map(fn (array $stockDelivery) => [
                'type'        => 'stock_delivery',
                'reference'   => $stockDelivery['reference'],
                'state'       => $stockDelivery['state'],
                'state_label' => $stockDeliveryStateLabels[$stockDelivery['state']] ?? $stockDelivery['state'],
                'lines'       => $stockDelivery['items'],
                'date'        => $stockDelivery['date'],
                'url'         => route('grp.org.procurement.org_partners.show.stock-deliveries.show', [$organisationSlug, $orgPartner->id, $stockDelivery['slug']]),
            ]);

        return [
            'purchase_orders'   => (int) $orgPartner->stats?->number_purchase_orders,
            'last_submitted_at' => $orgPartner->purchaseOrders()->max('submitted_at'),
            'current'           => $stockDeliveries->concat($purchaseOrders)->values()->all(),
            'rescuable'         => GetPartnerStockCoverBuckets::make()->rescuable($orgPartner),
        ];
    }

    public function htmlResponse(Collection $orgPartners, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/Partners',
            [
                'breadcrumbs'   => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'         => __('Partners'),
                'pageHead'      => [
                    'model' => __('Procurement'),
                    'icon'  => ['fal', 'fa-users-class'],
                    'title' => __('Partners'),
                ],
                'currency_code' => $this->organisation->currency->code,
                'can_create_purchase_orders' => $this->canEdit,
                'partners'      => $orgPartners->map(fn (OrgPartner $orgPartner) => $this->partnerCard($orgPartner))->all(),
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.org_partners.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Partners'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            ),
        };
    }
}
