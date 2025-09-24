<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Sept 2025 22:44:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tax_numbers', function (Blueprint $table) {
            $table->string('validation_type')->nullable();
            $table->unsignedSmallInteger('manual_validation_user_id')->nullable();
            $table->foreign('manual_validation_user_id')->references('id')->on('users')->nullOnDelete();
            $table->text('manual_validation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tax_numbers', function (Blueprint $table) {
            $table->dropForeign(['manual_validation_user_id']);
            $table->dropColumn('manual_validation_notes');
            $table->dropColumn('manual_validation_user_id');
            $table->dropColumn('validation_type');
        });
    }
};
