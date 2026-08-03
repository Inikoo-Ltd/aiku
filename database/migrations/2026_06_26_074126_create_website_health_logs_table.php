<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 23:07:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->boolean('is_up')->default(false);
            $table->integer('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('last_deployment_date')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('website_health_logs');
    }
};
