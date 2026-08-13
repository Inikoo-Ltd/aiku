<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_delivery_deposit_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('created_by')->nullable()->index();
            $table->foreign('created_by')->references('id')->on('users');
            $table->unsignedSmallInteger('deleted_by')->nullable()->index();
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::table('stock_delivery_deposit_applications', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['created_by', 'deleted_by', 'deleted_at']);
        });
    }
};
