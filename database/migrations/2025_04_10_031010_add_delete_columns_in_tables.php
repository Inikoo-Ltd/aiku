<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 14:51:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pallet_deliveries', function (Blueprint $table) {
            $table->renameColumn('delete_comment', 'deleted_note');
            $table->unsignedSmallInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('pallets', function (Blueprint $table) {
            $table->renameColumn('delete_comment', 'deleted_note');
            $table->unsignedSmallInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->renameColumn('delete_comment', 'deleted_note');
            $table->unsignedSmallInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('pallet_deliveries', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_note');
            $table->dropColumn('deleted_by');
        });
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_note');
            $table->dropColumn('deleted_by');
        });
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_note');
            $table->dropColumn('deleted_by');
        });
    }
};
