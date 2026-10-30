<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->unsignedInteger('branched_from_id')->nullable()->index()->comment('launched artefact this experiment was branched from to be redeveloped; it ends merged back into it or launched as its own product');
            $table->foreign('branched_from_id')->references('id')->on('artefacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropForeign(['branched_from_id']);
            $table->dropColumn('branched_from_id');
        });
    }
};
