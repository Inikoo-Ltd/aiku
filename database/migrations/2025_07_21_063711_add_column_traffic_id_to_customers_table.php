<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 13:55:55 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('traffic_source_id')->nullable()->index();
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources');
        });
    }


    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['traffic_source_id']);
            $table->dropColumn('traffic_source_id');
        });
    }
};
