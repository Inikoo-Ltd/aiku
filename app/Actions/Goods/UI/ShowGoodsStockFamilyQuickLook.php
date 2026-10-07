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
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Http\Resources\History\HistoryResource;
use App\Models\Goods\StockFamily;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGoodsStockFamilyQuickLook extends OrgAction
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

    public function asController(StockFamily $stockFamily, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($stockFamily);
    }

    public function salesAnalysis(StockFamily $stockFamily, ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return GetSalesAnalysis::run(SalesAnalysisScope::forStockFamily($stockFamily), $request->only(['from', 'to', 'compareFrom', 'compareTo', 'organisations', 'shops', 'partners']));
    }

    public function history(StockFamily $stockFamily, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup(app('group'), $request);

        return HistoryResource::collection(IndexHistory::run($stockFamily, auditScope: GetGoodsHistoryAuditScope::make()->forStockFamily($stockFamily)));
    }

    /**
     * @return array{code: string, name: ?string, url: string, sales_analysis_teaser: array}
     */
    public function handle(StockFamily $stockFamily): array
    {
        return [
            'code'                  => $stockFamily->code,
            'name'                  => $stockFamily->name,
            'url'                   => route('grp.goods.stock-families.show', $stockFamily->slug),
            'sales_analysis_teaser' => GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forStockFamily($stockFamily)),
        ];
    }
}
