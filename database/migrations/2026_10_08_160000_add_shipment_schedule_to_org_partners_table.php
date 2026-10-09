<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_partners', function (Blueprint $table) {
            $table->date('next_shipment_on')->nullable();
            $table->unsignedSmallInteger('shipment_every_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('org_partners', function (Blueprint $table) {
            $table->dropColumn(['next_shipment_on', 'shipment_every_days']);
        });
    }
};
