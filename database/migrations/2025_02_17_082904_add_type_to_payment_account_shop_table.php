<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 16:29:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->string('type')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
