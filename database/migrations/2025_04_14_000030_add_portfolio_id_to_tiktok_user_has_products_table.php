<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 14:34:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tiktok_user_has_products', function (Blueprint $table) {
            $table->unsignedInteger('portfolio_id')->index()->nullable();
            $table->foreign('portfolio_id')->references('id')->on('portfolios');
        });
    }


    public function down(): void
    {
        Schema::table('tiktok_user_has_products', function (Blueprint $table) {
            $table->dropForeign(['portfolio_id']);
            $table->dropColumn(['portfolio_id']);
        });
    }
};
