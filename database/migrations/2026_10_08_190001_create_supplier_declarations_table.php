<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_declarations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('upload_id')->nullable()->index();
            $table->foreign('upload_id')->references('id')->on('uploads')->nullOnDelete();
            $table->string('company')->nullable();
            $table->string('signed_by')->nullable();
            $table->string('position')->nullable();
            $table->date('signed_on')->nullable();
            $table->jsonb('answers');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_declarations');
    }
};
