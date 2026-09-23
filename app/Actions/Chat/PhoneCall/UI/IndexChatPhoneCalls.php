<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatPhoneCall;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexChatPhoneCalls extends OrgAction
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(Group|Organisation|Shop $parent, ?string $prefix = null, ?User $viewer = null): LengthAwarePaginator
    {
        $visibility = $this->visibilityFor($parent, $viewer);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->whereStartWith('users.contact_name', $value)
                    ->orWhereStartWith('chat_phone_calls.contact_name', $value)
                    ->orWhere('chat_phone_calls.notes', 'ilike', '%'.$value.'%');
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(ChatPhoneCall::class)
            ->join('users', 'users.id', '=', 'chat_phone_calls.user_id')
            ->leftJoin('shops', 'shops.id', '=', 'chat_phone_calls.shop_id')
            ->leftJoin('chat_sessions', 'chat_sessions.id', '=', 'chat_phone_calls.chat_session_id');

        if ($parent instanceof Shop) {
            $query->where('chat_phone_calls.shop_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('chat_phone_calls.organisation_id', $parent->id);
        } else {
            $query->where('chat_phone_calls.group_id', $parent->id);
        }

        $visibility($query);

        $query->select([
            'chat_phone_calls.id',
            'chat_phone_calls.status',
            'chat_phone_calls.contact_type',
            'chat_phone_calls.contact_name',
            'chat_phone_calls.notes',
            'chat_phone_calls.started_at',
            'chat_phone_calls.ended_at',
            'chat_phone_calls.duration_seconds',
            'chat_phone_calls.customer_id',
            'users.contact_name as agent_name',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'chat_sessions.ulid as chat_session_ulid',
        ]);

        foreach ($this->getElementGroups($parent, $viewer) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query
            ->defaultSort('-started_at')
            ->allowedSorts(['started_at', 'duration_seconds', 'agent_name', 'contact_name', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * What somebody wrote about a call is read by the people who work that shop and by whoever
     * oversees the organisation, never by everybody who can open the page. A shop page has
     * already been authorised against its shop, so only the wider pages are narrowed.
     */
    private function visibilityFor(Group|Organisation|Shop $parent, ?User $viewer): Closure
    {
        return function ($query) use ($parent, $viewer) {
            if (!$viewer || $parent instanceof Shop) {
                return;
            }

            if ($parent instanceof Organisation && $viewer->authTo(['org-supervisor.'.$parent->id, 'org-admin.'.$parent->id])) {
                return;
            }

            if ($parent instanceof Group && $viewer->authTo(['group-overview'])) {
                return;
            }

            $shopIds = $this->workableShopIdsFor($viewer);

            $query->where(function ($where) use ($shopIds, $viewer) {
                $where->whereIn('chat_phone_calls.shop_id', $shopIds)
                    ->orWhere('chat_phone_calls.user_id', $viewer->id);
            });
        };
    }

    private function getElementGroups(Group|Organisation|Shop $parent, ?User $viewer = null): array
    {
        $counts = ChatPhoneCall::query()
            ->tap($this->visibilityFor($parent, $viewer))
            ->when($parent instanceof Shop, fn ($q) => $q->where('shop_id', $parent->id))
            ->when($parent instanceof Organisation, fn ($q) => $q->where('organisation_id', $parent->id))
            ->when($parent instanceof Group, fn ($q) => $q->where('group_id', $parent->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $elements = [];
        foreach (ChatPhoneCallStatusEnum::cases() as $case) {
            $elements[$case->value] = [
                ChatPhoneCallStatusEnum::labels()[$case->value] ?? $case->value,
                (int) ($counts[$case->value] ?? 0),
            ];
        }

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => $elements,
                'engine'   => fn ($query, $elements) => $query->whereIn('chat_phone_calls.status', $elements),
            ],
        ];
    }

    public function tableStructure(Group|Organisation|Shop $parent, ?string $prefix = null, ?User $viewer = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $viewer) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent, $viewer) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'started_at', label: __('Started'), canBeHidden: false, sortable: true)
                ->column(key: 'agent_name', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact', label: __('Spoke to'), canBeHidden: false)
                ->column(key: 'shop_name', label: __('Shop'))
                ->column(key: 'duration', label: __('Duration'), sortable: true)
                ->column(key: 'status', label: __('Status'), sortable: true)
                ->column(key: 'notes', label: __('What came out of it'), canBeHidden: false)
                ->defaultSort('-started_at');
        };
    }
}
