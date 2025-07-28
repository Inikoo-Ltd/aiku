<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:27:53 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->boolean('has_valid_platform_product_id')->default(false);
            $table->boolean('exist_in_platform')->default(false);
            $table->boolean('platform_status')->default(false)->comment('for shopify: variant has correct location ');
            $table->jsonb('platform_possible_matches')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn('has_valid_platform_product_id');
            $table->dropColumn('exist_in_platform');
            $table->dropColumn('platform_status');
            $table->dropColumn('platform_possible_matches');
        });
    }
};
