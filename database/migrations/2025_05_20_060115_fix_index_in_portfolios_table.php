<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 14:14:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {

            DB::statement('ALTER TABLE portfolios DROP CONSTRAINT portfolios_customer_id_product_id_unique');
            $table->unique(['customer_sales_channel_id', 'item_id']);
        });
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropUnique(['customer_sales_channel_id', 'item_id']);
            $table->unique(['customer_id', 'index_id']);

        });
    }
};
