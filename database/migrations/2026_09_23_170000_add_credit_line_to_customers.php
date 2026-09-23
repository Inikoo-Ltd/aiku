<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 16)->default(0)->comment('How far below zero the balance may go when ordering on account');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->comment('Agreed days to settle credit, informative only');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'payment_terms_days']);
        });
    }
};
