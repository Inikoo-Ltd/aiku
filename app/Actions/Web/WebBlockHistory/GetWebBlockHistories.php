<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Web\WebBlockHistory;

use App\Actions\OrgAction;
use App\Http\Resources\Web\WebBlockHistoriesResource;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockHistory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetWebBlockHistories extends OrgAction
{
    public function handle(Webpage $parent, WebBlock|WebBlockType|null $scope, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(WebBlockHistory::class);
        if ($scope instanceof WebBlock) {
            $queryBuilder->where('web_block_id', $scope->id)
                         ->where('webpage_id', $parent->id);
        } elseif ($scope instanceof WebBlockType) {
            $queryBuilder->where('web_block_type_id', $scope->id)
                        ->where('webpage_id', $parent->id);
        } else {
            $queryBuilder->where('webpage_id', $parent->id);
        }
        $queryBuilder
            ->defaultSort('web_block_histories.id')
            ->select([
                'web_block_histories.id',
                'web_block_histories.layout',
                'web_block_histories.created_at',
            ]);

        return $queryBuilder
            ->allowedSorts(['created_at'])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $webBlockHistories): AnonymousResourceCollection
    {
        return WebBlockHistoriesResource::collection($webBlockHistories);
    }

    public function asController(Webpage $webpage, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($webpage->shop, $request);
        return $this->handle(parent: $webpage, scope: null);
    }
    public function inWebBlock(Webpage $webpage, WebBlock $webBlock, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($webpage->shop, $request);
        return $this->handle(parent: $webpage, scope: $webBlock);
    }
    public function inWebBlockType(Webpage $webpage, WebBlockType $webBlockType, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($webpage->shop, $request);
        return $this->handle(parent: $webpage, scope: $webBlockType);
    }

}
