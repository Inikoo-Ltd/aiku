<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 16 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\ArtefactLabel;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexArtefactLabels extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getElementGroups(Production $production): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ArtefactLabelStateEnum::labels(),
                    $this->getStateCounts($production)
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('artefact_labels.state', $elements);
                },
            ],
        ];
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function getStateCounts(Production $production): array
    {
        $counts = ArtefactLabel::join('artefacts', 'artefact_labels.artefact_id', 'artefacts.id')
            ->where('artefacts.production_id', $production->id)
            ->groupBy('artefact_labels.state')
            ->selectRaw('artefact_labels.state, count(*) as number')
            ->pluck('number', 'state');

        $stateCounts = [];

        foreach (ArtefactLabelStateEnum::cases() as $state) {
            $stateCounts[$state->value] = [(int) ($counts[$state->value] ?? 0)];
        }

        return $stateCounts;
    }

    public function handle(Production $production, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('artefact_labels.name', $value)
                    ->orWhereWith('artefacts.code', $value)
                    ->orWhereWith('artefacts.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ArtefactLabel::class)
            ->join('artefacts', 'artefact_labels.artefact_id', 'artefacts.id')
            ->leftJoin('media', 'artefact_labels.artwork_id', 'media.id')
            ->where('artefacts.production_id', $production->id);

        foreach ($this->getElementGroups($production) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $queryBuilder
            ->defaultSort('artefacts.code')
            ->select([
                'artefact_labels.id',
                'artefact_labels.name',
                'artefact_labels.state',
                'artefact_labels.layout',
                'artefact_labels.published_at',
                'artefact_labels.updated_at',
                'artefacts.code as artefact_code',
                'artefacts.name as artefact_name',
                'artefacts.slug as artefact_slug',
                'media.mime_type as artwork_mime_type',
            ])
            ->allowedSorts(['name', 'artefact_code', 'state', 'published_at', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Production $production, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($production, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($production) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('label'), __('labels')])
                ->withEmptyState([
                    'title'       => __('No labels designed yet'),
                    'description' => __('A label is designed on the artefact it belongs to, under its Labels tab.'),
                    'count'       => $this->countLabels($production),
                ])
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'artefact_code', label: __('Artefact'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'artefact_name', label: __('Artefact name'), canBeHidden: true)
                ->column(key: 'name', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'grid', label: __('Grid'), canBeHidden: false)
                ->column(key: 'sources', label: __('Prints'), canBeHidden: false)
                ->column(key: 'published_at', label: __('Published'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('artefact_code');
        };
    }

    private function countLabels(Production $production): int
    {
        return ArtefactLabel::join('artefacts', 'artefact_labels.artefact_id', 'artefacts.id')
            ->where('artefacts.production_id', $production->id)
            ->count();
    }

    public function jsonResponse(LengthAwarePaginator $labels): AnonymousResourceCollection
    {
        return ArtefactLabelsResource::collection($labels);
    }

    public function htmlResponse(LengthAwarePaginator $labels, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ArtefactLabels',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Labels'),
                'pageHead'    => [
                    'model'         => __('Crafts'),
                    'title'         => __('Labels'),
                    'icon'          => ['icon' => ['fal', 'fa-tags'], 'title' => __('Labels')],
                    'subNavigation' => IndexArtefacts::make()->getArtefactsSubNavigation($this->production),
                ],
                'data'        => ArtefactLabelsResource::collection($labels),
            ]
        )->table($this->tableStructure($this->production));
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.labels.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Labels'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
