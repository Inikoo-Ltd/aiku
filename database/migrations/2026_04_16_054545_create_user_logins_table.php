<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 22:12:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_logins', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
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
        Schema::dropIfExists('user_logins');
    }
};
