<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\Helpers\Ticket\SyncTicketCollaborators;
use App\Actions\Tasks\SyncStaffTaskCollaborators;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\HumanResources\Leave;
use App\Models\Tasks\StaffTask;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class AddLeaveCoverCollaborators
{
    use AsAction;

    public string $commandSignature = 'hr:add-leave-cover-collaborators';

    /**
     * While a leave runs, whoever covers it joins the absent employee's open tasks and tickets as a
     * collaborator. The assignee is recorded as the one who added them, so it reads as their handover.
     * Covers are never removed: they may have worked on the item, and anyone can take them off by hand.
     */
    public function handle(Leave $leave): void
    {
        if ($leave->status !== LeaveStatusEnum::APPROVED || !$leave->coverEmployee || $leave->start_date->isFuture() || $leave->end_date->lt(today())) {
            return;
        }

        $absentUserIds = GetUserLeaveCovers::make()->absentUserIds($leave);
        $coverUserIds  = $leave->coverEmployee->users()->wherePivot('status', true)->pluck('users.id');
        if ($absentUserIds->isEmpty() || $coverUserIds->isEmpty()) {
            return;
        }

        $withoutCover = fn (Builder $query) => $query
            ->whereIn('assignee_id', $absentUserIds)
            ->whereDoesntHave('collaborators', fn (Builder $collaborators) => $collaborators->whereIn('users.id', $coverUserIds))
            ->with('assignee');

        StaffTask::query()->open()->tap($withoutCover)->eachById(
            fn (StaffTask $task) => SyncStaffTaskCollaborators::run($task, $task->collaborators()->pluck('users.id')->merge($coverUserIds)->all(), $task->assignee)
        );

        Ticket::query()->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED])->tap($withoutCover)->eachById(
            fn (Ticket $ticket) => SyncTicketCollaborators::make()->action($ticket, $ticket->collaborators()->pluck('users.id')->merge($coverUserIds)->all(), $ticket->assignee)
        );
    }

    /**
     * Picks up leaves on the day they start and work assigned to the absent employee after the cover was set.
     */
    public function asCommand(Command $command): int
    {
        Leave::where('status', LeaveStatusEnum::APPROVED)
            ->whereNotNull('cover_employee_id')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with(['employee.users:id', 'coverEmployee'])
            ->eachById(fn (Leave $leave) => $this->handle($leave));

        return 0;
    }
}
