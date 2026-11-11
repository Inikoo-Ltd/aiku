<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dateTimeTz('publish_scheduled_at')->nullable()->index();
            $table->unsignedBigInteger('publish_scheduled_by_user_id')->nullable();
            $table->foreign('publish_scheduled_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['publish_scheduled_by_user_id']);
            $table->dropColumn(['publish_scheduled_at', 'publish_scheduled_by_user_id']);
        });
    }
};
