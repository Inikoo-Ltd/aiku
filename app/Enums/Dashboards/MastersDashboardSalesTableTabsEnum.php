<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 16:40:10 Central Standard Time, Plane Mexico-Tokio
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dashboards;

use App\Actions\Dashboard\IndexMasterShopsSalesTable;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Http\Resources\Dashboards\DashboardHeaderShopsSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalGroupMasterShopsSalesResource;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;

enum MastersDashboardSalesTableTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case MASTER_SHOPS = 'master_shops';


    public function blueprint(): array
    {
        return match ($this) {

            MastersDashboardSalesTableTabsEnum::MASTER_SHOPS => [
                'title' => __('Shops'),
                'icon'  => 'fal fa-store-alt',
            ],

        };
    }


    public function table(Group $group): array
    {

        $header = match ($this) {
            MastersDashboardSalesTableTabsEnum::MASTER_SHOPS => json_decode(DashboardHeaderShopsSalesResource::make($group)->toJson(), true),
        };

        Arr::set($header, 'columns.label.formatted_value', __('Master Shop'));


        $body = match ($this) {
            MastersDashboardSalesTableTabsEnum::MASTER_SHOPS => IndexMasterShopsSalesTable::make()->action($group),
        };

        $totals = match ($this) {
            MastersDashboardSalesTableTabsEnum::MASTER_SHOPS => json_decode(DashboardTotalGroupMasterShopsSalesResource::make($group)->toJson(), true),
        };



        return [
            'header' => $header,
            'body'   => $body,
            'totals' => $totals
        ];
    }

    public static function tables(Group $group): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($group) {
            return [$case->value => $case->table($group)];
        })->all();
    }


}
