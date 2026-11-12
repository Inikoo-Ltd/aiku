<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Web\StaticWebsite\StaticWebsiteStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('static_websites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name');
            $table->string('domain')->unique()->collation('und_ns');
            $table->string('state')->default(StaticWebsiteStateEnum::IN_PROCESS->value)->index();
            $table->string('source_url')->nullable();
            $table->timestampTz('crawled_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_websites');
    }
};
