<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:23:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('translation_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('field');
            $table->text('source_text');
            $table->text('machine_text');
            $table->char('machine_text_hash', 32);
            $table->text('corrected_text')->nullable();
            $table->unsignedSmallInteger('rating')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['model_type', 'model_id', 'field', 'machine_text_hash']);
            $table->index(['language_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_reviews');
    }
};
