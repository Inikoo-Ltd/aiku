<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 18:43:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->index('order_id');
            $table->index('family_id');
            $table->index('department_id');
            $table->index('source_alt_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['family_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['source_alt_id']);
        });
    }
};
