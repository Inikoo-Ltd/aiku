<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-14h-48m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\UI;

use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
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

    protected function pinToTop($queryBuilder): void
    {
        $queryBuilder->orderByRaw("CASE WHEN tickets.qa_status = ? THEN 0 ELSE 1 END", [TicketQaStatusEnum::REQUESTED->value]);
    }

    protected function elementGroupDefault(string $key): ?string
    {
        return $key === 'qa_status' ? 'none' : parent::elementGroupDefault($key);
    }

    protected function listTip(): ?string
    {
        return __("By default, we're only showing Tickets that have no QA verdict. Use the filter to check everything.");
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
