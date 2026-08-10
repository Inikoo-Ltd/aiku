<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Production;

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ManufacturePayrollExport implements FromArray, WithHeadings
{
    public function __construct(
        protected Production $production,
        protected Carbon $from,
        protected Carbon $to
    ) {
    }

    public function headings(): array
    {
        return [
            'Worker',
            'Task code',
            'Task name',
            'Sessions',
            'Hours worked',
            'Quantity made',
            'Quantity rejected',
            'Rate per unit',
            'Base pay',
            'Reward terms',
            'Reward amount per unit',
        ];
    }

    public function array(): array
    {
        return ManufactureTaskSession::query()
            ->join('users', 'users.id', 'manufacture_task_sessions.user_id')
            ->join('manufacture_tasks', 'manufacture_tasks.id', 'manufacture_task_sessions.manufacture_task_id')
            ->where('manufacture_task_sessions.production_id', $this->production->id)
            ->where('manufacture_task_sessions.state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereBetween('manufacture_task_sessions.ended_at', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->groupBy(
                'users.id',
                'users.username',
                'users.contact_name',
                'manufacture_tasks.id',
                'manufacture_tasks.code',
                'manufacture_tasks.name',
                'manufacture_task_sessions.task_work_cost',
                'manufacture_task_sessions.operative_reward_terms',
                'manufacture_task_sessions.operative_reward_amount'
            )
            ->select(
                'users.contact_name',
                'users.username',
                'manufacture_tasks.code as task_code',
                'manufacture_tasks.name as task_name',
                'manufacture_task_sessions.task_work_cost',
                'manufacture_task_sessions.operative_reward_terms',
                'manufacture_task_sessions.operative_reward_amount'
            )
            ->selectRaw('count(*) as number_sessions')
            ->selectRaw('sum(extract(epoch from (manufacture_task_sessions.ended_at - manufacture_task_sessions.started_at))) as seconds_worked')
            ->selectRaw('sum(manufacture_task_sessions.quantity_made) as quantity_made')
            ->selectRaw('sum(manufacture_task_sessions.quantity_rejected) as quantity_rejected')
            ->orderBy('users.username')
            ->orderBy('manufacture_tasks.code')
            ->get()
            ->map(fn ($row) => [
                $row->contact_name ?: $row->username,
                $row->task_code,
                $row->task_name,
                (int)$row->number_sessions,
                round($row->seconds_worked / 3600, 2),
                (float)$row->quantity_made,
                (float)$row->quantity_rejected,
                (float)($row->task_work_cost ?? 0),
                round($row->quantity_made * ($row->task_work_cost ?? 0), 2),
                $row->operative_reward_terms,
                (float)($row->operative_reward_amount ?? 0),
            ])->all();
    }
}
