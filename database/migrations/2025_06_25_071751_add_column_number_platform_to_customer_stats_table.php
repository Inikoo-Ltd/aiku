<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 21:00:32 Malaysia Time, Sheffield, United Kingdom
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
            $table->unsignedInteger('number_platforms')->default(0);
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->unsignedInteger("number_platforms_type_".$platform->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('number_platforms');
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->dropColumn("number_platforms_type_".$platform->snake());
            }
        });
    }
};
