<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 16 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * What a run prints on its labels. Both are per run: the batch code identifies this making of
     * the artefact, and the expiry date is the one chosen when the run was prepared, which may
     * differ from the date standing on the label design.
     */
    public function up(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->string('batch_code', 64)->nullable();
            $table->date('expiry_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dropColumn(['batch_code', 'expiry_date']);
        });
    }
};
