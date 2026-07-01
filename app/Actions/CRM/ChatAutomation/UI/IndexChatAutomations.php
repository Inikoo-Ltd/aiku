<?php

namespace App\Actions\CRM\ChatAutomation\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatAutomationResource;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexChatAutomations extends OrgAction
{
    use AsAction;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('chat_automations.name', 'ILIKE', "%$value%");
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ChatAutomation::class)
            ->whereHas('shop', function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            })
            ->with('shop')
            ->defaultSort('chat_automations.priority')
            ->allowedSorts([
                AllowedSort::field('name', 'chat_automations.name'),
                AllowedSort::field('trigger_type', 'chat_automations.trigger_type'),
                AllowedSort::field('priority', 'chat_automations.priority'),
                AllowedSort::field('created_at', 'chat_automations.created_at'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::exact('trigger_type'),
                AllowedFilter::exact('shop_id'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No automated messages yet'),
                    'count' => 0,
                ]);

            $table
                ->column(key: 'is_enabled', label: __('Status'), canBeHidden: false)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'trigger_label', label: __('Trigger'), canBeHidden: false)
                ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false)
                ->column(key: 'sent_count', label: __('Sent'), canBeHidden: false)
                ->column(key: 'action', label: __('Action'))
                ->defaultSort('priority');
        };
    }

    public function jsonResponse(LengthAwarePaginator $automations): AnonymousResourceCollection
    {
        return ChatAutomationResource::collection($automations);
    }
}
