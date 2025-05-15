<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('tracking', 1024)->change();
            $table->string('reference')->nullable()->change();
            $table->unsignedSmallInteger('number_parcels')->nullable();
            $table->jsonb('api_response')->nullable();
            $table->string('combined_label_url');
            $table->jsonb('trackings')->nullable();
            $table->jsonb('tracking_urls')->nullable();
            $table->jsonb('label_urls')->nullable();
            $table->dropColumn('status');



        });
    }


    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('tracking', 255)->change();
            $table->dropColumn('number_parcels');
            $table->dropColumn('api_response');
            $table->dropColumn('combined_label_url');
            $table->dropColumn('trackings');
            $table->dropColumn('tracking_urls');
            $table->dropColumn('label_urls');
            $table->string('status')->nullable();

        });
    }
};
