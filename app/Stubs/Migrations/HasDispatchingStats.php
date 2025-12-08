<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 20:19:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Dispatching\Shipment\ShipmentStatusEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasDispatchingStats
{
    public function shipmentsStatsFields(Blueprint $table): Blueprint
    {

        $table->unsignedInteger('number_shipments')->default(0);
        foreach (ShipmentStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_shipments_status_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function shipmentTrackingsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_shipment_trackings')->default(0);

        return $table;
    }

    public function shipperAccountsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_shipper_accounts')->default(0);
        $table->unsignedSmallInteger('number_active_shipper_accounts')->default(0);

        return $table;
    }
}
