<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->jsonb('translations')->default('{}');
            $table->unsignedSmallInteger('reply_language_id')->nullable()->index();
            $table->foreign('reply_language_id')->references('id')->on('languages')->onDelete('set null');

        });

        Schema::table('poll_options', function (Blueprint $table) {
            $table->unsignedSmallInteger('language_id')->nullable()->index();
            $table->jsonb('translations')->default('{}');

            $table->foreign('language_id')->references('id')->on('languages')->onDelete('set null');
        });

        Schema::table('poll_replies', function (Blueprint $table) {
            $table->unsignedSmallInteger('language_id')->nullable()->index();
            $table->jsonb('translations')->default('{}');

            $table->foreign('language_id')->references('id')->on('languages')->onDelete('set null');
        });
    }


    public function down(): void
    {
        Schema::table('poll_replies', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropColumn(['language_id', 'translations']);
        });

        Schema::table('poll_options', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropColumn(['language_id', 'translations']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['translations']);
        });
    }
};
