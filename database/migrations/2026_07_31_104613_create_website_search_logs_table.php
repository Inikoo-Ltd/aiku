<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_search_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedInteger('shop_id')->index();
            $table->unsignedInteger('website_id')->index();
            $table->unsignedInteger('web_user_id')->nullable()->index();
            $table->string('scope')->index();
            $table->string('query');
            $table->string('session_id', 64)->nullable()->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->text('clicked_url')->nullable();
            $table->dateTimeTz('clicked_at')->nullable();
            $table->timestampsTz();
            $table->index('created_at');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('website_search_logs');
    }
};
