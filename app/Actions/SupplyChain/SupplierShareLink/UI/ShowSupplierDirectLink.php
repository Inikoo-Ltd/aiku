<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\UI\SupplyChain\SupplierPortalAccessTabsEnum;
use App\Http\Resources\SupplyChain\SupplierPortalVisitsResource;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\SupplyChain\SupplierShareLinkView;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One direct link staff gave a supplier: what it is and every visit made with it.
 */
class ShowSupplierDirectLink extends OrgAction
{
    use WithSupplierSubNavigation;
    use WithSupplyChainAuthorisation;

    private Supplier $supplier;

    public function asController(Supplier $supplier, SupplierShareLink $supplierShareLink, ActionRequest $request): SupplierShareLink
    {
        abort_if($supplierShareLink->supplier_id !== $supplier->id, 404);
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request)->withTab(SupplierPortalAccessTabsEnum::values());

        return $supplierShareLink;
    }

    public function htmlResponse(SupplierShareLink $link): Response
    {
        $state = $link->revoked_at ? 'stopped' : ($link->isLive() ? 'active' : 'expired');

        return $this->page($this->supplier, $link, $this->tab, __('Direct link'), 'fal fa-link', $link->reference(), [
            ['label' => __('Status'), 'type' => 'state', 'value' => $state],
            ['label' => __('Created'), 'type' => 'date', 'value' => $link->created_at, 'note' => $link->createdBy?->contact_name],
            ['label' => __('Works until'), 'type' => 'date', 'value' => $link->expires_at, 'empty' => __('Forever')],
            ['label' => __('Stopped'), 'type' => 'date', 'value' => $link->revoked_at, 'hidden' => !$link->revoked_at],
            ...$this->visitFacts($link),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visitFacts(SupplierShareLink|SupplierPortalContact $parent): array
    {
        $views = SupplierShareLinkView::query()
            ->join('supplier_share_links', 'supplier_share_links.id', 'supplier_share_link_views.supplier_share_link_id')
            ->when(
                $parent instanceof SupplierShareLink,
                fn ($query) => $query->where('supplier_share_link_views.supplier_share_link_id', $parent->id),
                fn ($query) => $query->where('supplier_share_links.supplier_portal_contact_id', $parent->id)
            );

        $last = (clone $views)->leftJoin('ip_geolocations', 'ip_geolocations.id', 'supplier_share_link_views.ip_geolocation_id')
            ->latest('supplier_share_link_views.created_at')
            ->first(['supplier_share_link_views.created_at', 'supplier_share_link_views.ip', 'supplier_share_link_views.country_code', 'ip_geolocations.city']);

        return [
            ['label' => __('Visits'), 'type' => 'number', 'value' => (clone $views)->count()],
            ['label' => __('Places'), 'type' => 'number', 'value' => (clone $views)->distinct()->count(DB::raw('coalesce(supplier_share_link_views.ip_geolocation_id::text, supplier_share_link_views.ip)'))],
            ['label' => __('Last visit'), 'type' => 'date', 'value' => $last?->created_at],
            ['label' => __('Last from'), 'type' => 'from', 'value' => $last ? ['ip' => $last->ip, 'country_code' => $last->country_code, 'city' => $last->city] : null],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $overview
     */
    public function page(Supplier $supplier, SupplierShareLink|SupplierPortalContact $parent, string $tab, string $model, string $icon, string $title, array $overview): Response
    {
        $visits = fn () => SupplierPortalVisitsResource::collection(IndexSupplierPortalVisits::make()->handle($parent, SupplierPortalAccessTabsEnum::VISITS->value));

        return Inertia::render('SupplyChain/SupplierPortalAccessDetail', [
            'title'       => $model.' '.$title,
            'breadcrumbs' => ShowSupplier::make()->getBreadcrumbs($supplier, 'grp.supply-chain.suppliers.show', [], '('.__('Supplier portal').' › '.$title.')'),
            'pageHead'    => [
                'title'         => $title,
                'model'         => $model,
                'icon'          => ['title' => $model, 'icon' => $icon],
                'subNavigation' => $this->getSupplierNavigation($supplier),
            ],
            'tabs'        => [
                'current'    => $tab,
                'navigation' => SupplierPortalAccessTabsEnum::navigation(),
            ],

            SupplierPortalAccessTabsEnum::OVERVIEW->value => array_values(array_filter($overview, fn (array $fact) => empty($fact['hidden']))),
            SupplierPortalAccessTabsEnum::VISITS->value   => $tab === SupplierPortalAccessTabsEnum::VISITS->value ? $visits : Inertia::optional($visits),
        ])->table(IndexSupplierPortalVisits::make()->tableStructure($parent, SupplierPortalAccessTabsEnum::VISITS->value));
    }
}
