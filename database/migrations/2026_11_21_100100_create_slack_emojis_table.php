<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_emojis', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('alias_for')->nullable();
            $table->text('url')->nullable();
            $table->unsignedInteger('media_id')->nullable();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            $table->timestampTz('removed_in_slack_at')->nullable()->comment('kept so old messages still show it');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_emojis');
    }
};
