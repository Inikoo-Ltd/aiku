<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_share_link_views', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->comment('from Cloudflare');
            $table->unsignedBigInteger('ip_geolocation_id')->nullable()->comment('city, from MaxMind, cached per ip');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_share_link_views', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'ip_geolocation_id']);
        });
    }
};
