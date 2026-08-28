<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * `orders.is_invoiced` has existed since 2022 and nothing has ever written it: 5 of 1,342,595
     * orders carry true, and the table goes back to 2003. Nothing read it either until the marketing
     * dashboard did, and trusting it made the pending-revenue figure repeat invoiced revenue instead
     * of leading it.
     *
     * Whether an order is invoiced is answered by looking for the invoice. That is an index-driven
     * anti join on invoices_order_id_index at 0.009ms a probe, so the flag bought no speed - only the
     * obligation to keep it true through invoice creation, deletion, voiding and every in_process
     * change, silently wrong the moment one path forgot.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_invoiced');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_invoiced')->default(false);
        });
    }
};
