<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Production\Artefact\ArtefactStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->string('state')->default(ArtefactStateEnum::IN_PROCESS->value)->index();
        });
    }

    public function down(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
