<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaffCalls extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return true;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request->user());
    }

    /**
     * Every call this person was rung in or took part in: the calls of the conversations they are in, and any call they joined.
     */
    public function handle(User $user, string $prefix = 'calls'): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $whoMatches = AllowedFilter::callback('global', function ($query, $value) use ($user) {
            $query->where(fn ($where) => $where
                ->whereHas('conversation.participants', fn ($people) => $people->where('users.id', '!=', $user->id)->where(fn ($name) => $name->whereStartWith('users.contact_name', $value)->orWhereStartWith('users.nickname', $value)->orWhereStartWith('users.username', $value)))
                ->orWhereHas('conversation', fn ($conversation) => $conversation->whereStartWith('name', $value)));
        });

        $missedOnly = AllowedFilter::callback('missed', fn ($query, $value) => $value ? $this->missedBy($query, $user) : $query);

        return QueryBuilder::for(StaffCall::class)
            ->where('staff_calls.group_id', $user->group_id)
            ->where(fn ($query) => $query
                ->whereHas('conversation.participants', fn ($people) => $people->where('users.id', $user->id))
                ->orWhereHas('users', fn ($people) => $people->where('users.id', $user->id)))
            ->with(['conversation.participants:id,username,contact_name,nickname,image_id', 'startedBy:id,username,contact_name,nickname', 'meeting'])
            ->defaultSort('-started_at')
            ->allowedSorts(['started_at'])
            ->allowedFilters([$whoMatches, $missedOnly])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (StaffCall $call) => $this->row($call, $user));
    }

    /**
     * A call rang this person and they never picked up: missed, or the caller gave up before anyone answered.
     */
    private function missedBy($query, User $user)
    {
        return $query->where('started_by_user_id', '!=', $user->id)
            ->whereIn('outcome', ['missed', 'cancelled'])
            ->whereDoesntHave('users', fn ($people) => $people->where('users.id', $user->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(StaffCall $call, User $user): array
    {
        $isOutgoing = $call->started_by_user_id === $user->id;
        $others     = $call->conversation?->participants->where('id', '!=', $user->id) ?? collect();
        $other      = $call->conversation?->type === 'dm' ? $others->first() : null;

        $status = match (true) {
            $call->ended_at === null                                     => 'live',
            $call->outcome === 'answered'                                => 'answered',
            !$isOutgoing && in_array($call->outcome, ['missed', 'cancelled']) => 'missed',
            $isOutgoing && $call->outcome === 'missed'                   => 'no_answer',
            default                                                      => $call->outcome ?? 'cancelled',
        };

        return [
            'ulid'              => $call->ulid,
            'direction'         => $isOutgoing ? 'outgoing' : 'incoming',
            'who'               => $other?->chatName() ?? $call->conversation?->name ?? $others->map->chatName()->join(', ') ?: __('Meeting'),
            'avatar'            => $other?->image_id ? User::chatAvatarOf($other->imageSources(0, 64)) : null,
            'started_by'        => $call->startedBy?->chatName(),
            'kind'              => $call->kind,
            'status'            => $status,
            'started_at'        => $call->started_at,
            'duration_seconds'  => $call->answered_at && $call->ended_at ? (int) $call->answered_at->diffInSeconds($call->ended_at) : null,
            'conversation_ulid' => $call->conversation?->ulid,
            'can_call_back'     => $other !== null && $call->conversation && StaffCall::canBeHeldIn($call->conversation),
        ];
    }

    public function tableStructure(string $prefix = 'calls'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->column(key: 'status', label: '', canBeHidden: false)
                ->column(key: 'who', label: __('Who'), canBeHidden: false, searchable: true)
                ->column(key: 'kind', label: __('Type'))
                ->column(key: 'started_at', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'duration_seconds', label: __('Duration'), align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('-started_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $calls, ActionRequest $request): Response
    {
        $title = __('Calls');

        return Inertia::render(
            'Chat/StaffCalls',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => ['icon' => ['fal', 'fa-phone'], 'title' => $title],
                ],
                'missedOnly'  => (bool) $request->input('calls_filter.missed'),
                'calls'       => JsonResource::collection($calls),
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
                        'icon'  => 'fal fa-phone',
                        'route' => ['name' => 'grp.chat.staff.calls.index'],
                        'label' => __('Calls'),
                    ],
                ],
            ]
        );
    }
}
