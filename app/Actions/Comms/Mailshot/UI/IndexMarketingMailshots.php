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
use App\Http\Resources\Mail\MailshotResource;
use App\Http\Resources\Mail\MarketingMailshotsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'sent', label: __('sent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hard_bounce', label: __('hard bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'soft_bounce', label: __('soft bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivered', label: __('delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: __('opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: __('clicked'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'spam', label: __('spam'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $mailshots): AnonymousResourceCollection
    {
        return MailshotResource::collection($mailshots);
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
