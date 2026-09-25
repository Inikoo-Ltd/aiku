<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgent extends OrgAction
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Group|Organisation|Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereHas('user', function ($q) use ($value) {
                $q->whereStartWith('contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $shopIds = $parent instanceof Shop ? [$parent->id] : $parent->shops()->pluck('shops.id')->all();

        $query = QueryBuilder::for(ChatAgent::class)
            ->withTrashed()
            ->join('users', 'chat_agents.user_id', '=', 'users.id')
            ->whereIn('chat_agents.user_id', $this->workingUserIdsQuery($shopIds))
            ->select([
                'chat_agents.id',
                'chat_agents.user_id',
                'users.contact_name as name',
                'chat_agents.max_concurrent_chats',
                'chat_agents.current_chat_count',
                'chat_agents.is_online',
                'chat_agents.is_available',
                'chat_agents.presence_status',
                'chat_agents.last_heartbeat_at',
                'chat_agents.auto_accept',
                'chat_agents.specialization',
                'chat_agents.created_at',
                'chat_agents.deleted_at',
            ]);

        foreach ($this->getElementGroups($shopIds) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null
            );
        }

        $agents = $query
            ->allowedSorts(['is_online', 'is_available', 'current_chat_count', 'max_concurrent_chats', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $this->hydrateShopsAndOrganisation($agents->getCollection(), $parent);

        return $agents;
    }

    /**
     * @param  array<int, int>  $shopIds
     */
    private function workingUserIdsQuery(array $shopIds): QueryBuilderContract
    {
        $permissionNames = collect($shopIds)->flatMap(fn ($shopId) => ["chat.{$shopId}"]);

        $fulfilmentIds = Fulfilment::whereIn('shop_id', $shopIds)->pluck('id');
        $permissionNames = $permissionNames->merge(
            $fulfilmentIds->flatMap(fn ($fulfilmentId) => ["fulfilment-chat.{$fulfilmentId}"])
        );

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', 'User')
            ->whereIn('permissions.name', $permissionNames->all())
            ->select('model_has_roles.model_id');
    }

    /**
     * The shops column and the organisation each agent is edited under are no longer stored
     * on an assignment row: they are read back from the same job-position permissions that
     * grant the access in the first place.
     */
    private function hydrateShopsAndOrganisation(Collection $agents, Group|Organisation|Shop $parent): void
    {
        if ($agents->isEmpty()) {
            return;
        }

        $organisationSlug = match (true) {
            $parent instanceof Organisation => $parent->slug,
            $parent instanceof Shop         => $parent->organisation->slug,
            default                         => null,
        };

        $users = User::whereIn('id', $agents->pluck('user_id'))->get()->keyBy('id');

        foreach ($agents as $agent) {
            $user          = $users->get($agent->user_id);
            $workedShopIds = $user ? $this->workableShopIdsFor($user) : [];
            $workedShops   = Shop::whereIn('id', $workedShopIds)->get(['id', 'code', 'organisation_id']);

            $agent->shops             = $workedShops->isNotEmpty() ? $workedShops->pluck('code')->implode(', ') : '—';
            $agent->organisation_slug = $organisationSlug ?? $workedShops->first()?->organisation?->slug;
        }
    }

    /**
     * @param  array<int, int>  $shopIds
     */
    private function getElementGroups(array $shopIds): array
    {
        $activeCount = ChatAgent::whereIn('user_id', $this->workingUserIdsQuery($shopIds))
            ->count();

        $deletedCount = ChatAgent::onlyTrashed()
            ->whereIn('user_id', $this->workingUserIdsQuery($shopIds))
            ->count();

        return [
            'state' => [
                'label'    => __('State'),
                'default'  => 'active',
                'elements' => [
                    'active'  => [__('Active'), $activeCount],
                    'deleted' => [__('Deleted'), $deletedCount],
                ],
                'engine' => function ($query, $elements) {
                    if (\in_array('active', $elements) && !\in_array('deleted', $elements)) {
                        $query->whereNull('chat_agents.deleted_at');
                    } elseif (\in_array('deleted', $elements) && !\in_array('active', $elements)) {
                        $query->whereNotNull('chat_agents.deleted_at');
                    }
                },
            ],
        ];
    }

    public function tableStructure(Group|Organisation|Shop|null $parent = null, ?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'shops', label: __('Shops'), canBeHidden: false)
                ->column(key: 'is_online', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'is_available', label: __('Available'), canBeHidden: false, sortable: true)
                ->column(key: 'current_chat_count', label: __('Current Chats'), canBeHidden: false, sortable: true)
                ->column(key: 'max_concurrent_chats', label: __('Max Chats'), canBeHidden: false, sortable: true)
                ->column(key: 'auto_accept', label: __('Auto Accept'), canBeHidden: false, sortable: true)
                ->column(key: 'specialization', label: __('Specialization'), canBeHidden: false)
                ->column(key: 'action', label: __('Action'))
                ->defaultSort('name');

            if ($parent) {
                $shopIds = $parent instanceof Shop ? [$parent->id] : $parent->shops()->pluck('shops.id')->all();

                foreach ($this->getElementGroups($shopIds) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $agents): AnonymousResourceCollection
    {
        return ChatAgentResource::collection($agents);
    }
}
