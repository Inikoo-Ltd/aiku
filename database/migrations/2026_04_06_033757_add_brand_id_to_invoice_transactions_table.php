<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 12:05:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('brand_id')->index()->nullable();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });
    }
};
