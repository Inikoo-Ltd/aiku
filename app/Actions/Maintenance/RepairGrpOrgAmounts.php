<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairGrpOrgAmounts
{
    use WithActionUpdate;


    public string $commandSignature = 'repair:grp_org_amounts';

    public function asCommand(Command $command): void
    {


        $tables = [
            'invoices',
            'orders',
            'invoice_transactions',
            'transactions',

        ];

        foreach ($tables as $table) {



            foreach(DB::table($table)->select(['date','id','grp_exchange','shop_id','source_id'])->whereNull('grp_exchange')->get() as $row){
                $shop=Shop::find($row->shop_id);
                $date=Carbon::parse($row->date);
                $grpExchange = GetHistoricCurrencyExchange::run($shop->currency,$shop->group->currency,$date);

                DB::table($table)->where('id',$row->id)->update(['grp_exchange'=>$grpExchange]);
                $command->info("$table $row->id (".$shop->currency->code.")  Grp Exchange added $grpExchange");

            }

            foreach(DB::table($table)->select(['date','id','org_exchange','shop_id','source_id'])->whereNull('org_exchange')->get() as $row){
                $shop=Shop::find($row->shop_id);
                $date=Carbon::parse($row->date);
                $orgExchange = GetHistoricCurrencyExchange::run($shop->currency,$shop->organisation->currency,$date);

                DB::table($table)->where('id',$row->id)->update(['org_exchange'=>$orgExchange]);
                $command->info("$table $row->id (".$shop->currency->code.")  Org Exchange added $orgExchange");

            }

            DB::table($table)->whereRaw('grp_net_amount!=grp_exchange * net_amount and grp_exchange is not null')->update([
                'grp_net_amount' => DB::raw('grp_exchange * net_amount'),
            ]);

            DB::table($table)->whereRaw('org_net_amount!=org_exchange * org_net_amount and org_exchange is not null')->update([
                'org_net_amount' => DB::raw('org_exchange * net_amount'),
            ]);

            $command->info("$table  recalculated");

        }


        $command->info('grp_net_amount recalculated for all invoice_transactions');


    }

}
