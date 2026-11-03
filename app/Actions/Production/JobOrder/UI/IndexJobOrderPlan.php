<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artisan\GetArtisanDepartments;
use App\Actions\Production\Production\UI\ShowOperationsDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Http\Resources\Production\JobOrderPlanItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class IndexJobOrderPlan extends OrgAction
{
    private string $date;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.edit",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $request->query('date'));
    }

    public function handle(Production $production, ?string $date = null): LengthAwarePaginator
    {
        $this->date = self::parseDate($date);

        $paginator = QueryBuilder::for(JobOrderItem::class)
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->leftJoin('artefacts', 'artefacts.id', 'job_order_items.artefact_id')
            ->where('job_orders.production_id', $production->id)
            ->whereNull('job_orders.deleted_at')
            ->where(fn (Builder $query) => self::onThePlan($query, $this->date))
            ->select([
                'job_order_items.id',
                'job_order_items.quantity',
                'job_orders.id as job_order_id',
                'job_orders.reference',
                'job_orders.slug as job_order_slug',
                'job_orders.state as job_order_state',
                'job_orders.planned_for',
                'job_orders.plan_position',
                'artefacts.code as artefact_code',
                'artefacts.name as artefact_name',
                'artefacts.artefact_department_id',
            ])
            ->orderBy('job_orders.planned_for')
            ->orderByRaw('job_orders.plan_position nulls last')
            ->orderBy('job_order_items.id')
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();

        $paginator->getCollection()->load([
            'tasks.manufactureTask.artisans',
            'tasks.employee',
            'tasks.sessions' => fn ($query) => $query->where('state', ManufactureTaskSessionStateEnum::OPEN)->with('user'),
            'employee',
            'jobOrder.employee',
        ]);

        $paginator->getCollection()->each(
            fn (JobOrderItem $item) => $item->tasks->each(fn (JobOrderItemTask $task) => $task->setRelation('jobOrderItem', $item))
        );

        return $paginator;
    }

    /**
     * What was planned for the day and, from today on, whatever an earlier day left unfinished:
     * it stays in front of the manager until it is confirmed onto a day or taken off the plan.
     * Needs job_orders and job_order_items joined.
     */
    public static function onThePlan(Builder|BaseBuilder $query, string $date): void
    {
        $today = now()->toDateString();

        $query->whereDate('job_orders.planned_for', $date);

        if ($date >= $today) {
            $query->orWhere(function (Builder|BaseBuilder $query) use ($today) {
                $query->whereDate('job_orders.planned_for', '<', $today)
                    ->whereIn('job_orders.state', JobOrderStateEnum::open())
                    ->whereExists(fn ($query) => $query->selectRaw('1')
                        ->from('job_order_item_tasks as unfinished')
                        ->whereColumn('unfinished.job_order_item_id', 'job_order_items.id')
                        ->where('unfinished.state', '!=', JobOrderItemTaskStateEnum::DONE->value));
            });
        }
    }

    public static function parseDate(?string $date): string
    {
        try {
            return $date ? Carbon::createFromFormat('Y-m-d', $date)->toDateString() : now()->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    /**
     * @return array{jobs: int, carried_over: int, steps: int, steps_done: int, steps_unassigned: int}
     */
    public function getSummary(Production $production): array
    {
        $summary = JobOrderItemTask::query()
            ->join('job_order_items', 'job_order_items.id', 'job_order_item_tasks.job_order_item_id')
            ->join('job_orders', 'job_orders.id', 'job_order_item_tasks.job_order_id')
            ->where('job_orders.production_id', $production->id)
            ->whereNull('job_orders.deleted_at')
            ->whereNull('job_order_items.deleted_at')
            ->where(fn (Builder $query) => self::onThePlan($query, $this->date))
            ->selectRaw('count(distinct job_orders.id) as jobs')
            ->selectRaw('count(distinct job_orders.id) filter (where job_orders.planned_for < ?) as carried_over', [now()->toDateString()])
            ->selectRaw('count(*) as steps')
            ->selectRaw("count(*) filter (where job_order_item_tasks.state = 'done') as steps_done")
            ->selectRaw("count(*) filter (where job_order_item_tasks.state != 'done' and ".JobOrderItemTask::ASSIGNEE_SQL.' is null) as steps_unassigned')
            ->toBase()
            ->first();

        return array_map('intval', (array) $summary);
    }

    /**
     * @return array<int, array{id: int, name: string, department_ids: array<int, int>}>
     */
    public function getOperators(Production $production): array
    {
        $departmentIds = GetArtisanDepartments::make()->departmentIdsByArtisan($production);

        return Employee::where('organisation_id', $production->organisation_id)
            ->where('state', EmployeeStateEnum::WORKING)
            ->whereNotIn('id', Arr::get($production->data, 'hidden_artisan_ids', []))
            ->orderBy('contact_name')
            ->get(['id', 'contact_name'])
            ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name, 'department_ids' => $departmentIds->get($employee->id, [])])
            ->all();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState([
                    'title'       => __('Nothing planned for this day'),
                    'description' => __('Plan jobs for a day from the To produce board'),
                ])
                ->column(key: 'plan_position', label: __('Order'), canBeHidden: false)
                ->column(key: 'reference', label: __('Job'), canBeHidden: false)
                ->column(key: 'artefact_code', label: __('Product'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Qty'), canBeHidden: false, align: 'right')
                ->column(key: 'steps', label: __('Steps and operators'), canBeHidden: false)
                ->column(key: 'planned_for', label: __('Planned'), canBeHidden: false);
        };
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/JobOrderPlan',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Day plan'),
                'pageHead'    => [
                    'title' => __('Day plan'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-calendar-day'],
                        'title' => __('Day plan'),
                    ],
                ],
                'date'      => $this->date,
                'today'     => now()->toDateString(),
                'can_edit'  => $this->canEdit,
                'summary'   => $this->getSummary($this->production),
                'operators' => $this->canEdit ? $this->getOperators($this->production) : [],
                'data'      => JobOrderPlanItemsResource::collection($items),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowOperationsDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.operations.day_plan',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Day plan'),
                    ],
                ],
            ]
        );
    }
}
