<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:33 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Market\Shop\UI;

use App\Actions\InertiaAction;
use App\Enums\Market\Shop\ShopTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

/**
 * @property array $breadcrumbs
 * @property bool $canEdit
 * @property string $title
 */
class CreateShopsBySpreadSheet extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->can('shops');

        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('shops.edit')
            );
    }

    public function asController(ActionRequest $request)
    {
        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModelBySpreadSheet',
            [
                'title'     => __('shops'),
                'pageHead'  => [
                    'title' => __('Upload shops'),
                ],
                'sheetData' => [

                    'columns' => [
                        [
                            'id'             => 'code',
                            'name'           => __('Code'),
                            'columnType'     => 'string',
                            'prop'           => 'code',
                            'required'       => true,
                        ],
                        [
                            'id'             => 'name',
                            'name'           => __('Label'),
                            'columnType'     => 'string',
                            'prop'           => 'name',
                            'required'       => true,
                        ],
                        [
                            'id'             => 'number',
                            'name'           => __('Number'),
                            'columnType'     => 'numeric',
                            'prop'           => 'number',
                            'required'       => true,
                        ],

                        [
                            'id'         => 'select',
                            'name'       => __('Type'),
                            'prop'       => 'type',
                            'required'   => false,
                            'columnType' => "select",
                            'options'    => Options::forEnum(ShopTypeEnum::class),
                            'labelKey'   => 'label',
                            'valueKey'   => 'value',
                        ],

                    ],
                ],
                'saveRoute' => [
                    'name' => 'models.shop.store-multi',
                ]

            ]
        );
    }


}
