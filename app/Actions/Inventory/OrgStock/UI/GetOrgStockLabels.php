<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Inventory\OrgStock;
use App\Models\Production\ArtefactLabel;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the label editor needs, for a SKO page or for an artefact, which reach the same labels
 * through different routes and permissions.
 */
class GetOrgStockLabels
{
    use AsObject;

    /**
     * @param  string  $routePrefix  'grp.models.org_stock.' or 'grp.models.artefact.'
     * @param  array<string, int>  $routeParameters
     * @param  array{edit: bool, publish: bool, set_mandatory: bool}  $abilities
     */
    public function handle(OrgStock $orgStock, string $routePrefix, array $routeParameters, array $abilities): array
    {
        $information = GetOrgStockLabelInformation::run($orgStock);
        $route       = fn (string $name) => ['name' => $routePrefix.$name, 'parameters' => $routeParameters];

        $labels = $orgStock->labels()->with(['artwork', 'orgStock'])->get();

        return array_merge($information, [
            'route'                 => $route('label_sheet'),
            'store_route'           => $route('labels.store'),
            'update_route'          => $route('labels.update'),
            'delete_route'          => $route('labels.delete'),
            'publish_route'         => $route('labels.publish'),
            'unpublish_route'       => $route('labels.unpublish'),
            'on_artwork_route'      => $route('labels.on_artwork'),
            'mandatory_route'       => [
                'name'       => 'grp.models.org_stock.label_mandatory_information.update',
                'parameters' => ['orgStock' => $orgStock->id],
            ],
            'information'           => $information,
            'information_options'   => array_map(
                fn (ArtefactLabelInformationEnum $option) => [
                    'value'        => $option->value,
                    'label'        => ArtefactLabelInformationEnum::labels()[$option->value],
                    'is_placeable' => $option->isPlaceable(),
                ],
                ArtefactLabelInformationEnum::cases()
            ),
            'mandatory_information' => $orgStock->label_mandatory_information ?? [],
            'abilities'             => $abilities,
            'labels'                => $labels->map(fn (ArtefactLabel $label) => array_merge(
                ArtefactLabelResource::make($label)->resolve(),
                [
                    'pdf_url' => $label->state === ArtefactLabelStateEnum::PUBLISHED
                        ? route($routePrefix.'labels.pdf', array_merge($routeParameters, ['label' => $label->id]))
                        : null,
                ]
            ))->all(),
        ]);
    }
}
