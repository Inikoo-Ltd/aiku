<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Actions\Production\JobOrder\BatchedUnitsForDemand;
use App\Actions\Production\Restock\GetProductionStockCoverBuckets;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowToRestock extends OrgAction
{
    private const TODO_LIMIT = 120;

    private const DEFAULT_BUCKETS = ['out', 'w1', 'w2'];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
            "productions_procurement.{$this->production->id}.view",
        ]);
    }

    /**
     * @param array<int, string> $buckets
     *
     * @return array<int, array<string, mixed>> artefacts worth making, urgent first
     */
    public function toDoLane(Production $production, array $buckets): array
    {
        $artefactIds = collect($buckets)
            ->flatMap(fn (string $bucket) => GetProductionStockCoverBuckets::make()->artefactIdsInBucket($production, $bucket))
            ->unique()
            ->all();

        if (!$artefactIds) {
            return [];
        }

        return DB::table('artefacts as a')
            ->leftJoin('org_stocks as os', 'os.id', 'a.org_stock_id')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->leftJoin('artefact_departments as ad', 'ad.id', 'a.artefact_department_id')
            ->leftJoin('employees as e', 'e.id', DB::raw("coalesce(
                (select employee_id from artisan_assignments where artisanable_type = 'Artefact' and artisanable_id = a.id order by position limit 1),
                (select employee_id from artisan_assignments where artisanable_type = 'ArtefactDepartment' and artisanable_id = ad.id order by position limit 1)
            )"))
            ->whereIn('a.id', $artefactIds)
            ->whereRaw("not exists (select 1 from partner_shopping_list_items sli
                where sli.stock_id = os.stock_id
                    and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                    and sli.deleted_at is null
                    and sli.pre_picked_at is null
                    and (sli.partner_organisation_id = os.organisation_id or (sli.partner_organisation_id is null and sli.organisation_id = os.organisation_id)))")
            ->select([
                'a.id as artefact_id',
                'os.id as org_stock_id',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.health_rank',
                'os.quantity_available as stock_available',
                's.days_of_cover',
                's.recommended_order_quantity',
                'a.recommended_batch_size as batch_size',
                'os.packed_in',
                'ad.name as family',
                'e.contact_name as maker',
            ])
            ->orderByRaw('s.days_of_cover nulls last')
            ->limit(self::TODO_LIMIT)
            ->get()
            ->map(function ($row) {
                $row->job_units = BatchedUnitsForDemand::run(
                    (float) $row->recommended_order_quantity,
                    $row->packed_in,
                    $row->batch_size
                );

                return (array) $row;
            })
            ->all();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function movingLanes(Organisation $seller, Production $production): array
    {
        $lines = DB::table('partner_shopping_list_items as i')
            ->join('stocks as st', 'st.id', 'i.stock_id')
            ->join('org_stocks as os', function ($join) use ($seller) {
                $join->on('os.stock_id', 'st.id')->where('os.organisation_id', $seller->id);
            })
            ->join('artefacts as a', function ($join) use ($production) {
                $join->on('a.org_stock_id', 'os.id')
                    ->where('a.production_id', $production->id)
                    ->whereNull('a.deleted_at');
            })
            ->leftJoin('artefact_departments as ad', 'ad.id', 'a.artefact_department_id')
            ->leftJoin('job_orders as jo', 'jo.id', 'i.job_order_id')
            ->leftJoin('employees as e', 'e.id', 'jo.employee_id')
            ->leftJoin('organisations as o', function ($join) {
                $join->on('o.id', 'i.organisation_id')->whereNotNull('i.partner_organisation_id');
            })
            ->where('i.state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('i.deleted_at')
            ->where(function ($query) use ($seller) {
                $query->where('i.partner_organisation_id', $seller->id)
                    ->orWhere(function ($query) use ($seller) {
                        $query->whereNull('i.partner_organisation_id')->where('i.organisation_id', $seller->id);
                    });
            })
            ->select([
                'i.id',
                'i.quantity',
                'i.priority',
                'i.job_order_id',
                'a.id as artefact_id',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.health_rank',
                'os.quantity_available as stock_available',
                'ad.name as family',
                'o.code as buyer_code',
                'jo.reference as job_order_reference',
                'jo.slug as job_order_slug',
                'jo.state as job_order_state',
                'e.contact_name as maker',
            ])
            ->orderBy('i.created_at')
            ->get()
            ->map(fn ($row) => (array) $row);

        return [
            'queued'    => $lines->whereNull('job_order_id')->values()->all(),
            'producing' => $lines->whereNotNull('job_order_id')->values()->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> what came back from the floor lately */
    public function restockedLane(Production $production): array
    {
        return DB::table('job_orders as jo')
            ->join('job_order_items as joi', 'joi.job_order_id', 'jo.id')
            ->join('artefacts as a', 'a.id', 'joi.artefact_id')
            ->leftJoin('org_stocks as os', 'os.id', 'a.org_stock_id')
            ->leftJoin('artefact_departments as ad', 'ad.id', 'a.artefact_department_id')
            ->leftJoin('employees as e', 'e.id', 'jo.employee_id')
            ->where('jo.production_id', $production->id)
            ->where('jo.state', JobOrderStateEnum::RECEIVED)
            ->where('jo.received_at', '>=', now()->subDays(14))
            ->select([
                'joi.id',
                'joi.quantity',
                'a.id as artefact_id',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.quantity_available as stock_available',
                'ad.name as family',
                'jo.reference as job_order_reference',
                'jo.slug as job_order_slug',
                'jo.received_at',
                'e.contact_name as maker',
            ])
            ->orderByDesc('jo.received_at')
            ->limit(60)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @return array<string, mixed> */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request);
    }

    /** @return array<string, mixed> */
    public function handle(ActionRequest $request): array
    {
        $cover    = GetProductionStockCoverBuckets::run($this->production);
        $selected = array_values(array_intersect(
            array_keys(GetProductionStockCoverBuckets::BUCKETS),
            explode(',', (string) $request->input('buckets')) ?: []
        )) ?: self::DEFAULT_BUCKETS;

        $moving = $this->movingLanes($this->organisation, $this->production);

        return [
            'leadTime'        => $cover['lead_time'],
            'coverTotal'      => $cover['total'],
            'coverBuckets'    => $cover['buckets'],
            'selectedBuckets' => $selected,
            'toDoLimit'       => self::TODO_LIMIT,
            'lanes'           => [
                'to_do'     => $this->toDoLane($this->production, $selected),
                'queued'    => $moving['queued'],
                'producing' => $moving['producing'],
                'restocked' => $this->restockedLane($this->production),
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    public function htmlResponse(array $payload, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ToRestock',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('To restock'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-inventory'],
                        'title' => __('To restock'),
                    ],
                    'title' => __('To restock'),
                ],
                ...$payload,
                'toProduceRoute' => [
                    'name'       => 'grp.org.productions.show.to_produce.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProduction::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.to_restock.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('To restock'),
                        'icon'  => 'fal fa-inventory',
                    ],
                ],
            ]
        );
    }
}
