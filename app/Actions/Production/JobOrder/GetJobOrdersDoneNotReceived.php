<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\Production;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Job orders with a line whose every step is done, that still has pieces to put into stock, and whose
 * last work is older than the production's done_not_received_days (3 by default): goods sitting on the
 * floor that pickers cannot find in Aiku (HELP-4941). Fill lines, trial runs and jobs on hold are left out.
 */
class GetJobOrdersDoneNotReceived
{
    use AsObject;

    /**
     * @return Collection<int, array{done_at: Carbon, days: int, artefacts: array<int, int>}> by job order id, oldest first
     */
    public function handle(Production $production): Collection
    {
        $lines = JobOrderItem::query()
            ->whereHas('jobOrder', fn ($query) => $query->where('production_id', $production->id)
                ->where('state', JobOrderStateEnum::CONFIRMED)
                ->whereRaw("job_orders.data->'hold' is null")
                ->whereRaw("coalesce((job_orders.data->>'trial_run')::boolean, false) = false"))
            ->whereRaw("not jsonb_exists(job_order_items.data, 'fill')")
            ->whereHas('tasks')
            ->whereDoesntHave('tasks', fn ($query) => $query->where('state', '!=', JobOrderItemTaskStateEnum::DONE))
            ->with(['jobOrder', 'artefact.manufactureTasks', 'tasks.recipeStep', 'tasks.manufactureTask'])
            ->get();

        if ($lines->isEmpty()) {
            return collect();
        }

        $lastWork = DB::table('manufacture_task_sessions')
            ->join('job_order_item_tasks', 'job_order_item_tasks.id', 'manufacture_task_sessions.job_order_item_task_id')
            ->whereIn('job_order_item_tasks.job_order_item_id', $lines->pluck('id'))
            ->where('manufacture_task_sessions.state', 'closed')
            ->groupBy('job_order_item_tasks.job_order_item_id')
            ->selectRaw('job_order_item_tasks.job_order_item_id, max(manufacture_task_sessions.ended_at) as last_work')
            ->pluck('last_work', 'job_order_item_id');

        $cutOff = now()->subDays($production->doneNotReceivedDays());

        return $lines
            ->map(function (JobOrderItem $line) use ($lastWork) {
                $doneAt = $lastWork->get($line->id) ?? $line->tasks->max('updated_at');

                return ['job_order_id' => $line->job_order_id, 'done_at' => Carbon::parse($doneAt), 'line' => $line];
            })
            ->filter(fn (array $row) => $row['done_at']->lte($cutOff))
            ->filter(fn (array $row) => $row['line']->receivableQuantity() > 0)
            ->groupBy('job_order_id')
            ->map(fn (Collection $rows) => [
                'done_at'   => $rows->min('done_at'),
                'days'      => (int) $rows->min('done_at')->diffInDays(now()),
                'artefacts' => $rows->groupBy(fn (array $row) => $row['line']->artefact_id)
                    ->map(fn (Collection $lines) => (int) $lines->min('done_at')->diffInDays(now()))
                    ->all(),
            ])
            ->sortBy(fn (array $row) => $row['done_at']);
    }
}
