<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 10:08:45 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->text('customer_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('shipping_notes')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('customer_notes');
            $table->dropColumn('public_notes');
            $table->dropColumn('internal_notes');
            $table->dropColumn('shipping_notes');
        });
    }
};
