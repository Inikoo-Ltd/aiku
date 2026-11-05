<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Http\Resources\Production\ArtefactBatchesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class IndexArtefactBatches
{
    use AsObject;

    public function handle(Artefact $artefact, ?string $prefix = null, bool $open = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('job_orders.reference', $value)
                    ->orWhereStartWith('employees.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for($open ? $this->toDo($artefact)->reorder() : $this->history($artefact));

        if (!$open) {
            foreach ($this->getElementGroups($artefact) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix,
                    default: 'produced'
                );
            }
            $queryBuilder->withBetweenDates(['work.started_at']);
        }

        $batches = $queryBuilder
            ->defaultSort($open ? 'date' : '-started_at')
            ->allowedSorts(['reference', 'artisan', 'date', 'started_at', 'quantity', 'quantity_received'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();

        if (!$open) {
            $this->attachSteps($artefact, $batches->getCollection());
        }

        return $batches;
    }

    public function jobsDone(Artefact $artefact, string $prefix): AnonymousResourceCollection
    {
        return ArtefactBatchesResource::collection($this->handle($artefact, $prefix))
            ->additional(['totals' => $this->totals($artefact)]);
    }

    /**
     * One row per batch: a job order line, or each artisan's part of a split line. Its date is when the
     * job order was received into stock, or when it was raised while it is still being made. Started and
     * last worked come from the floor sessions on its steps, its own or its share of a combined batch.
     */
    public function query(Artefact $artefact): Builder
    {
        return JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->join('productions', 'productions.id', 'job_orders.production_id')
            ->join('organisations', 'organisations.id', 'job_order_items.organisation_id')
            ->leftJoin('employees', 'employees.id', DB::raw('coalesce(job_order_items.employee_id, job_orders.employee_id)'))
            ->leftJoinSub($this->work($artefact), 'work', 'work.job_order_item_id', 'job_order_items.id')
            ->leftJoin('job_orders as carried_from', 'carried_from.id', DB::raw("(job_orders.data->>'carried_from')::bigint"))
            ->where('job_order_items.artefact_id', $artefact->id)
            ->select(
                'job_order_items.id',
                'job_order_items.quantity',
                'job_order_items.quantity_received',
                'job_orders.slug as job_order_slug',
                'productions.slug as production_slug',
                'organisations.slug as organisation_slug',
                'job_orders.reference',
                'job_orders.state as job_order_state',
                'job_orders.deleted_at as job_order_deleted_at',
                'employees.contact_name as artisan',
                'work.started_at',
                'work.last_worked_at',
                'carried_from.reference as carried_from_reference'
            )
            ->selectRaw("(select string_agg(c.reference, ', ' order by c.reference) from job_orders c where c.deleted_at is null and (c.data->>'carried_from')::bigint = job_orders.id) as carried_to_reference")
            ->selectRaw("coalesce(nullif(job_order_items.data->>'batch_code', ''), job_orders.reference || '-' || ?) as batch_code", [$artefact->code])
            ->selectRaw('coalesce(job_orders.received_at, job_orders.date, job_orders.created_at) as date')
            ->selectRaw('(select t.quantity_made from job_order_item_tasks t where t.job_order_item_id = job_order_items.id order by t.position desc, t.id desc limit 1) as quantity_made')
            ->selectRaw('(select t.state from job_order_item_tasks t where t.job_order_item_id = job_order_items.id order by t.position desc, t.id desc limit 1) as last_task_state')
            ->selectRaw('(select amt.units_per_artefact from job_order_item_tasks t join artefacts_manufacture_tasks amt on amt.id = t.artefact_manufacture_task_id where t.job_order_item_id = job_order_items.id order by t.position desc, t.id desc limit 1) as last_task_units_per_artefact')
            ->selectRaw('(select coalesce(sum(t.quantity_rejected), 0) from job_order_item_tasks t where t.job_order_item_id = job_order_items.id) as quantity_rejected')
            ->selectRaw("(select count(*) from job_order_item_tasks t where t.job_order_item_id = job_order_items.id and t.state = 'done') as number_tasks_done")
            ->selectRaw('(select count(*) from job_order_item_tasks t where t.job_order_item_id = job_order_items.id) as number_tasks');
    }

    /**
     * Every batch work has started on, newest first. A cancelled job order is a deleted one: its lines are
     * deleted with it and were never worked, so they only come back with the Cancelled filter.
     */
    public function history(Artefact $artefact): Builder
    {
        return $this->query($artefact)
            ->withTrashed()
            ->where(function (Builder $query) {
                $query->whereNotNull('job_orders.deleted_at')
                    ->orWhere(fn (Builder $query) => $query->whereNull('job_order_items.deleted_at')->whereNotNull('work.started_at'));
            });
    }

    public function produced(Artefact $artefact, int $limit = 10): Builder
    {
        return $this->query($artefact)
            ->where('job_order_items.quantity_received', '>', 0)
            ->orderByDesc('date')
            ->limit($limit);
    }

    public function toDo(Artefact $artefact): Builder
    {
        return $this->query($artefact)
            ->whereIn('job_orders.state', JobOrderStateEnum::open())
            ->whereColumn('job_order_items.quantity_received', '<', 'job_order_items.quantity')
            ->orderBy('date');
    }

    /** @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}> */
    public function getElementGroups(Artefact $artefact): array
    {
        $cancelled = $this->history($artefact)->whereNotNull('job_orders.deleted_at')->count();

        return [
            'cancelled' => [
                'label'    => __('Cancelled'),
                'elements' => [
                    'produced'  => [__('Produced'), $this->history($artefact)->count() - $cancelled],
                    'cancelled' => [__('Cancelled'), $cancelled],
                ],
                'engine'   => function ($query, $elements) {
                    if (in_array('produced', $elements) && !in_array('cancelled', $elements)) {
                        $query->whereNull('job_orders.deleted_at');
                    } elseif (in_array('cancelled', $elements) && !in_array('produced', $elements)) {
                        $query->whereNotNull('job_orders.deleted_at');
                    }
                },
            ],
        ];
    }

    /**
     * What the last step made, in artefacts, by the day it was recorded: a pack step's quantity is
     * turned into the artefacts its packs hold.
     *
     * @return array{last_30_days: int, last_12_months: int, all_time: int, last_produced_at: string|null}
     */
    public function totals(Artefact $artefact): array
    {
        $lastSteps = DB::table('job_order_item_tasks as t')
            ->join('job_order_items as i', 'i.id', 't.job_order_item_id')
            ->join('job_orders as jo', 'jo.id', 'i.job_order_id')
            ->where('i.artefact_id', $artefact->id)
            ->whereNull('jo.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereRaw('t.id = (select l.id from job_order_item_tasks l where l.job_order_item_id = i.id order by l.position desc, l.id desc limit 1)')
            ->pluck('t.artefact_manufacture_task_id', 't.id');

        $unitsPerArtefact = ArtefactManufactureTask::where('artefact_id', $artefact->id)->pluck('units_per_artefact', 'id');

        $made = $this->closedWork($lastSteps->keys())->map(fn (object $work) => [
            'ended_at' => $work->ended_at,
            'made'     => (float) $work->quantity_made / (ArtefactManufactureTask::exactUnits($unitsPerArtefact[$lastSteps[$work->task_id]] ?? 1) ?: 1),
        ]);

        $since = fn (string $date) => (int) floor(round($made->where('ended_at', '>=', $date)->sum('made'), 3));

        return [
            'last_30_days'     => $since(now()->subDays(30)->toDateTimeString()),
            'last_12_months'   => $since(now()->subMonths(12)->toDateTimeString()),
            'all_time'         => (int) floor(round($made->sum('made'), 3)),
            'last_produced_at' => $made->where('made', '>', 0)->max('ended_at'),
        ];
    }

    /**
     * @param Collection<int, int> $taskIds
     * @return Collection<int, object{task_id: int, ended_at: string, quantity_made: string}>
     */
    private function closedWork(Collection $taskIds): Collection
    {
        $own = DB::table('manufacture_task_sessions as s')
            ->whereIn('s.job_order_item_task_id', $taskIds)
            ->where('s.is_combined', false)
            ->where('s.state', ManufactureTaskSessionStateEnum::CLOSED->value)
            ->select('s.job_order_item_task_id as task_id', 's.ended_at', 's.quantity_made');

        $shared = DB::table('manufacture_task_session_shares as sh')
            ->join('manufacture_task_sessions as s', 's.id', 'sh.manufacture_task_session_id')
            ->whereIn('sh.job_order_item_task_id', $taskIds)
            ->where('s.state', ManufactureTaskSessionStateEnum::CLOSED->value)
            ->select('sh.job_order_item_task_id as task_id', 's.ended_at', 'sh.quantity_made');

        return $own->unionAll($shared)->get();
    }

    private function work(Artefact $artefact): \Illuminate\Database\Query\Builder
    {
        $worked = [ManufactureTaskSessionStateEnum::OPEN->value, ManufactureTaskSessionStateEnum::CLOSED->value];

        $own = DB::table('manufacture_task_sessions as s')
            ->join('job_order_item_tasks as t', 't.id', 's.job_order_item_task_id')
            ->join('job_order_items as i', 'i.id', 't.job_order_item_id')
            ->where('i.artefact_id', $artefact->id)
            ->where('s.is_combined', false)
            ->whereIn('s.state', $worked)
            ->select('t.job_order_item_id', 's.started_at', 's.ended_at');

        $shared = DB::table('manufacture_task_session_shares as sh')
            ->join('manufacture_task_sessions as s', 's.id', 'sh.manufacture_task_session_id')
            ->join('job_order_item_tasks as t', 't.id', 'sh.job_order_item_task_id')
            ->join('job_order_items as i', 'i.id', 't.job_order_item_id')
            ->where('i.artefact_id', $artefact->id)
            ->whereIn('s.state', $worked)
            ->select('t.job_order_item_id', 's.started_at', 's.ended_at');

        return DB::query()
            ->fromSub($own->unionAll($shared), 'sessions')
            ->groupBy('job_order_item_id')
            ->select('job_order_item_id')
            ->selectRaw('min(started_at) as started_at, max(ended_at) as last_worked_at');
    }

    /**
     * Each step of the batch with when it was worked, what it made in its own unit and who worked it.
     *
     * @param Collection<int, JobOrderItem> $batches
     */
    private function attachSteps(Artefact $artefact, Collection $batches): void
    {
        $batches->load(['tasks.manufactureTask', 'tasks.recipeStep', 'tasks.sessions.employee', 'tasks.sessionShares.session.employee']);

        $unitsPerArtefact = ArtefactManufactureTask::where('artefact_id', $artefact->id)->pluck('units_per_artefact', 'id');

        foreach ($batches as $batch) {
            $batch->setAttribute('steps', $batch->tasks->map(function (JobOrderItemTask $task) use ($unitsPerArtefact) {
                $sessions = $task->sessions->where('is_combined', false)
                    ->concat($task->sessionShares->map(fn (ManufactureTaskSessionShare $share) => $share->session))
                    ->filter(fn (ManufactureTaskSession $session) => $session->state != ManufactureTaskSessionStateEnum::VOIDED);

                return [
                    'id'                => $task->id,
                    'name'              => $task->manufactureTask ? $task->stepName() : null,
                    'state'             => $task->state->value,
                    'unit'              => $task->manufactureTask?->is_recorded_in_kg ? 'kg' : null,
                    'pack_size'         => $task->manufactureTask?->is_recorded_in_kg ? null : ArtefactManufactureTask::packSize($unitsPerArtefact[$task->artefact_manufacture_task_id] ?? null),
                    'quantity_required' => (float) $task->quantity_required,
                    'quantity_made'     => (float) $task->quantity_made,
                    'quantity_rejected' => (float) $task->quantity_rejected,
                    'started_at'        => $sessions->min('started_at'),
                    'finished_at'       => $task->state == JobOrderItemTaskStateEnum::DONE ? $sessions->max('ended_at') : null,
                    'artisans'          => $sessions->map(fn (ManufactureTaskSession $session) => $session->employee?->contact_name)->filter()->unique()->values()->all(),
                ];
            })->values()->all());
        }
    }

    public function tableStructure(?Artefact $artefact = null, ?string $prefix = null, bool $open = false): Closure
    {
        return function (InertiaTable $table) use ($artefact, $prefix, $open) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if (!$open && $artefact) {
                foreach ($this->getElementGroups($artefact) as $key => $elementGroup) {
                    $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements'], default: 'produced');
                }
                $table->betweenDates(['started_at']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => $open ? __('No current job orders') : __('Nothing produced yet')]);

            if ($open) {
                $table
                    ->column(key: 'reference', label: __('Job order'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'artisan', label: __('Artisan'), canBeHidden: false, sortable: true)
                    ->column(key: 'status', label: __('Status'), canBeHidden: false)
                    ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                    ->column(key: 'tasks', label: __('Tasks done'), canBeHidden: false)
                    ->column(key: 'quantity', label: __('Batch'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'quantity_made', label: __('Made'), canBeHidden: false, align: 'right')
                    ->column(key: 'quantity_received', label: __('Received'), canBeHidden: false, sortable: true, align: 'right')
                    ->defaultSort('date');

                return;
            }

            $table
                ->column(key: 'started_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'finished_at', label: __('Finished'), canBeHidden: false)
                ->column(key: 'reference', label: __('Job order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'batch_code', label: __('Batch code'), canBeHidden: false)
                ->column(key: 'artisan', label: __('Artisan'), canBeHidden: false, sortable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Planned'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'quantity_made', label: __('Made'), canBeHidden: false, align: 'right')
                ->column(key: 'quantity_rejected', label: __('Rejected'), canBeHidden: false, align: 'right')
                ->column(key: 'quantity_received', label: __('Received'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-started_at');
        };
    }
}
