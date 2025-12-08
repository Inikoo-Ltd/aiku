<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Feb 2025 14:14:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('in_process');
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->index('in_process');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('in_process');
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropIndex('in_process');
        });
    }
};
