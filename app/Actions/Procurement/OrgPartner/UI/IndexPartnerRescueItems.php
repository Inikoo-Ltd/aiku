<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 2 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPartnerRescueItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgPartnerSubNavigation;

    private OrgPartner $orgPartner;

    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $buckets = GetPartnerStockCoverBuckets::make();

        $paginator = $buckets->rescueItems($orgPartner)->paginate(50)->withQueryString();
        $paginator->getCollection()->transform($buckets->rescueItem(...));

        return $paginator;
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_if($orgPartner->partner->is_manufacturing_hub, 404);

        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        $title = __('What :partner can rescue', ['partner' => $this->orgPartner->partner->name]);

        return Inertia::render(
            'Procurement/PartnerRescueItems',
            [
                'breadcrumbs'   => ShowOrgPartner::make()->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters(), __('Rescue')),
                'title'         => $title,
                'pageHead'      => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-life-ring'],
                        'title' => __('Rescue'),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => __('Rescue'),
                    'subNavigation' => $this->getOrgPartnerNavigation($this->orgPartner),
                ],
                'currency_code' => $this->orgPartner->organisation->currency->code,
                'orgPartner'    => [
                    'id'   => $this->orgPartner->id,
                    'name' => $this->orgPartner->partner->name,
                ],
                'items'         => $items,
            ]
        );
    }
}
