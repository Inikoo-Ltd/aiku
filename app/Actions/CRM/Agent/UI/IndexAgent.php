<?php

namespace App\Actions\CRM\Agent\UI;

use Closure;
use App\Actions\OrgAction;
use App\Services\QueryBuilder;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;

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
            $query->where(function ($query) use ($value) {
                $query
                    ->orWhereStartWith('is_online', $value)
                    ->orWhereStartWith('is_available', $value)
                    ->orWhereStartWith('current_chat_count', $value)
                    ->orWhereHas('user', function ($q) use ($value) {
                        $q
                            ->whereStartWith('contact_name', $value)
                            ->orWhereStartWith('email', $value);
                    });
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ChatAgent::class)
            ->with(['user']);

        return $queryBuilder
            ->defaultSort('id')
            ->select([
                'id',
                'user_id',
                'max_concurrent_chats',
                'current_chat_count',
                'is_online',
                'is_available',
                'auto_accept',
                'specialization',
                'created_at'
            ])
            ->allowedSorts([
                'is_online',
                'is_available',
                'current_chat_count',
                'max_concurrent_chats',
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
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
            ->withModelOperations($modelOperations)
            ->withGlobalSearch()
            ->column(key: 'user_contact_name',label: __('Agent Name'), sortable: true, searchable: true)
            ->column(key: 'is_online',label: __('Online'), sortable: true)
            ->column(key: 'is_available',label: __('Available'), sortable: true)
            ->column(key: 'current_chat_count',label: __('Current Chats'), sortable: true)
            ->column(key: 'max_concurrent_chats',label: __('Max Chats'), sortable: true)
            ->column(key: 'specialization',label: __('Specialization'))
            ->column(key: 'action',label: __('Action'))
            ->defaultSort('user_contact_name');
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