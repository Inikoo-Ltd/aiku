<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 13:37:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_customer_sales_channels')->default(0);
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->unsignedInteger("number_customer_sales_channels_platform_type_".$platform->snake())->default(0);
            }
        });

        Schema::table('customer_stats', function (Blueprint $table) {
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->dropColumn("number_platforms_type_".$platform->snake());
            }
        });
    }


    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('number_customer_sales_channels');
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->dropColumn("number_customer_sales_channels_platform_type_".$platform->snake());
            }
        });

        Schema::table('customer_stats', function (Blueprint $table) {
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->unsignedInteger("number_platforms_type_".$platform->snake())->default(0);
            }
        });
    }
};
