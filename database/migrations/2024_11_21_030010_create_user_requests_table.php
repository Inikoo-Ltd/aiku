<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Nov 2024 12:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('aiku_scoped_section_id')->nullable()->index();
            $table->foreign('aiku_scoped_section_id')->references('id')->on('aiku_scoped_sections')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->dateTimeTz('date');
            $table->string('route_name');
            $table->jsonb('route_params');
            $table->string('os');
            $table->string('device');
            $table->string('browser');
            $table->string('ip_address');
            $table->jsonb('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_requests');
    }
};
