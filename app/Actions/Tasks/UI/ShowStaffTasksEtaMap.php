<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffTasksEtaMap extends OrgAction
{
    use WithStaffTasksScope;

    public const int DAYS_AHEAD = 14;

    public const array URGENCY_WEIGHTS = ['overdue' => 4, 'critical' => 3, 'soon' => 2, 'planned' => 1, 'none' => 0];

    /**
     * @return array{columns: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, summary: array<string, int>, proposals: array<int, array<string, mixed>>}
     */
    public function handle(Group|Organisation $parent, User $viewer): array
    {
        $today = Carbon::today();

        $tasks = StaffTask::query()->within($parent)->visibleTo($viewer)->inSection(null)->open()
            ->with(['assignee.image', 'requester', 'collaborators'])
            ->orderByRaw('due_at asc nulls last, id asc')
            ->get();

        $chips = $tasks->map(fn (StaffTask $task) => $this->chip($task, $today, $viewer));

        $rows = $chips
            ->groupBy('row_key')
            ->map(fn (Collection $rowChips) => [
                'key'      => $rowChips->first()['row_key'],
                'label'    => $rowChips->first()['row_label'],
                'avatar'   => $rowChips->first()['row_avatar'],
                'kind'     => $rowChips->first()['row_kind'],
                'is_me'    => $rowChips->first()['row_key'] === 'user:'.$viewer->id,
                'count'    => $rowChips->count(),
                'pressure' => $rowChips->sum(fn (array $chip) => self::URGENCY_WEIGHTS[$chip['urgency']]),
                'cells'    => $rowChips->groupBy('column')->map(fn (Collection $cellChips) => $cellChips->map(fn (array $chip) => collect($chip)->except(['row_key', 'row_label', 'row_avatar', 'row_kind', 'column'])->all())->values()->all())->all(),
            ])
            ->sortBy([['is_me', 'desc'], ['pressure', 'desc'], ['label', 'asc']])
            ->values()
            ->all();

        return [
            'columns'   => $this->columns($today),
            'rows'      => $rows,
            'summary'   => collect(self::URGENCY_WEIGHTS)->map(fn ($weight, string $urgency) => $chips->where('urgency', $urgency)->count())->all(),
            'proposals' => $tasks
                ->filter(fn (StaffTask $task) => isset($task->data['eta_proposal']) && $task->requester_id === $viewer->id)
                ->map(fn (StaffTask $task) => [
                    'id'        => $task->id,
                    'reference' => $task->reference,
                    'subject'   => $task->subject,
                    'due_at'    => $task->due_at?->toDateString(),
                    'proposal'  => $task->data['eta_proposal'],
                ])
                ->values()
                ->all(),
        ];
    }

    public static function urgencyOf(StaffTask $task, Carbon $today): string
    {
        if (!$task->due_at) {
            return 'none';
        }

        $daysLeft = (int) $today->diffInDays($task->due_at->copy()->startOfDay(), false);
        $isPressing = in_array($task->priority, [ChatPriorityEnum::HIGH, ChatPriorityEnum::URGENT], true);

        return match (true) {
            $daysLeft < 0                                         => 'overdue',
            $daysLeft <= 1                                        => 'critical',
            $daysLeft <= 3 || ($isPressing && $daysLeft <= 7)     => 'soon',
            default                                               => 'planned',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function chip(StaffTask $task, Carbon $today, User $viewer): array
    {
        [$rowKey, $rowLabel, $rowAvatar, $rowKind] = match (true) {
            (bool) $task->assignee => ['user:'.$task->assignee_id, $task->assignee->chatName(), $task->assignee->image_id ? $task->assignee->imageSources(0, 48) : null, 'person'],
            (bool) $task->department => ['department:'.$task->department, StaffTask::departmentLabel($task->department), null, 'department'],
            default => ['unassigned', __('Unassigned'), null, 'unassigned'],
        };

        return [
            'id'               => $task->id,
            'reference'        => $task->reference,
            'subject'          => $task->subject,
            'priority'         => $task->priority->value,
            'priority_icon'    => ChatPriorityEnum::stateIcon()[$task->priority->value] ?? null,
            'due_at'           => $task->due_at?->toDateString(),
            'urgency'          => self::urgencyOf($task, $today),
            'has_eta_proposal' => isset($task->data['eta_proposal']),
            'is_mine'          => $task->assignee_id === $viewer->id || $task->collaborators->contains('id', $viewer->id),
            'row_key'          => $rowKey,
            'row_label'        => $rowLabel,
            'row_avatar'       => $rowAvatar,
            'row_kind'         => $rowKind,
            'column'           => $this->columnOf($task, $today),
        ];
    }

    private function columnOf(StaffTask $task, Carbon $today): string
    {
        if (!$task->due_at) {
            return 'none';
        }

        $dueDay = $task->due_at->copy()->startOfDay();

        return match (true) {
            $dueDay->lt($today)                                       => 'overdue',
            $dueDay->gt($today->copy()->addDays(self::DAYS_AHEAD - 1)) => 'later',
            default                                                   => $dueDay->toDateString(),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, sublabel: string|null, is_today: bool, is_weekend: bool}>
     */
    private function columns(Carbon $today): array
    {
        $days = collect(range(0, self::DAYS_AHEAD - 1))->map(function (int $offset) use ($today) {
            $day = $today->copy()->addDays($offset);

            return [
                'key'        => $day->toDateString(),
                'label'      => $offset === 0 ? __('Today') : $day->isoFormat('ddd'),
                'sublabel'   => $day->isoFormat('D MMM'),
                'is_today'   => $offset === 0,
                'is_weekend' => $day->isWeekend(),
            ];
        });

        return [
            ['key' => 'overdue', 'label' => __('Overdue'), 'sublabel' => null, 'is_today' => false, 'is_weekend' => false],
            ...$days->all(),
            ['key' => 'later', 'label' => __('Later'), 'sublabel' => null, 'is_today' => false, 'is_weekend' => false],
            ['key' => 'none', 'label' => __('No ETA'), 'sublabel' => null, 'is_today' => false, 'is_weekend' => false],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($this->tasksParent(), $request->user());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($this->tasksParent(), $request->user());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($this->tasksParent(), $request->user());
    }

    public function htmlResponse(array $map, ActionRequest $request): Response
    {
        $title = __('ETA map');

        return Inertia::render('Tasks/StaffTasksEtaMap', [
            'breadcrumbs' => array_merge(
                $this->tasksBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => $this->tasksRoute('eta_map'), 'label' => __('ETA map')]]]
            ),
            'title'       => $title,
            'pageHead'    => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-calendar-alt'], 'title' => $title]],
            'map'         => $map,
            'showRoute'   => $this->tasksRoute('show'),
        ]);
    }
}
