<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 17:15:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::rename('model_has_platforms', 'customer_has_platforms');
        Schema::table('customer_has_platforms', function ($table) {
            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->dropColumn(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_has_platforms', function ($table) {
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();

            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::rename('customer_has_platforms', 'model_has_platforms');
    }
};
