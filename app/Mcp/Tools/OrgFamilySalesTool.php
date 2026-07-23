<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Aggregated sales by stock family across a whole organisation (all shops) over a date range, in organisation currency. Sort best-first or worst-first.')]
class OrgFamilySalesTool extends AikuOrganisationTool
{
    protected function permission(): OrganisationPermissionsEnum
    {
        return OrganisationPermissionsEnum::ACCOUNTING_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'from'         => ['required', 'date'],
            'to'           => ['required', 'date', 'after_or_equal:from'],
            'sort'         => ['sometimes', 'string', 'in:best,worst'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $organisation = $this->authorisedOrganisation($request);
        if (!$organisation) {
            return Response::error('Organisation not found or permission denied.');
        }

        $direction = (string) $request->string('sort', 'best') === 'worst' ? 'asc' : 'desc';

        $families = OrgStockFamily::where('org_stock_families.organisation_id', $organisation->id)
            ->leftJoin('org_stock_family_time_series', function ($join) {
                $join->on('org_stock_family_time_series.org_stock_family_id', '=', 'org_stock_families.id')
                    ->where('org_stock_family_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value);
            })
            ->leftJoin('org_stock_family_time_series_records', function ($join) use ($request) {
                $join->on('org_stock_family_time_series_records.org_stock_family_time_series_id', '=', 'org_stock_family_time_series.id')
                    ->whereBetween('org_stock_family_time_series_records.from', [
                        $request->date('from'),
                        $request->date('to')->endOfDay(),
                    ]);
            })
            ->groupBy('org_stock_families.id', 'org_stock_families.code', 'org_stock_families.name')
            ->selectRaw('org_stock_families.code, org_stock_families.name, coalesce(sum(org_stock_family_time_series_records.sales_org_currency_external), 0) as sales, coalesce(sum(org_stock_family_time_series_records.orders), 0) as orders')
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
            'organisation' => $organisation->name,
            'from'         => $request->string('from'),
            'to'           => $request->string('to'),
            'currency'     => $organisation->currency->code,
            'sort'         => $direction === 'desc' ? 'best' : 'worst',
            'families'     => $families,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug')->required(),
            'from'         => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'           => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'sort'         => $schema->string()->description('best (highest sales first, default) or worst (lowest first)'),
            'limit'        => $schema->integer()->description('Maximum families to return, default 15')->minimum(1)->maximum(50),
        ];
    }
}
