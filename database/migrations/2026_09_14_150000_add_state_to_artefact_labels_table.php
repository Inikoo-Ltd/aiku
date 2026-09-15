<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefact_labels', function (Blueprint $table) {
            $table->string('state')->default(ArtefactLabelStateEnum::RAW->value)->index();
            $table->timestampTz('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('artefact_labels', function (Blueprint $table) {
            $table->dropColumn(['state', 'published_at']);
        });
    }
};
