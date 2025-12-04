<?php

namespace App\Actions\CRM\Agent\UI;

use Closure;
use App\Actions\OrgAction;
use App\Services\QueryBuilder;
use App\Models\Goods\TradeUnit;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Spatie\QueryBuilder\AllowedFilter;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndexAgent extends OrgAction
{
    use AsAction;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    /**
     * Controller entry-point
     */
    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /**
     * Main handler
     */
    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereHas('user', function ($q) use ($value) {
                $q->whereStartWith('contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ChatAgent::class)
          ->join('users', 'chat_agents.user_id', '=', 'users.id')
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
              'chat_agents.created_at'
          ])
          ->allowedSorts([
              'is_online',
              'is_available',
              'current_chat_count',
              'max_concurrent_chats',
              'name',
              'created_at'
          ])
          ->allowedFilters([$globalSearch])
          ->withPaginator($prefix, tableName: request()->route()->getName())
          ->withQueryString();
    }

    /**
     * Table structure for Inertia
     */
    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
               ->withModelOperations($modelOperations)
               ->withGlobalSearch()
               ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
               ->column(key: 'is_online', label: __('Status'), canBeHidden: false, sortable: true)
               ->column(key: 'is_available', label: __('Available'), canBeHidden: false, sortable: true)
               ->column(key: 'current_chat_count', label: __('Current Chats'), canBeHidden: false, sortable: true)
               ->column(key: 'max_concurrent_chats', label: __('Max Chats'), canBeHidden: false, sortable: true)
               ->column(key: 'auto_accept', label: __('Auto Accept'), canBeHidden: false, sortable: true) // TAMBAH INI
               ->column(key: 'specialization', label: __('Specialization'), canBeHidden: false)
               ->column(key: 'action', label: __('Action'))
               ->defaultSort('name');
        };
    }

    /**
     * JSON response for API
     */
    public function jsonResponse(LengthAwarePaginator $agents): AnonymousResourceCollection
    {
        return ChatAgentResource::collection($agents);
    }
}
