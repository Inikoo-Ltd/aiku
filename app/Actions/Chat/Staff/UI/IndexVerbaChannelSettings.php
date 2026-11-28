<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\SlackChannel;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\User;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Verba settings (INI-595), for Verba admins only: every public channel of the group, which organisations get it in
 * their V·Desks list and whether it is hidden from the lists. Private channels and DMs never come here.
 */
class IndexVerbaChannelSettings extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(GroupPermissionsEnum::VERBA_ADMIN->value);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function handle(Group $group, string $prefix = 'channels'): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $nameMatches = AllowedFilter::callback('global', fn ($query, $value) => $query->whereStartWith('slack_channels.name', $value));

        $channels = QueryBuilder::for(SlackChannel::class)
            ->kept()
            ->where('group_id', $group->id)
            ->with('organisations:id')
            ->defaultSort('name')
            ->allowedSorts(['name'])
            ->allowedFilters([$nameMatches])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (SlackChannel $channel) => [
                'id'                 => $channel->id,
                'name'               => $channel->name,
                'workspace'          => $channel->slack_team_id ? SlackWorkspace::forTeam($channel->slack_team_id)?->name : null,
                'is_archived'        => $channel->is_archived,
                'is_hidden_in_lists' => $channel->is_hidden_in_lists,
                'organisation_ids'   => $channel->organisations->pluck('id')->all(),
            ]);

        $lastChanges = Audit::query()
            ->selectRaw('distinct on (auditable_id) auditable_id, user_id, created_at')
            ->where('auditable_type', 'SlackChannel')
            ->whereIn('auditable_id', collect($channels->items())->pluck('id'))
            ->orderBy('auditable_id')->orderByDesc('id')
            ->get()->keyBy('auditable_id');
        $names = User::whereIn('id', $lastChanges->pluck('user_id')->filter())->pluck('contact_name', 'id');
        $channels->through(fn (array $row) => [...$row, 'last_change' => ($change = $lastChanges->get($row['id'])) ? [
            'by' => $names[$change->user_id] ?? null,
            'at' => $change->created_at?->toIso8601String(),
        ] : null]);

        return $channels;
    }

    public function tableStructure(string $prefix = 'channels'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->column(key: 'name', label: __('V·Desk'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'organisation_ids', label: __('Shows to'), canBeHidden: false)
                ->column(key: 'is_hidden_in_lists', label: __('Hidden'), canBeHidden: false)
                ->column(key: 'last_change', label: __('Last change'), canBeHidden: false)
                ->defaultSort('name');
        };
    }

    public function htmlResponse(LengthAwarePaginator $channels, ActionRequest $request): Response
    {
        $title = __('Settings');

        return Inertia::render(
            'Chat/VerbaSettings',
            [
                'breadcrumbs'   => $this->getBreadcrumbs(),
                'title'         => $title,
                'pageHead'      => [
                    'title' => $title,
                    'icon'  => ['icon' => ['fal', 'fa-cog'], 'title' => $title],
                ],
                'organisations' => Organisation::where('group_id', $this->group->id)->orderBy('name')->get(['id', 'name'])->map(fn (Organisation $organisation) => ['id' => $organisation->id, 'name' => $organisation->name]),
                'channels'      => JsonResource::collection($channels),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowStaffMessaging::make()->getBreadcrumbs(null),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-cog',
                        'route' => ['name' => 'grp.chat.staff.settings.index'],
                        'label' => __('Settings'),
                    ],
                ],
            ]
        );
    }
}
