<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:30:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->boolean('is_hidden_in_lists')->default(false);
        });

        Schema::create('slack_channel_organisations', function (Blueprint $table) {
            $table->unsignedInteger('slack_channel_id');
            $table->foreign('slack_channel_id')->references('id')->on('slack_channels')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->primary(['slack_channel_id', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channel_organisations');
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->dropColumn('is_hidden_in_lists');
        });
    }
};
