<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\Json;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTags extends OrgAction
{
    private bool $inRetina = false;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): Collection
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): Collection
    {
        $this->initialisation($customer->organisation, $request);

        return $this->handle($customer);
    }

    public function inRetina(Customer $customer, ActionRequest $request): Collection
    {
        $this->inRetina = true;
        $this->initialisation($customer->organisation, $request);

        return $this->handle($customer);
    }

    public function handle(Group|Customer|TradeUnit $parent, $prefix = null): Collection
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereStartWith('name', $value)
                    ->orWhereWith('scope', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Tag::class);

        if ($parent instanceof TradeUnit) {
            $queryBuilder->where('scope', TagScopeEnum::PRODUCT_PROPERTY);
        }

        if ($parent instanceof Customer && !$this->inRetina) {
            $queryBuilder->whereIn('scope', [TagScopeEnum::ADMIN_CUSTOMER, TagScopeEnum::USER_CUSTOMER]);
        }

        if ($this->inRetina) {
            $queryBuilder->where('scope', TagScopeEnum::USER_CUSTOMER);
        }

        return $queryBuilder
            ->defaultSort('name')
            ->select(['id', 'name', 'slug', 'scope'])
            ->allowedSorts(['tag_name'])
            ->allowedFilters([$globalSearch])
            ->get();
    }

    public function jsonResponse($tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }
}
