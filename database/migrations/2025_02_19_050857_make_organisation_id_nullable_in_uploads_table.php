<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Feb 2025 13:27:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->unsignedSmallInteger('organisation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->unsignedSmallInteger('organisation_id')->nullable(false)->change();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
        });
    }
};
