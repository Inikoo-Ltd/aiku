<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Sales by trade unit family aggregated across ALL organisations in the group over a date range, in group currency. Sort best-first or worst-first.')]
class TradeUnitFamilySalesTool extends AikuGroupTool
{
    protected function permission(): GroupPermissionsEnum
    {
        return GroupPermissionsEnum::GROUP_REPORTS;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'from'  => ['required', 'date'],
            'to'    => ['required', 'date', 'after_or_equal:from'],
            'sort'  => ['sometimes', 'string', 'in:best,worst'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $group = $this->authorisedGroup($request);
        if (!$group) {
            return Response::error('Permission denied.');
        }

        $direction = (string) $request->string('sort', 'best') === 'worst' ? 'asc' : 'desc';

        $families = TradeUnitFamily::where('trade_unit_families.group_id', $group->id)
            ->leftJoin('trade_unit_family_time_series', function ($join) {
                $join->on('trade_unit_family_time_series.trade_unit_family_id', '=', 'trade_unit_families.id')
                    ->where('trade_unit_family_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value);
            })
            ->leftJoin('trade_unit_family_time_series_records', function ($join) use ($request) {
                $join->on('trade_unit_family_time_series_records.trade_unit_family_time_series_id', '=', 'trade_unit_family_time_series.id')
                    ->whereBetween('trade_unit_family_time_series_records.from', [
                        $request->date('from'),
                        $request->date('to')->endOfDay(),
                    ]);
            })
            ->groupBy('trade_unit_families.id', 'trade_unit_families.code', 'trade_unit_families.name')
            ->selectRaw('trade_unit_families.code, trade_unit_families.name, coalesce(sum(trade_unit_family_time_series_records.sales_grp_currency_external), 0) as sales, coalesce(sum(trade_unit_family_time_series_records.orders), 0) as orders')
            ->orderBy('sales', $direction)
            ->limit($request->integer('limit', 15))
            ->get()
            ->map(fn ($family) => [
                'code'   => $family->code,
                'name'   => $family->name,
                'sales'  => (float) $family->sales,
                'orders' => (int) $family->orders,
            ])
            ->all();

        return Response::json([
            'group'    => $group->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $group->currency->code,
            'sort'     => $direction === 'desc' ? 'best' : 'worst',
            'families' => $families,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from'  => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'    => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'sort'  => $schema->string()->description('best (highest sales first, default) or worst (lowest first)'),
            'limit' => $schema->integer()->description('Maximum families to return, default 15')->minimum(1)->maximum(50),
        ];
    }
}
