<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Mar 2026 22:01:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('submitted_gross_amount', 16)->nullable();
            $table->decimal('submitted_net_amount', 16)->nullable();
            $table->double('submitted_discount_factor')->default(1);
            $table->double('current_discount_factor')->default(1);
        });
    }


    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['submitted_gross_amount', 'submitted_net_amount', 'submitted_discount_factor', 'current_discount_factor']);
        });
    }
};
