<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 17:34:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('user_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_current_api_tokens')->default(0);
            $table->unsignedInteger('number_expired_api_tokens')->default(0);
        });

        Schema::table('group_sysadmin_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_current_api_tokens')->default(0);
            $table->unsignedInteger('number_expired_api_tokens')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('user_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_api_tokens');
            $table->dropColumn('number_expired_api_tokens');
        });

        Schema::table('group_sysadmin_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_api_tokens');
            $table->dropColumn('number_expired_api_tokens');
        });
    }
};
