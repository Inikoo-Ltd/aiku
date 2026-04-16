<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 22:25:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('web_user_failed_logins', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
            $table->unsignedSmallInteger('web_user_id')->nullable()->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->nullOnDelete();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address')->nullable();
            $table->jsonb('location')->nullable();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('web_user_failed_logins');
    }
};
