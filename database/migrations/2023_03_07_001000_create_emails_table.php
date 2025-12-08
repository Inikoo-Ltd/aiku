<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 14:10:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('outbox_id')->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes')->onUpdate('cascade')->onDelete('cascade');

            $table->string('parent_type')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('identifier')->nullable()->index();
            $table->string('builder')->index();
            $table->string('subject')->index();

            $table->unsignedInteger('unpublished_snapshot_id')->nullable()->index();
            $table->foreign('unpublished_snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('live_snapshot_id')->nullable()->index();
            $table->foreign('live_snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('screenshot_id')->nullable();
            $table->foreign('screenshot_id')->references('id')->on('media');
            $table->jsonb('data');

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->index(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
