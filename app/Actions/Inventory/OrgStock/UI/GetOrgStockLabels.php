<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Production\Artefact\Label\GetArtefactLabelIconSource;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Helpers\Language;
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
            'information_options'   => $this->getInformationOptions($information),
            'icons'                 => GetArtefactLabelIconSource::make()->forBrowser($this->getIcons($information)),
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

    /**
     * @param  array<string, string>  $information
     * @return array<int, array{value: string, label: string, is_icon: bool, can_be_typed: bool}>
     */
    private function getInformationOptions(array $information): array
    {
        $languageNames = Language::whereIn('code', array_filter(array_map(
            fn (string $source) => ArtefactLabelInformationEnum::parse($source)[1],
            array_keys($information)
        )))->pluck('name', 'code')->all();

        return array_map(
            function (string $source) use ($languageNames) {
                [$option, $languageCode] = ArtefactLabelInformationEnum::parse($source);

                return [
                    'value'        => $source,
                    'label'        => ArtefactLabelInformationEnum::label($source, $languageNames),
                    'is_icon'      => $option->isIcon(),
                    'can_be_typed' => $languageCode !== null || $option === ArtefactLabelInformationEnum::FREE_TEXT,
                ];
            },
            array_keys($information)
        );
    }

    /**
     * @param  array<string, string>  $information
     * @return array<int, string>
     */
    private function getIcons(array $information): array
    {
        $icons = [];
        foreach ($information as $source => $text) {
            if (ArtefactLabelInformationEnum::parse($source)[0]?->isIcon()) {
                $icons = array_merge($icons, array_filter(explode(',', $text)));
            }
        }

        return array_values(array_unique($icons));
    }
}
