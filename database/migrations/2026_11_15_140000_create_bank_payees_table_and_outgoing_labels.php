<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bank_payees', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('key');
            $table->string('name');
            $table->string('category')->index();
            $table->string('model_type')->nullable();
            $table->unsignedInteger('model_id')->nullable();
            $table->string('source')->default('auto');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestampsTz();
            $table->unique(['group_id', 'key']);
            $table->index(['model_type', 'model_id']);
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('payee_key')->nullable()->index();
            $table->string('out_category')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn(['payee_key', 'out_category']);
        });
        Schema::dropIfExists('bank_payees');
    }
};
