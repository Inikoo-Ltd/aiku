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
use Spatie\QueryBuilder\AllowedFilter;

class IndexQaTickets extends IndexTickets
{
    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canCheckQa($request->user());
    }

    protected function getElementGroups(Group $group): array
    {
        $elementGroups           = Arr::except(parent::getElementGroups($group), ['mine']);
        $elementGroups['status'] = $this->qaListStatusElementGroup($group, $elementGroups['status']);

        return [
            'qa_checker' => $this->qaAssigneeElementGroup($group),
            ...$elementGroups,
        ];
    }

    /**
     * @param  array{label: string, elements: array<string, array{0: string, 1: int}>, engine: \Closure}  $statusElementGroup
     *
     * @return array{label: string, elements: array<string, array{0: string, 1: int}>, engine: \Closure}
     */
    protected function qaListStatusElementGroup(Group $group, array $statusElementGroup): array
    {
        $base = $this->elementGroupsBase($group)->where(fn ($query) => $this->whereInQaList($query));

        $statusElementGroup['elements'] = collect(TicketStatusEnum::cases())
            ->mapWithKeys(fn (TicketStatusEnum $status) => [
                $status->value => [TicketStatusEnum::labels()[$status->value], (clone $base)->where('status', $status)->count()],
            ])
            ->filter(fn (array $element, string $status) => $element[1] > 0 || in_array($status, [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::PENDING_DEPLOY->value], true))
            ->all();

        return $statusElementGroup;
    }

    protected function whereInQaList($query): void
    {
        $query->whereIn('tickets.status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::PENDING_DEPLOY])
            ->orWhere('tickets.qa_status', TicketQaStatusEnum::CHECKING);
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

    /* A ticket with a checker on it - asked of them, claimed by them, or already given their
       verdict - is theirs, and stays out of every other checker's list. Choosing Everyone in
       the QA assignee filter is the one way to see the whole team's work. */
    protected function restrictRows($queryBuilder, ?string $prefix): void
    {
        $queryBuilder->where(fn ($query) => $this->whereInQaList($query));

        $checkerFilter = explode(',', (string) request()->input(($prefix ? $prefix.'_' : '').'elements.qa_checker', ''));
        $qaStateFilter = request()->input(($prefix ? $prefix.'_' : '').'filter.qa_state');

        if (in_array('everyone', $checkerFilter, true) || filled($qaStateFilter)) {
            return;
        }

        $user = request()->user();

        $queryBuilder->where(fn ($query) => $query
            ->whereNull('tickets.qa_user_id')
            ->orWhere('tickets.qa_user_id', $user->id));
    }

    /**
     * @return array<int, AllowedFilter>
     */
    protected function extraFilters(): array
    {
        return [
            AllowedFilter::callback('qa_state', function ($query, $value) {
                $query->whereIn('tickets.status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::PENDING_DEPLOY]);

                if (in_array($value, [TicketQaStatusEnum::PASSED->value, TicketQaStatusEnum::FAILED->value, TicketQaStatusEnum::SKIPPED->value], true)) {
                    $query->where('tickets.qa_status', $value);
                } elseif ($value === 'in_qa') {
                    $query->whereIn('tickets.qa_status', [TicketQaStatusEnum::REQUESTED, TicketQaStatusEnum::CHECKING]);
                } elseif ($value === 'not_checked') {
                    $query->whereNull('tickets.qa_status');
                }
            }),
        ];
    }

    protected function combinesKindAndModule(): bool
    {
        return true;
    }

    protected function showsCreatedColumn(): bool
    {
        return false;
    }

    protected function pinToTop($queryBuilder): void
    {
        $queryBuilder->orderByRaw("CASE WHEN tickets.qa_status = ? THEN 0 ELSE 1 END", [TicketQaStatusEnum::REQUESTED->value]);
    }

    protected function elementGroupDefault(string $key): ?string
    {
        return match ($key) {
            'qa_status' => 'none',
            default     => parent::elementGroupDefault($key),
        };
    }

    protected function listTip(): ?string
    {
        return __("We show Done and Waiting for deployment tickets, plus every ticket being QA checked whatever its status. By default only those with no QA verdict are listed; use the filters to see the rest.");
    }

    protected function listTipTitle(): ?string
    {
        return __("QA Tips");
    }

    /**
     * @return array{done: int, passed: int, failed: int, skipped: int, in_qa: int, not_checked: int}
     */
    protected function listSummary(): array
    {
        $counts = $this->elementGroupsBase($this->group)
            ->whereIn('tickets.status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::PENDING_DEPLOY])
            ->toBase()
            ->selectRaw('count(*) as done')
            ->selectRaw('count(*) filter (where tickets.qa_status = ?) as passed', [TicketQaStatusEnum::PASSED->value])
            ->selectRaw('count(*) filter (where tickets.qa_status = ?) as failed', [TicketQaStatusEnum::FAILED->value])
            ->selectRaw('count(*) filter (where tickets.qa_status = ?) as skipped', [TicketQaStatusEnum::SKIPPED->value])
            ->selectRaw('count(*) filter (where tickets.qa_status in (?, ?)) as in_qa', [TicketQaStatusEnum::REQUESTED->value, TicketQaStatusEnum::CHECKING->value])
            ->selectRaw('count(*) filter (where tickets.qa_status is null) as not_checked')
            ->first();

        return [
            'done'        => (int) $counts->done,
            'passed'      => (int) $counts->passed,
            'failed'      => (int) $counts->failed,
            'skipped'     => (int) $counts->skipped,
            'in_qa'       => (int) $counts->in_qa,
            'not_checked' => (int) $counts->not_checked,
        ];
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
