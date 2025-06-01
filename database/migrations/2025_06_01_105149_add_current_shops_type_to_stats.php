<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 18:52:07 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->unsignedSmallInteger('number_current_shops_type_'.$shopType->snake())->default(0);
            }
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->unsignedSmallInteger('number_current_shops_type_'.$shopType->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->dropColumn('number_current_shops_type_'.$shopType->snake());
            }
        });
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->dropColumn('number_current_shops_type_'.$shopType->snake());
            }
        });
    }
};
