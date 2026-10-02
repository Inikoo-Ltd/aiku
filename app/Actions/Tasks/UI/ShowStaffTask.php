<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks\UI;

use App\Actions\OrgAction;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Http\Resources\Chat\StaffConversationResource;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffTask extends OrgAction
{
    use WithStaffTasksScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isVisibleTo($request->user());
    }

    public function handle(StaffTask $staffTask): StaffTask
    {
        return $staffTask->load(['requester.image', 'assignee.image', 'collaborators.image', 'conversation.participants.image', 'conversation.context', 'model', 'media']);
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTask
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($staffTask);
    }

    public function inOrganisation(Organisation $organisation, StaffTask $staffTask, ActionRequest $request): StaffTask
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($staffTask);
    }

    public function inShop(Organisation $organisation, Shop $shop, StaffTask $staffTask, ActionRequest $request): StaffTask
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($staffTask);
    }

    public function linkedRecordUrl(StaffTask $staffTask): ?string
    {
        $record = $staffTask->model;

        return match (true) {
            !$record                                  => null,
            $staffTask->model_type === 'Customer'     => $record->shop ? route('grp.org.shops.show.crm.customers.show', [$record->organisation->slug, $record->shop->slug, $record->slug]) : null,
            $staffTask->model_type === 'Product'      => $record->shop ? route('grp.org.shops.show.catalogue.products.all_products.show', [$record->organisation->slug, $record->shop->slug, $record->slug]) : null,
            $staffTask->model_type === 'Order'        => route('grp.org.shops.show.ordering.orders.show', [$record->organisation->slug, $record->shop->slug, $record->slug]),
            $staffTask->model_type === 'DeliveryNote' => route('grp.org.warehouses.show.dispatching.delivery_notes.show', [$record->organisation->slug, $record->warehouse->slug, $record->slug]),
            default                                   => null,
        };
    }

    /**
     * @return array<int, array{at: mixed, icon: string, text: string, by: string|null}>
     */
    public function timeline(StaffTask $staffTask): array
    {
        $audits      = $staffTask->audits()->with('user')->orderBy('id')->get();
        $assigneeIds = $audits->flatMap(fn ($audit) => [$audit->old_values['assignee_id'] ?? null, $audit->new_values['assignee_id'] ?? null])->filter()->unique();
        $names       = User::whereIn('id', $assigneeIds)->get()->mapWithKeys(fn (User $user) => [$user->id => $user->chatName()]);
        $statuses    = StaffTaskStatusEnum::labels();
        $statusIcons = StaffTaskStatusEnum::stateIcon();
        $priorities  = ChatPriorityEnum::labels();

        $events = [[
            'at'   => $staffTask->created_at,
            'icon' => 'fal fa-plus-circle',
            'text' => __('Task raised'),
            'by'   => $staffTask->requester?->chatName(),
        ]];

        foreach ($audits->where('event', 'updated') as $audit) {
            foreach ($audit->new_values as $field => $value) {
                $old  = $audit->old_values[$field] ?? null;
                $text = match ($field) {
                    'status'      => __('Status: :from → :to', ['from' => $statuses[$old] ?? '—', 'to' => $statuses[$value] ?? $value]),
                    'assignee_id' => $value ? __('Assigned to :name', ['name' => $names[$value] ?? '?']) : __('Unassigned'),
                    'department'  => $value ? __('Sent to :department', ['department' => StaffTask::departmentLabel($value)]) : null,
                    'priority'    => __('Priority: :from → :to', ['from' => $priorities[$old] ?? '—', 'to' => $priorities[$value] ?? $value]),
                    'due_at'      => $value ? __('Due date set to :date', ['date' => Carbon::parse($value)->toFormattedDateString()]) : __('Due date removed'),
                    'subject'     => __('Subject edited'),
                    default       => null,
                };

                if ($text) {
                    $events[] = [
                        'at'   => $audit->created_at,
                        'icon' => match ($field) {
                            'status'      => $statusIcons[$value]['icon'] ?? 'fal fa-exchange',
                            'assignee_id' => 'fal fa-user-check',
                            'due_at'      => 'fal fa-calendar',
                            'priority'    => 'fal fa-flag',
                            default       => 'fal fa-pencil',
                        },
                        'text' => $text,
                        'by'   => $audit->user?->chatName(),
                    ];
                }
            }
        }

        return array_reverse($events);
    }

    public function htmlResponse(StaffTask $staffTask, ActionRequest $request): Response
    {
        $viewer       = $request->user();
        $conversation = $staffTask->conversation;

        $this->markNotificationsRead($staffTask, $viewer);

        return Inertia::render('Tasks/StaffTask', [
            'breadcrumbs'  => $this->getBreadcrumbs($staffTask),
            'title'        => $staffTask->reference.' · '.$staffTask->subject,
            'pageHead'     => [
                'title' => $staffTask->reference,
                'model' => __('Task'),
                'icon'  => ['icon' => ['fal', 'fa-tasks'], 'title' => __('Task')],
            ],
            'task'         => StaffTaskResource::make($staffTask)->resolve(),
            'linked_url'   => $this->linkedRecordUrl($staffTask),
            'conversation' => $conversation?->canBeAccessedBy($viewer) ? StaffConversationResource::make($conversation)->resolve() : null,
            'can_edit'     => $staffTask->isWorkedOnBy($viewer),
            'due_access'   => $staffTask->dueAccessFor($viewer),
            'can_remove_collaborators' => $staffTask->canRemoveCollaboratorsBy($viewer),
            'timeline'     => $this->timeline($staffTask),
            'options'      => $this->staffTaskEditOptions(),
            'listRoute'    => $this->tasksRoute('list_all'),
        ]);
    }

    public function markNotificationsRead(StaffTask $staffTask, User $viewer): void
    {
        $marked = $viewer->unreadNotifications()
            ->whereRaw("(data::jsonb)->>'type' = 'staff_task'")
            ->whereRaw("(data::jsonb)->>'route' = ?", [route('grp.tasks.show', $staffTask->reference)])
            ->update(['read_at' => now()]);

        if ($marked) {
            SendStaffTaskBadgeUpdateToUsers::run([$viewer->id]);
        }
    }

    public function getBreadcrumbs(StaffTask $staffTask): array
    {
        return array_merge(
            $this->tasksBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->tasksRoute('list_all'),
                        'label' => __('All'),
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->tasksRoute('show', [$staffTask->reference]),
                        'label' => $staffTask->reference,
                    ],
                ],
            ]
        );
    }
}
