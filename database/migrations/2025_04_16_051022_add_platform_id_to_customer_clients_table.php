<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 13:57:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->unsignedSmallInteger('platform_id')->index()->nullable();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropColumn(['platform_id']);
        });
    }
};
