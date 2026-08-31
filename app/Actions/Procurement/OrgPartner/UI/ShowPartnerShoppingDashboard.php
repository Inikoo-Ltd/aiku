<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPartnerShoppingDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;

    private function pricePerSkoSubQuery(): string
    {
        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
            join org_stocks sos on sos.id = phos.org_stock_id
            where sos.stock_id = partner_shopping_list_items.stock_id
                and sos.organisation_id = partner_shopping_list_items.partner_organisation_id
            limit 1)";
    }

    public function handle(OrgPartner $orgPartner): array
    {
        $estimatedTotal = (float) DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('coalesce(sum(quantity * coalesce('.$this->pricePerSkoSubQuery().', 0)), 0) as total')
            ->value('total');

        $priorityBreakdown = PartnerShoppingListItem::query()
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $recentItems = PartnerShoppingListItem::query()
            ->leftJoin('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->leftJoin('users', 'users.id', 'partner_shopping_list_items.added_by_user_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.created_at',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'users.contact_name as added_by_name',
            ])
            ->orderByDesc('partner_shopping_list_items.created_at')
            ->limit(5)
            ->get();

        return [
            'open_items_count'   => $orgPartner->stats->number_open_shopping_list_items,
            'estimated_total'    => $estimatedTotal,
            'priority_breakdown' => collect(ShoppingListItemPriorityEnum::cases())->map(fn ($priority) => [
                'priority' => $priority->value,
                'label'    => ShoppingListItemPriorityEnum::labels()[$priority->value],
                'count'    => $priorityBreakdown[$priority->value] ?? 0,
            ])->values(),
            'recent_items'       => $recentItems,
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerShoppingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => __('Shopping'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping'),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => __('Shopping'),
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner'  => [
                    'id'       => $this->orgPartner->id,
                    'slug'     => $this->orgPartner->partner->slug,
                    'currency' => $this->orgPartner->partner->currency->code,
                ],
                'browseRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.browse.index',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'shoppingListRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'canBrowse'         => (bool) Arr::get($this->orgPartner->partner->settings, 'procurement.shop_id'),
                'stats'             => [
                    'open_items_count'   => $data['open_items_count'],
                    'estimated_total'    => $data['estimated_total'],
                    'priority_breakdown' => $data['priority_breakdown'],
                ],
                'recentItems'       => $data['recent_items'],
            ]
        );
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowOrgPartner::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.shopping.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Shopping'),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
