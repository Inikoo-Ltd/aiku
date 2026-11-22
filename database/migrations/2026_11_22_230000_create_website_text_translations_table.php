<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_text_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->string('locale', 8);
            $table->char('source_hash', 40);
            $table->text('source_text');
            $table->text('text');
            $table->string('origin', 16)->default('machine');
            $table->timestampsTz();
            $table->unique(['website_id', 'locale', 'source_hash']);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_text_translations');
    }
};
