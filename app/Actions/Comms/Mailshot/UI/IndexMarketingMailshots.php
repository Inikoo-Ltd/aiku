<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Http\Resources\Mail\MarketingMailshotsResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexMarketingMailshots extends OrgAction
{
    use HasUIMailshots;
    use WithCatalogueAuthorisation;
    use WithIndexMailshots;

    public Group|Outbox|PostRoom|Organisation|Shop $parent;

    public function handle(Group|Outbox|PostRoom|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        return $this->handleMailshot(OutboxCodeEnum::MARKETING, $parent, $prefix);
    }

    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {

        $actions = [
            [
                'type'    => 'button',
                'style'   => 'create',
                'label'   => __('mailshot'),
                'route'   => [
                    'name'       => 'grp.org.shops.show.marketing.mailshots.create',
                    'parameters' => array_values($request->route()->originalParameters())
                ]
            ]
        ];

        $title = __('mailshots');
        $model = __('marketing');
        if ($this->parent instanceof Group) {
            $actions = [];
            $title = __('marketing mailshots');
        }

        return Inertia::render(
            'Comms/Mailshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->parent
                ),
                'title'       => $title,
                'pageHead'    => array_filter([
                    'title'    => $title,
                    'icon'     => 'fal fa-mail-bulk',
                    'model'    => $model,
                    'actions'  => $actions,
                ]),
                'data' => MarketingMailshotsResource::collection($mailshots),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        return $this->handle($organisation);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
