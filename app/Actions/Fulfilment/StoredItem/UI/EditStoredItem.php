<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\InertiaAction;
use App\Actions\UI\Fulfilment\ShowFulfilmentsDashboard;
use App\Enums\Fulfilment\StoredItem\StoredItemTypeEnum;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStoredItem extends InertiaAction
{
    public function handle(StoredItem $storedItem): StoredItem
    {
        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('fulfilment.edit');

        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('hr.view')
            );
    }


    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }


    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('stored items'),
                'pageHead'    => [
                    'title'     => __('stored items'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Item'),
                            'icon'   => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'reference' => [
                                    'type'    => 'input',
                                    'label'   => __('reference'),
                                    'value'   => $storedItem->reference,
                                    'required'=> true
                                ],
                                'type' => [
                                    'type'    => 'select',
                                    'label'   => __('type'),
                                    'value'   => $storedItem->type,
                                    'required'=> true,
                                    'options' => StoredItemTypeEnum::values()
                                ],
                                'location' => [
                                    'type'     => 'combobox',
                                    'label'    => __('location'),
                                    'value'    => '',
                                    'required' => true,
                                    'apiUrl'   => route('grp.json.locations') . '?filter[slug]=',
                                ]
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stored-items.update',
                            'parameters' => $storedItem->slug
                        ],
                    ]
                ],
            ]
        );
    }

    public function asController(StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->initialisation($request);

        return $this->handle($storedItem);
    }


    public function getBreadcrumbs(): array
    {
        return array_merge(
            (new ShowFulfilmentsDashboard())->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.fulfilment.stored-items.index'
                        ],
                        'label' => __('stored items'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
