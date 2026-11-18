<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closing, correcting or voiding an entry locks every job order it can write to, in id order, before the entry, as
 * Start and Receive into stock lock theirs, then the payroll lock takes the worker. Nothing waits on the same rows the
 * other way round, so two of them never deadlock and a receive never reads a job half changed.
 */
trait WithSessionJobOrderLocks
{
    /**
     * The job orders are read before the entry is locked. When a combine or a move commits in between, the entry then
     * touches a job order not locked yet: the transaction is let go and everything is read and locked again, instead of
     * locking that job order after the entry.
     *
     * @param  Closure(ManufactureTaskSession): Collection<int, int>  $jobOrderIdsOf
     * @param  Closure(ManufactureTaskSession): mixed  $then
     */
    protected function withSessionLocked(int $sessionId, Closure $jobOrderIdsOf, Closure $then): mixed
    {
        $attempts = DB::transactionLevel() > 0 ? 1 : 3;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $result = DB::transaction(function () use ($sessionId, $jobOrderIdsOf, $then) {
                $jobOrderIds = $jobOrderIdsOf(ManufactureTaskSession::findOrFail($sessionId))->filter()->unique()->values();
                if ($jobOrderIds->isNotEmpty()) {
                    JobOrder::whereIn('id', $jobOrderIds)->orderBy('id')->lockForUpdate()->get();
                }
                $session = ManufactureTaskSession::lockForUpdate()->findOrFail($sessionId);
                if ($jobOrderIdsOf($session)->filter()->diff($jobOrderIds)->isNotEmpty()) {
                    return null;
                }

                return [$then($session)];
            });
            if ($result !== null) {
                return $result[0];
            }
        }

        throw ValidationException::withMessages(['state' => __('This entry is being changed by someone else, try again')]);
    }

    /**
     * @param  array<int, int|null>  $alsoTaskIds  steps the entry is moved to
     * @return Collection<int, int>
     */
    protected static function jobOrderIdsOfEntry(ManufactureTaskSession $session, array $alsoTaskIds = []): Collection
    {
        $jobOrderIds = JobOrderItemTask::whereIn('id', array_filter([$session->job_order_item_task_id, ...$alsoTaskIds]))->pluck('job_order_id');
        if (!$session->mix_pool_key) {
            return $jobOrderIds;
        }

        return $jobOrderIds
            ->merge(JobOrderItemTask::whereIn('id', $session->shares()->select('job_order_item_task_id'))->pluck('job_order_id'))
            ->merge(ShareMixPool::make()->jobOrderIdsNeeding($session->production_id, $session->mix_pool_key));
    }
}
