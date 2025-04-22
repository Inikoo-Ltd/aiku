<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Apr 2025 19:54:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTimeTz('updated_by_customer_at')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('updated_by_customer_at');
        });
    }
};
