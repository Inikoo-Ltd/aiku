<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Aug 2026 20:29:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->change();
        });

        DB::table('services')
            ->whereIn('shop_id', DB::table('shops')->where('type', '!=', ShopTypeEnum::FULFILMENT->value)->pluck('id'))
            ->update(['is_public' => false]);
    }


    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->change();
        });
    }
};
