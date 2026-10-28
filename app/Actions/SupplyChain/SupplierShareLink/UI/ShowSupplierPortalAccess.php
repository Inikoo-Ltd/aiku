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
use App\Actions\SupplyChain\SupplierPortalContact\SyncSupplierPortalContact;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\UI\SupplyChain\SupplierShareTabsEnum;
use App\Http\Resources\SupplyChain\SupplierDirectLinksResource;
use App\Http\Resources\SupplyChain\SupplierPortalContactsResource;
use App\Models\SupplyChain\Supplier;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Who at a supplier can see its portal: the contacts allowed to sign in by email and, for suppliers without email, the direct links staff gave.
 */
class ShowSupplierPortalAccess extends OrgAction
{
    use WithSupplierSubNavigation;
    use WithSupplyChainAuthorisation;

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($supplier->group, $request)->withTab(SupplierShareTabsEnum::values());
        SyncSupplierPortalContact::run($supplier);

        return $supplier;
    }

    public function htmlResponse(Supplier $supplier): Response
    {
        return Inertia::render(
            'SupplyChain/SupplierPortalAccess',
            [
                'title'       => __('Supplier portal').' '.$supplier->code,
                'breadcrumbs' => ShowSupplier::make()->getBreadcrumbs($supplier, 'grp.supply-chain.suppliers.show', [], '('.__('Supplier portal').')'),
                'pageHead'    => [
                    'title'         => $supplier->name,
                    'model'         => __('Supplier portal'),
                    'icon'          => [
                        'title' => __('Supplier portal'),
                        'icon'  => 'fal fa-share-alt',
                    ],
                    'subNavigation' => $this->getSupplierNavigation($supplier),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierShareTabsEnum::navigation(),
                ],
                'share'       => GetSupplierShareLinks::run($supplier, $this->canEdit),

                SupplierShareTabsEnum::SELF_LOG_IN->value => $this->tab == SupplierShareTabsEnum::SELF_LOG_IN->value ?
                    fn () => SupplierPortalContactsResource::collection(IndexSupplierPortalContacts::run($supplier, SupplierShareTabsEnum::SELF_LOG_IN->value))->additional(IndexSupplierPortalContacts::make()->facets($supplier, SupplierShareTabsEnum::SELF_LOG_IN->value))
                    : Inertia::optional(fn () => SupplierPortalContactsResource::collection(IndexSupplierPortalContacts::run($supplier, SupplierShareTabsEnum::SELF_LOG_IN->value))->additional(IndexSupplierPortalContacts::make()->facets($supplier, SupplierShareTabsEnum::SELF_LOG_IN->value))),

                SupplierShareTabsEnum::DIRECT_LINKS->value => $this->tab == SupplierShareTabsEnum::DIRECT_LINKS->value ?
                    fn () => SupplierDirectLinksResource::collection(IndexSupplierDirectLinks::run($supplier, SupplierShareTabsEnum::DIRECT_LINKS->value))->additional(IndexSupplierDirectLinks::make()->facets($supplier, SupplierShareTabsEnum::DIRECT_LINKS->value))
                    : Inertia::optional(fn () => SupplierDirectLinksResource::collection(IndexSupplierDirectLinks::run($supplier, SupplierShareTabsEnum::DIRECT_LINKS->value))->additional(IndexSupplierDirectLinks::make()->facets($supplier, SupplierShareTabsEnum::DIRECT_LINKS->value))),
            ]
        )->table(IndexSupplierPortalContacts::make()->tableStructure(SupplierShareTabsEnum::SELF_LOG_IN->value))
            ->table(IndexSupplierDirectLinks::make()->tableStructure(SupplierShareTabsEnum::DIRECT_LINKS->value));
    }
}
