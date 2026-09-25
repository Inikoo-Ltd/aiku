<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-14h-48m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\UI;

use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class IndexQaTickets extends IndexTickets
{
    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canCheckQa($request->user());
    }

    protected function getElementGroups(Group $group): array
    {
        return [
            'qa_checker' => $this->qaAssigneeElementGroup($group),
            ...Arr::except(parent::getElementGroups($group), 'mine'),
        ];
    }

    /**
     * @return array{label: string, optional: bool, elements: array<string, array{0: string, 1: int}>, engine: \Closure}
     */
    protected function qaAssigneeElementGroup(Group $group): array
    {
        $user = request()->user();
        $base = $this->elementGroupsBase($group);

        return [
            'label'    => __('QA assignee'),
            'optional' => true,
            'elements' => [
                'mine'     => [__('Mine'), (clone $base)->where('qa_user_id', $user->id)->count()],
                'anyone'   => [__('Anyone'), (clone $base)->whereNotNull('qa_status')->whereNull('qa_user_id')->count()],
                'everyone' => [__('Everyone'), (clone $base)->whereNotNull('qa_status')->count()],
            ],
            'engine'   => function ($query, $elements) use ($user) {
                $query->where(function ($query) use ($elements, $user) {
                    if (in_array('everyone', $elements)) {
                        $query->orWhereNotNull('tickets.qa_status');

                        return;
                    }
                    if (in_array('mine', $elements)) {
                        $query->orWhere('tickets.qa_user_id', $user->id);
                    }
                    if (in_array('anyone', $elements)) {
                        $query->orWhere(fn ($query) => $query->whereNotNull('tickets.qa_status')->whereNull('tickets.qa_user_id'));
                    }
                });
            },
        ];
    }

    /* A check asked of somebody in particular is theirs to do. It stays out of everybody
       else's list whatever the filters say, so the queue a checker sees is only work they
       can actually pick up: unclaimed requests, and the ones addressed to them. */
    protected function restrictRows($queryBuilder): void
    {
        $user = request()->user();

        $queryBuilder->where(fn ($query) => $query
            ->where('tickets.qa_status', '!=', TicketQaStatusEnum::REQUESTED->value)
            ->orWhereNull('tickets.qa_status')
            ->orWhereNull('tickets.qa_user_id')
            ->orWhere('tickets.qa_user_id', $user->id));
    }

    protected function pinToTop($queryBuilder): void
    {
        $queryBuilder->orderByRaw("CASE WHEN tickets.qa_status = ? THEN 0 ELSE 1 END", [TicketQaStatusEnum::REQUESTED->value]);
    }

    protected function elementGroupDefault(string $key): ?string
    {
        return match ($key) {
            'qa_status' => 'none',
            'status'    => TicketStatusEnum::RESOLVED->value.','.TicketStatusEnum::PENDING_DEPLOY->value,
            default     => parent::elementGroupDefault($key),
        };
    }

    protected function listTip(): ?string
    {
        return __("By default, we're only showing Done and Waiting for deployment tickets that have no QA verdict. Use the filters to check everything.");
    }

    protected function listTipTitle(): ?string
    {
        return __("QA Tips");
    }

    protected function listTitle(): string
    {
        return __('QA List');
    }

    /**
     * @return array<int, string>
     */
    protected function listIcon(): array
    {
        return ['fal', 'fa-vial'];
    }

    protected function listBreadcrumbs(): array
    {
        return array_merge(
            $this->ticketsBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->ticketsRoute('qa_list'),
                        'label' => __('QA List'),
                    ],
                ],
            ]
        );
    }
}
