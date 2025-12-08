<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Jan 2025 20:03:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_user_password_resets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unsignedInteger('web_user_id')->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->cascadeOnDelete();
            $table->string('token', 255)->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_password_resets');
    }
};
