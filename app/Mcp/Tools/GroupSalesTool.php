<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Total sales across ALL organisations in the group over a date range, from invoices, in group currency. Includes invoice and refund counts and distinct customers invoiced.')]
class GroupSalesTool extends AikuGroupTool
{
    protected function permission(): GroupPermissionsEnum
    {
        return GroupPermissionsEnum::GROUP_REPORTS;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $group = $this->authorisedGroup($request);
        if (!$group) {
            return Response::error('Permission denied.');
        }

        $sales = Invoice::where('group_id', $group->id)
            ->where('in_process', false)
            ->whereBetween('date', [$request->date('from'), $request->date('to')->endOfDay()])
            ->selectRaw("
                coalesce(sum(grp_net_amount), 0) as net_sales,
                count(*) filter (where type = 'invoice') as number_invoices,
                count(*) filter (where type = 'refund') as number_refunds,
                count(distinct customer_id) as customers_invoiced
            ")
            ->first();

        return Response::json([
            'group'              => $group->name,
            'from'               => $request->string('from'),
            'to'                 => $request->string('to'),
            'currency'           => $group->currency->code,
            'net_sales'          => (float) $sales->net_sales,
            'number_invoices'    => (int) $sales->number_invoices,
            'number_refunds'     => (int) $sales->number_refunds,
            'customers_invoiced' => (int) $sales->customers_invoiced,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'   => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
