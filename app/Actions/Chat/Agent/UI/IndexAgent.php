<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ShopHasChatAgent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgent extends OrgAction
{
    use AsAction;

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

        $query = QueryBuilder::for(ChatAgent::class)
            ->join('users', 'chat_agents.user_id', '=', 'users.id')
            ->leftJoin('shop_has_chat_agents as shca', function ($join) use ($parent) {
                $join->on('shca.chat_agent_id', '=', 'chat_agents.id');
                if ($parent instanceof Organisation) {
                    $join->where('shca.organisation_id', $parent->id);
                } elseif ($parent instanceof Shop) {
                    $join->where('shca.shop_id', $parent->id);
                }
            })
            ->leftJoin('shops', function ($join) {
                $join->on('shops.id', '=', 'shca.shop_id')
                     ->whereNull('shca.deleted_at');
            })
            ->leftJoin('organisations', 'organisations.id', '=', 'shca.organisation_id')
            ->whereNotNull('shca.id')
            ->without('user')
            ->select([
                'chat_agents.id',
                'chat_agents.user_id',
                'users.contact_name as name',
                'chat_agents.max_concurrent_chats',
                'chat_agents.current_chat_count',
                'chat_agents.is_online',
                'chat_agents.is_available',
                'chat_agents.auto_accept',
                'chat_agents.specialization',
                'chat_agents.created_at',
                'organisations.slug as organisation_slug',
                DB::raw("COALESCE(STRING_AGG(DISTINCT shops.code, ', '), '—') as shops"),
                DB::raw("SUM(CASE WHEN shca.deleted_at IS NULL THEN 1 ELSE 0 END) as active_shca_count"),
            ])
            ->groupBy([
                'chat_agents.id',
                'users.contact_name',
                'organisations.slug',
            ]);

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null
            );
        }

        return $query
            ->allowedSorts(['is_online', 'is_available', 'current_chat_count', 'max_concurrent_chats', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function getElementGroups(Group|Organisation|Shop $parent): array
    {
        $activeCount = ShopHasChatAgent::whereNull('deleted_at')
            ->when($parent instanceof Organisation, fn ($q) => $q->where('organisation_id', $parent->id))
            ->when($parent instanceof Shop, fn ($q) => $q->where('shop_id', $parent->id))
            ->distinct('chat_agent_id')
            ->count('chat_agent_id');

        $deletedCount = ShopHasChatAgent::onlyTrashed()
            ->when($parent instanceof Organisation, fn ($q) => $q->where('organisation_id', $parent->id))
            ->when($parent instanceof Shop, fn ($q) => $q->where('shop_id', $parent->id))
            ->whereNotIn('chat_agent_id', function ($sub) use ($parent) {
                $sub->select('chat_agent_id')
                    ->from('shop_has_chat_agents')
                    ->whereNull('deleted_at')
                    ->when($parent instanceof Organisation, fn ($q) => $q->where('organisation_id', $parent->id))
                    ->when($parent instanceof Shop, fn ($q) => $q->where('shop_id', $parent->id));
            })
            ->distinct('chat_agent_id')
            ->count('chat_agent_id');

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
                        $query->havingRaw('SUM(CASE WHEN shca.deleted_at IS NULL THEN 1 ELSE 0 END) > 0');
                    } elseif (\in_array('deleted', $elements) && !\in_array('active', $elements)) {
                        $query->havingRaw('SUM(CASE WHEN shca.deleted_at IS NULL THEN 1 ELSE 0 END) = 0');
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
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
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
