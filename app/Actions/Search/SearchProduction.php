<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\JobOrder;
use App\Models\Production\ManufactureTask;
use App\Models\Production\RawMaterial;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProduction
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $productionId   = Arr::get($options, 'production_id');
        $organisationId = Arr::get($options, 'organisation_id');
        $routeArguments = [Arr::get($options, 'organisation_slug'), Arr::get($options, 'production_slug')];

        $inProduction = static function (Builder $builder) use ($productionId) {
            return $productionId ? $builder->where('production_id', $productionId) : $builder;
        };

        $sections = [
            'artefact_departments' => [$inProduction(ArtefactDepartment::search($query)), 'grp.org.productions.show.crafts.artefact_departments.show'],
            'artefact_families'    => [$inProduction(ArtefactFamily::search($query)), 'grp.org.productions.show.crafts.artefact_families.show'],
            'artefacts'            => [$inProduction(Artefact::search($query)), 'grp.org.productions.show.crafts.artefacts.show'],
            'raw_materials'        => [$inProduction(RawMaterial::search($query)), 'grp.org.productions.show.crafts.raw_materials.show'],
            'manufacture_tasks'    => [$inProduction(ManufactureTask::search($query)), 'grp.org.productions.show.operations.manufacture_tasks.show'],
            'job_orders'           => [$inProduction(JobOrder::search($query)), 'grp.org.productions.show.operations.job-orders.show'],
        ];

        $results = [];
        foreach ($sections as $section => [$searchQuery, $routeName]) {
            $results[$section] = array_map(
                fn (array $document) => [
                    'id'        => (int)$document['id'],
                    'code'      => $document['code'] ?? null,
                    'name'      => $document['name'] ?? null,
                    'reference' => $document['reference'] ?? null,
                    'state'     => $document['state'] ?? null,
                    'href'      => isset($document['slug']) ? route($routeName, [...$routeArguments, $document['slug']]) : null,
                ],
                $this->rawDocuments($searchQuery)
            );
        }

        $artisansQuery = Employee::search($query);
        if ($organisationId) {
            $artisansQuery->where('organisation_id', $organisationId);
        }

        $results['artisans'] = array_map(
            static fn (array $document) => [
                'id'           => (int)$document['id'],
                'code'         => $document['worker_number'] ?? null,
                'name'         => $document['contact_name'] ?? null,
                'contact_name' => $document['contact_name'] ?? null,
                'state'        => $document['state'] ?? null,
            ],
            $this->rawDocuments($artisansQuery)
        );

        return [
            'scope'   => 'production',
            'results' => $results,
        ];
    }
}
