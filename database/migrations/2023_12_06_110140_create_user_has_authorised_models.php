<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Dec 2023 19:01:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_has_authorised_models', function (Blueprint $table) {
            $table->unsignedInteger('org_id')->nullable()->comment('Not using organisation_id to avoid confusion with the organisation_id column in the users table');
            $table->foreign('org_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('model_type')->index();
            $table->unsignedSmallInteger('model_id');
            $table->timestampsTz();
            $table->unique(['user_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_authorised_models');
    }
};
