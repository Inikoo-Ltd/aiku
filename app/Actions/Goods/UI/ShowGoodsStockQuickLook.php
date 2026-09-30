<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-12h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\UI;

use App\Actions\Catalogue\SalesAnalysis\GetSalesAnalysis;
use App\Actions\Catalogue\SalesAnalysis\SalesAnalysisScope;
use App\Actions\Goods\Stock\UI\GetStockShowcase;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Http\Resources\History\HistoryResource;
use App\Models\Goods\Stock;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGoodsStockQuickLook extends OrgAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [];
    }

    public function asController(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($stock);
    }

    public function salesAnalysis(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return GetSalesAnalysis::run(SalesAnalysisScope::forStock($stock), $request->only(['from', 'to', 'compareFrom', 'compareTo', 'organisations', 'shops', 'partners']));
    }

    public function history(Stock $stock, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup(app('group'), $request);

        return HistoryResource::collection(IndexHistory::run($stock, auditScope: GetGoodsHistoryAuditScope::make()->forStock($stock)));
    }

    /**
     * @return array{code: string, name: ?string, url: string, showcase: array, sales_analysis_teaser: array}
     */
    public function handle(Stock $stock): array
    {
        return [
            'code'                  => $stock->code,
            'name'                  => $stock->name,
            'url'                   => route('grp.goods.stocks.show', $stock->slug),
            'showcase'              => GetStockShowcase::run($stock),
            'sales_analysis_teaser' => GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forStock($stock)),
        ];
    }
}
