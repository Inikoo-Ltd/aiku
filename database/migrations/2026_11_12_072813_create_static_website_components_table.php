<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('static_website_components', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('static_website_id')->index();
            $table->foreign('static_website_id')->references('id')->on('static_websites')->cascadeOnDelete();
            $table->string('code');
            $table->text('html');
            $table->timestampTz('crawled_at')->nullable();
            $table->timestampsTz();
            $table->unique(['static_website_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_website_components');
    }
};
