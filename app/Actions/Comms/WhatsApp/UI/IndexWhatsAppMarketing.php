<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 19 May 2026 16:19:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsApp\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Comms\WhatsAppMarketingResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Closure;
use Lorisleiva\Actions\ActionRequest;

class IndexWhatsAppMarketing extends OrgAction
{
    use HasUIWhatsAppMarketing;
    use WithIndexWhatsAppMarketing;

    public Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        return $this->handleWhatsAppMarketing($parent, $prefix);
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'sent', label: '', icon: 'fal fa-paper-plane', tooltip: __('Sent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivered', label: '', icon: 'fal fa-inbox-in', tooltip: __('Delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: '', icon: 'fal fa-envelope-open', tooltip: __('Opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: '', icon: 'fal fa-hand-pointer', tooltip: __('Clicked'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $campaigns, ActionRequest $request): Response
    {
        $actions = [];
        if ($this->parent instanceof Shop) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('New Campaign'),
                    'route' => [
                        'name'       => 'grp.org.shops.show.marketing.whatsapp.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ]
            ];
        }

        $title = __('WhatsApp Marketing');

        return Inertia::render(
            'Comms/WhatsAppMarketing',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => array_filter([
                    'title'   => $title,
                    'icon'    => ['fab', 'fa-whatsapp'],
                    'actions' => $actions,
                ]),
                'data'        => WhatsAppMarketingResource::collection($campaigns),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
