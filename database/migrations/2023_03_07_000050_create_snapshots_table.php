<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 13:14:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('scope')->index();
            $table->string('publisher_type')->nullable();
            $table->unsignedInteger('publisher_id')->nullable();
            $table->string('parent_type')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onUpdate('cascade')->onDelete('cascade');
            $table->string('builder')->index();
            $table->string('state')->default(SnapshotStateEnum::UNPUBLISHED->value);
            $table->dateTimeTz('published_at')->nullable();
            $table->dateTimeTz('published_until')->nullable();
            $table->string('checksum')->index();
            $table->jsonb('layout');
            $table->text('compiled_layout')->nullable()->comment('e.g. html in case of email builders');
            $table->string('comment')->nullable();
            $table->boolean('first_commit')->default(false);
            $table->boolean('recyclable')->nullable();
            $table->string('recyclable_tag')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->index();

            $table->index(['parent_type', 'parent_id']);
            $table->index(['parent_type', 'parent_id', 'scope']);
            $table->index(['publisher_id', 'publisher_type']);
        });

        Schema::table('websites', function (Blueprint $table) {
            $table->foreign('unpublished_header_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_header_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('unpublished_footer_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_footer_snapshot_id')->references('id')->on('snapshots');
        });

        Schema::table('webpages', function (Blueprint $table) {
            $table->foreign('unpublished_snapshot_id')->references('id')->on('snapshots');
            $table->foreign('live_snapshot_id')->references('id')->on('snapshots');
        });

    }

    public function down(): void
    {

        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['unpublished_snapshot_id', 'live_snapshot_id']);
        });
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['live_header_snapshot_id', 'unpublished_header_snapshot_id', 'live_footer_snapshot_id', 'unpublished_footer_snapshot_id']);
        });
        Schema::dropIfExists('snapshots');
    }
};
