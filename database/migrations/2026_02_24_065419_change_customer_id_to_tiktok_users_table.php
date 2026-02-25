<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Feb 2026 18:48:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->nullable()->change();
            $table->unsignedSmallInteger('organisation_id')->nullable()->change();
            $table->unsignedInteger('customer_id')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->change();
            $table->unsignedSmallInteger('organisation_id')->change();
            $table->unsignedInteger('customer_id')->change();
        });
    }
};
