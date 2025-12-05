<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 22:28:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_unit_family_stats', function (Blueprint $table): void {
            foreach (TradeUnitStatusEnum::cases() as $case) {
                $table->unsignedInteger('number_trade_units_status_'.$case->snake())->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trade_unit_family_stats', function (Blueprint $table): void {
            foreach (TradeUnitStatusEnum::cases() as $case) {
                $table->dropColumn('number_trade_units_status_'.$case->snake());
            }
        });
    }
};
